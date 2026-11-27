package rabbitmq

import (
	"errors"
	"slices"
	"testing"

	"shop/shared/events"
	sharedrabbit "shop/shared/rabbitmq"

	amqp "github.com/rabbitmq/amqp091-go"
)

func TestUserQueueBindsBothEventGenerations(t *testing.T) {
	keys := map[string]bool{}
	for _, spec := range queues {
		if spec.Name == QueueUserNotifications {
			for _, key := range spec.Keys {
				keys[key] = true
			}
		}
	}
	for _, key := range []string{"user.registered", "user.email_verified", "user.email_verification_requested", "user.welcome_email_requested", "user.email_changed"} {
		if !keys[key] {
			t.Errorf("user queue missing binding for %s", key)
		}
	}
}

func TestQueueNames(t *testing.T) {
	want := []string{QueueUserNotifications, QueueOrderProcessing}
	if got := QueueNames(); !slices.Equal(got, want) {
		t.Fatalf("expected queues %v, got %v", want, got)
	}
}

func TestInitTopology_NilClientOrChannel(t *testing.T) {
	if err := InitTopology(nil); err == nil {
		t.Fatal("expected error for nil client, got nil")
	}

	if err := InitTopology(&sharedrabbit.RabbitClient{}); err == nil {
		t.Fatal("expected error for nil channel, got nil")
	}
}

func TestInitTopology_DeclaresExchangesAndQueues(t *testing.T) {
	mock := &mockTopologyDeclarer{}

	if err := initTopology(mock); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	wantExchanges := []string{events.ExchangeUser, events.ExchangeOrder, events.ExchangeDLX}
	if !slices.Equal(mock.exchanges, wantExchanges) {
		t.Errorf("expected exchanges %v, got %v", wantExchanges, mock.exchanges)
	}

	wantQueues := []string{QueueFailedMessages, QueueUserNotifications, QueueOrderProcessing}
	if !slices.Equal(mock.queues, wantQueues) {
		t.Errorf("expected queues %v, got %v", wantQueues, mock.queues)
	}

	if len(mock.binds) != 9 {
		t.Fatalf("expected 9 bindings, got %d: %v", len(mock.binds), mock.binds)
	}
}

func TestInitTopology_PropagatesDeclareError(t *testing.T) {
	wantErr := errors.New("broker unavailable")
	mock := &mockTopologyDeclarer{err: wantErr}

	if err := initTopology(mock); !errors.Is(err, wantErr) {
		t.Fatalf("expected error wrapping %v, got %v", wantErr, err)
	}
}

type mockTopologyDeclarer struct {
	exchanges []string
	queues    []string
	binds     []string
	err       error
}

func (m *mockTopologyDeclarer) ExchangeDeclare(
	name, kind string,
	durable, autoDelete, internal, noWait bool,
	args amqp.Table,
) error {
	if m.err != nil {
		return m.err
	}

	m.exchanges = append(m.exchanges, name)

	return nil
}

func (m *mockTopologyDeclarer) QueueDeclare(
	name string,
	durable, autoDelete, exclusive, noWait bool,
	args amqp.Table,
) (amqp.Queue, error) {
	if m.err != nil {
		return amqp.Queue{}, m.err
	}

	m.queues = append(m.queues, name)

	return amqp.Queue{Name: name}, nil
}

func (m *mockTopologyDeclarer) QueueBind(queue, key, exchange string, noWait bool, args amqp.Table) error {
	if m.err != nil {
		return m.err
	}

	m.binds = append(m.binds, queue+"|"+key+"|"+exchange)

	return nil
}
