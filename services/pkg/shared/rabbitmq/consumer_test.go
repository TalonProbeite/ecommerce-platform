package rabbitmq

import (
	"errors"
	"slices"
	"strings"
	"testing"

	amqp "github.com/rabbitmq/amqp091-go"
)

type mockChannelConsumer struct {
	qosCalls    int
	qosPrefetch int
	qosErr      error

	consumed     []string
	consumeErr   error
	consumeErrOn string
	deliveries   <-chan amqp.Delivery
}

func (m *mockChannelConsumer) Qos(prefetchCount, prefetchSize int, global bool) error {
	m.qosCalls++
	m.qosPrefetch = prefetchCount

	return m.qosErr
}

func (m *mockChannelConsumer) Consume(
	queue, consumer string,
	autoAck, exclusive, noLocal, noWait bool,
	args amqp.Table,
) (<-chan amqp.Delivery, error) {
	if m.consumeErr != nil && queue == m.consumeErrOn {
		return nil, m.consumeErr
	}

	m.consumed = append(m.consumed, queue)

	return m.deliveries, nil
}

func TestNewConsumer_NilClientOrChannel(t *testing.T) {
	if _, err := NewConsumer(nil, []string{"q"}); err == nil {
		t.Fatal("expected error for nil client, got nil")
	}

	if _, err := NewConsumer(&RabbitClient{}, []string{"q"}); err == nil {
		t.Fatal("expected error for nil channel, got nil")
	}
}

func TestNewConsumer_SetsQoSAndSubscribes(t *testing.T) {
	deliveries := make(chan amqp.Delivery)
	mock := &mockChannelConsumer{deliveries: deliveries}

	consumer, err := newConsumer(mock, []string{"first.q", "second.q"})
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if mock.qosCalls != 1 {
		t.Fatalf("expected 1 Qos call, got %d", mock.qosCalls)
	}
	if mock.qosPrefetch != prefetchCount {
		t.Errorf("expected prefetch %d, got %d", prefetchCount, mock.qosPrefetch)
	}
	if !slices.Equal(mock.consumed, []string{"first.q", "second.q"}) {
		t.Errorf("unexpected consumed queues: %v", mock.consumed)
	}

	if len(consumer.ConsumerList) != 2 {
		t.Fatalf("expected 2 consumers, got %d", len(consumer.ConsumerList))
	}
	for i, want := range []string{"first.q", "second.q"} {
		if consumer.ConsumerList[i].Queue != want {
			t.Errorf("consumer %d: expected queue %q, got %q", i, want, consumer.ConsumerList[i].Queue)
		}
		if consumer.ConsumerList[i].Deliveries != deliveries {
			t.Errorf("consumer %d: unexpected deliveries channel", i)
		}
	}
}

func TestNewConsumer_PropagatesQosError(t *testing.T) {
	wantErr := errors.New("qos unavailable")
	mock := &mockChannelConsumer{qosErr: wantErr}

	_, err := newConsumer(mock, []string{"q"})
	if !errors.Is(err, wantErr) {
		t.Fatalf("expected error wrapping %v, got %v", wantErr, err)
	}
	if len(mock.consumed) != 0 {
		t.Fatalf("expected no Consume calls, got %v", mock.consumed)
	}
}

func TestNewConsumer_PropagatesConsumeError(t *testing.T) {
	wantErr := errors.New("queue unavailable")
	mock := &mockChannelConsumer{consumeErr: wantErr, consumeErrOn: "broken.q"}

	_, err := newConsumer(mock, []string{"broken.q"})
	if !errors.Is(err, wantErr) {
		t.Fatalf("expected error wrapping %v, got %v", wantErr, err)
	}
	if err == nil || !strings.Contains(err.Error(), "broken.q") {
		t.Fatalf("expected error to mention queue name, got %v", err)
	}
}
