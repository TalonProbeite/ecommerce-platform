package rabbitmq

import (
	"errors"
	"testing"

	amqp "github.com/rabbitmq/amqp091-go"
)

type mockExchangeDeclarer struct {
	name       string
	kind       string
	durable    bool
	autoDelete bool
	internal   bool
	noWait     bool
	args       amqp.Table
	err        error
	calls      int
}

func (m *mockExchangeDeclarer) ExchangeDeclare(
	name, kind string,
	durable, autoDelete, internal, noWait bool,
	args amqp.Table,
) error {
	m.calls++
	m.name = name
	m.kind = kind
	m.durable = durable
	m.autoDelete = autoDelete
	m.internal = internal
	m.noWait = noWait
	m.args = args
	return m.err
}

func TestDeclareExchange_NilClientOrChannel(t *testing.T) {
	if err := DeclareExchange(nil, "test.events", "topic"); err == nil {
		t.Fatal("expected error for nil client, got nil")
	}

	if err := DeclareExchange(&RabbitClient{}, "test.events", "topic"); err == nil {
		t.Fatal("expected error for nil channel, got nil")
	}
}

func TestDeclareExchange_EmptyName(t *testing.T) {
	mock := &mockExchangeDeclarer{}

	if err := declareExchange(mock, "", "topic"); err == nil {
		t.Fatal("expected error for empty exchange name, got nil")
	}
	if mock.calls != 0 {
		t.Fatalf("expected no ExchangeDeclare calls, got %d", mock.calls)
	}
}

func TestDeclareExchange_EmptyKind(t *testing.T) {
	mock := &mockExchangeDeclarer{}

	if err := declareExchange(mock, "test.events", ""); err == nil {
		t.Fatal("expected error for empty exchange kind, got nil")
	}
	if mock.calls != 0 {
		t.Fatalf("expected no ExchangeDeclare calls, got %d", mock.calls)
	}
}

func TestDeclareExchange_DeclaresDurableExchange(t *testing.T) {
	mock := &mockExchangeDeclarer{}

	if err := declareExchange(mock, "test.events", "topic"); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if mock.calls != 1 {
		t.Fatalf("expected 1 ExchangeDeclare call, got %d", mock.calls)
	}
	if mock.name != "test.events" {
		t.Errorf("expected exchange %q, got %q", "test.events", mock.name)
	}
	if mock.kind != "topic" {
		t.Errorf("expected kind %q, got %q", "topic", mock.kind)
	}
	if !mock.durable || mock.autoDelete || mock.internal || mock.noWait || mock.args != nil {
		t.Errorf("unexpected declare flags: durable=%v autoDelete=%v internal=%v noWait=%v args=%v",
			mock.durable, mock.autoDelete, mock.internal, mock.noWait, mock.args)
	}
}

func TestDeclareExchange_PropagatesDeclareError(t *testing.T) {
	wantErr := errors.New("broker unavailable")
	mock := &mockExchangeDeclarer{err: wantErr}

	err := declareExchange(mock, "test.events", "topic")
	if !errors.Is(err, wantErr) {
		t.Fatalf("expected error wrapping %v, got %v", wantErr, err)
	}
}
