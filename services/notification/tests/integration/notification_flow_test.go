//go:build integration

package integration

import (
	"cmp"
	"errors"
	"os"
	"os/exec"
	"path/filepath"
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mailer"
	"shop/shared/events"
	"slices"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

// Run auth's real publisher in its own module because its implementation is internal.
func TestNotificationFlow_AuthPublisherProducesEmail(t *testing.T) {
	s := newStack(t, nil)
	cmd := exec.CommandContext(t.Context(), "go", "test", "-tags=integration", "-count=1", "-run=^TestPublisherDeliversUserEvent$", "./internal/infra/rabbitmq")
	cmd.Dir = filepath.Join("..", "auth")
	cmd.Env = append(os.Environ(), "TEST_RABBITMQ_URL="+env.amqpURL)
	output, err := cmd.CombinedOutput()
	require.NoError(t, err, "auth publisher test: %s", output)

	msg := waitForMail(t, "test@example.com")
	assert.Equal(t, events.EmailVerificationSubject, msg.subject())
	assert.Contains(t, msg.body(t), "123456")
	logs := s.waitForLogs(t, "test@example.com", 1)
	require.Len(t, logs, 1)
	assert.Equal(t, mailer.SendStatusSent, logs[0].Status)
}

func TestNotificationFlow_EventProducesEmailAndAuditLog(t *testing.T) {
	s := newStack(t, nil)

	tests := []struct {
		name       string
		exchange   string
		key        string
		recipient  string
		event      any
		subject    string
		bodyMarker string
	}{
		{
			name:       "verification request sends verification code",
			exchange:   events.ExchangeUser,
			key:        events.EmailVerificationRequestedEventKey,
			recipient:  "registered@example.com",
			event:      events.EmailVerificationRequestedEvent{Email: "registered@example.com", Code: "482913"},
			subject:    events.EmailVerificationSubject,
			bodyMarker: "482913",
		},
		{
			name:       "welcome request sends welcome mail",
			exchange:   events.ExchangeUser,
			key:        events.WelcomeEmailRequestedEventKey,
			recipient:  "verified@example.com",
			event:      events.WelcomeEmailRequestedEvent{Email: "verified@example.com", Name: "Alice"},
			subject:    events.WelcomeEmailSubject,
			bodyMarker: "Alice",
		},
		{
			name:       "order confirmed",
			exchange:   events.ExchangeOrder,
			key:        events.OrderConfirmedEventKey,
			recipient:  "confirmed@example.com",
			event:      events.OrderConfirmedEvent{OrderID: "order-1001", Email: "confirmed@example.com"},
			subject:    events.OrderConfirmedSubject,
			bodyMarker: "order-1001",
		},
		{
			name:       "order paid",
			exchange:   events.ExchangeOrder,
			key:        events.OrderPaidEventKey,
			recipient:  "paid@example.com",
			event:      events.OrderPaidEvent{OrderID: "order-1002", Email: "paid@example.com", Amount: 149.9},
			subject:    events.OrderPaidSubject,
			bodyMarker: "order-1002",
		},
		{
			name:       "order cancelled",
			exchange:   events.ExchangeOrder,
			key:        events.OrderCancelledEventKey,
			recipient:  "cancelled@example.com",
			event:      events.OrderCancelledPayload{OrderID: "order-1003", Email: "cancelled@example.com", Reason: "out of stock"},
			subject:    events.OrderCancelledSubject,
			bodyMarker: "order-1003",
		},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			s.publishJSON(t, tc.exchange, tc.key, tc.event)

			msg := waitForMail(t, tc.recipient)
			assert.Equal(t, tc.subject, msg.subject())
			assert.Contains(t, msg.body(t), tc.bodyMarker)

			logs := s.waitForLogs(t, tc.recipient, 1)
			require.Len(t, logs, 1)

			got := logs[0]
			assert.Equal(t, tc.key, got.Type)
			assert.Equal(t, "email", got.Channel)
			assert.Equal(t, tc.recipient, got.Recipient)
			assert.Equal(t, mailer.SendStatusSent, got.Status)
			assert.Equal(t, 1, got.Attempts)
			assert.Empty(t, got.Error)
			assert.NotNil(t, got.Payload)
		})
	}
}

func TestNotificationFlow_InvalidPayloadGoesToDeadLetterQueue(t *testing.T) {
	s := newStack(t, nil)

	body := []byte("{definitely not json")
	s.publish(t, events.ExchangeUser, events.EmailVerificationRequestedEventKey, body)

	dead := s.waitForDeadLetter(t)
	assert.Equal(t, body, dead.Body)

	logs, err := s.repo.GetHistory(t.Context(), &domain.HistoryFilters{})
	require.NoError(t, err)
	assert.Empty(t, logs)
}

func TestNotificationFlow_SMTPFailureIsRetriedThenDeadLettered(t *testing.T) {
	const recipient = "retry@example.com"

	errSMTP := errors.New("smtp unavailable")
	s := newStack(t, failingSender{err: errSMTP})

	event := events.EmailVerificationRequestedEvent{Email: recipient, Code: "111222"}
	sent := s.publishJSON(t, events.ExchangeUser, events.EmailVerificationRequestedEventKey, event)

	dead := s.waitForDeadLetter(t)
	assert.JSONEq(t, string(sent), string(dead.Body))

	logs := s.waitForLogs(t, recipient, 2)
	slices.SortFunc(logs, func(a, b domain.EventLog) int {
		return cmp.Compare(a.Attempts, b.Attempts)
	})

	for i := range logs {
		assert.Equal(t, i+1, logs[i].Attempts)
		assert.Equal(t, mailer.SendStatusFailed, logs[i].Status)
		assert.Equal(t, errSMTP.Error(), logs[i].Error)
	}
}
