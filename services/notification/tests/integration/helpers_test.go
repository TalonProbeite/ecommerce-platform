//go:build integration

package integration

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log/slog"
	"mime/quotedprintable"
	"net/http"
	"net/url"
	"os"
	"shop/notification/internal/config"
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mailer"
	"shop/notification/internal/infra/mongodb"
	"shop/notification/internal/infra/rabbitmq"
	"shop/notification/internal/infra/repository"
	"shop/notification/internal/infra/template"
	"shop/notification/internal/services"
	"shop/shared/events"
	"strings"
	"testing"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

const (
	waitLong     = 20 * time.Second
	pollInterval = 200 * time.Millisecond
	routerStop   = 10 * time.Second
	mailFrom     = "notifications@shop.local"
)

type stack struct {
	repo      *repository.EventRepo
	publisher *amqp.Channel
	dlq       *amqp.Channel
}

func newStack(t *testing.T, sender services.MailSender) *stack {
	t.Helper()

	repo := newRepo(t)

	rabbitClient, err := rabbitmq.NewRabbitClient(env.amqpURL)
	require.NoError(t, err)
	t.Cleanup(func() { assert.NoError(t, rabbitClient.Close()) })

	consumer, err := rabbitmq.NewConsumer(rabbitClient)
	require.NoError(t, err)

	renderer, err := template.NewMessageBuilder("local")
	require.NoError(t, err)

	if sender == nil {
		sender = newRealSender(t)
	}

	userHandler := services.NewUserEventHandler(renderer, sender, repo)
	orderHandler := services.NewOrderEventHandler(renderer, sender, repo)

	handlers := map[string]services.HandlerFunc{
		events.UserRegisteredEventKey:    userHandler.HandleUserRegistered,
		events.UserEmailVerifiedEventKey: userHandler.HandleEmailVerified,
		events.OrderConfirmedEventKey:    orderHandler.HandleOrderConfirmed,
		events.OrderPaidEventKey:         orderHandler.HandleOrderPaid,
		events.OrderCancelledEventKey:    orderHandler.HandleOrderCancelled,
	}

	router := services.NewEventRouter(consumer.ConsumerList, handlers, newLogger())
	startRouter(t, router)

	conn, err := amqp.Dial(env.amqpURL)
	require.NoError(t, err)
	t.Cleanup(func() { assert.NoError(t, conn.Close()) })

	publisher, err := conn.Channel()
	require.NoError(t, err)

	dlq, err := conn.Channel()
	require.NoError(t, err)

	_, err = dlq.QueuePurge(rabbitmq.QueueFailedMessages, false)
	require.NoError(t, err)

	return &stack{repo: repo, publisher: publisher, dlq: dlq}
}

func newRepo(t *testing.T) *repository.EventRepo {
	t.Helper()

	client, err := mongodb.NewMongoClient(env.mongoURI, uniqueDBName())
	require.NoError(t, err)
	t.Cleanup(func() { assert.NoError(t, client.Close()) })

	return repository.NewEventRepo(client)
}

func newRealSender(t *testing.T) *mailer.Sender {
	t.Helper()

	sender, err := mailer.NewSender(&config.SMTPConfig{
		SMTPHost: env.smtpHost,
		SMTPPort: env.smtpPort,
		From:     mailFrom,
	})
	require.NoError(t, err)

	return sender
}

func newLogger() *slog.Logger {
	return slog.New(slog.NewTextHandler(os.Stderr, &slog.HandlerOptions{Level: slog.LevelWarn}))
}

func uniqueDBName() string {
	return fmt.Sprintf("it_%d", time.Now().UnixNano())
}

func startRouter(t *testing.T, router *services.EventRouter) {
	t.Helper()

	ctx, cancel := context.WithCancel(context.Background())
	done := make(chan error, 1)

	go func() { done <- router.Run(ctx) }()

	t.Cleanup(func() {
		cancel()

		select {
		case err := <-done:
			assert.NoError(t, err)
		case <-time.After(routerStop):
			t.Error("event router did not stop in time")
		}
	})
}

func (s *stack) publish(t *testing.T, exchange, key string, body []byte) {
	t.Helper()

	err := s.publisher.PublishWithContext(t.Context(), exchange, key, false, false, amqp.Publishing{
		ContentType:  "application/json",
		DeliveryMode: amqp.Persistent,
		Body:         body,
	})
	require.NoError(t, err)
}

func (s *stack) publishJSON(t *testing.T, exchange, key string, event any) []byte {
	t.Helper()

	body, err := json.Marshal(event)
	require.NoError(t, err)

	s.publish(t, exchange, key, body)

	return body
}

func (s *stack) waitForDeadLetter(t *testing.T) amqp.Delivery {
	t.Helper()

	var msg amqp.Delivery

	require.Eventually(t, func() bool {
		d, ok, err := s.dlq.Get(rabbitmq.QueueFailedMessages, true)
		if err != nil || !ok {
			return false
		}

		msg = d

		return true
	}, waitLong, pollInterval, "no message arrived in the dead letter queue")

	return msg
}

func (s *stack) waitForLogs(t *testing.T, recipient string, atLeast int) []domain.EventLog {
	t.Helper()

	var logs []domain.EventLog

	require.Eventually(t, func() bool {
		got, err := s.repo.GetHistory(context.Background(), &domain.HistoryFilters{Recipient: recipient})
		if err != nil {
			return false
		}

		logs = got

		return len(got) >= atLeast
	}, waitLong, pollInterval, "expected at least %d audit logs for %s", atLeast, recipient)

	return logs
}

type mailAddress struct {
	Mailbox string `json:"Mailbox"`
	Domain  string `json:"Domain"`
}

type mailContent struct {
	Headers map[string][]string `json:"Headers"`
	Body    string              `json:"Body"`
}

type mailMessage struct {
	To      []mailAddress `json:"To"`
	Content mailContent   `json:"Content"`
}

func (m mailMessage) subject() string {
	values := m.Content.Headers["Subject"]
	if len(values) == 0 {
		return ""
	}

	return values[0]
}

func (m mailMessage) body(t *testing.T) string {
	t.Helper()

	decoded, err := io.ReadAll(quotedprintable.NewReader(strings.NewReader(m.Content.Body)))
	require.NoError(t, err)

	return string(decoded)
}

func fetchMail(recipient string) (msgs []mailMessage, err error) {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	query := url.Values{"kind": {"to"}, "query": {recipient}}
	endpoint := env.mailhogAPI + "/api/v2/search?" + query.Encode()

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, endpoint, nil)
	if err != nil {
		return nil, err
	}

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer func() {
		if closeErr := resp.Body.Close(); closeErr != nil && err == nil {
			err = closeErr
		}
	}()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("mailhog search returned status %d", resp.StatusCode)
	}

	var list struct {
		Items []mailMessage `json:"items"`
	}

	if err = json.NewDecoder(resp.Body).Decode(&list); err != nil {
		return nil, err
	}

	return list.Items, nil
}

func waitForMail(t *testing.T, recipient string) mailMessage {
	t.Helper()

	var found []mailMessage

	require.Eventually(t, func() bool {
		msgs, err := fetchMail(recipient)
		if err != nil {
			return false
		}

		found = msgs

		return len(msgs) > 0
	}, waitLong, pollInterval, "no mail arrived in MailHog for %s", recipient)

	require.Len(t, found, 1)

	return found[0]
}

type failingSender struct {
	err error
}

func (f failingSender) SendHTML(string, string, string) error {
	return f.err
}
