package rabbitmq

import (
	"errors"
	"shop/shared/events"
	sharedrabbit "shop/shared/rabbitmq"
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

func TestInitTopology_NilClientOrChannel(t *testing.T) {
	if err := InitTopology(nil); err == nil {
		t.Fatal("expected error for nil client, got nil")
	}

	if err := InitTopology(&sharedrabbit.RabbitClient{}); err == nil {
		t.Fatal("expected error for nil channel, got nil")
	}
}

func TestInitTopology_DeclaresUserExchange(t *testing.T) {
	mock := &mockExchangeDeclarer{}

	if err := initTopology(mock); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if mock.calls != 1 {
		t.Fatalf("expected 1 ExchangeDeclare call, got %d", mock.calls)
	}
	if mock.name != events.ExchangeUser {
		t.Errorf("expected exchange %q, got %q", events.ExchangeUser, mock.name)
	}
	if mock.kind != events.ExchangeKindTopic {
		t.Errorf("expected kind %q, got %q", events.ExchangeKindTopic, mock.kind)
	}
	if !mock.durable || mock.autoDelete || mock.internal || mock.noWait || mock.args != nil {
		t.Errorf("unexpected declare flags: durable=%v autoDelete=%v internal=%v noWait=%v args=%v",
			mock.durable, mock.autoDelete, mock.internal, mock.noWait, mock.args)
	}
}

func TestInitTopology_PropagatesDeclareError(t *testing.T) {
	wantErr := errors.New("broker unavailable")
	mock := &mockExchangeDeclarer{err: wantErr}

	err := initTopology(mock)
	if !errors.Is(err, wantErr) {
		t.Fatalf("expected error wrapping %v, got %v", wantErr, err)
	}
}
