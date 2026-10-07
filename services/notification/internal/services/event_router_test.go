package services

import (
	"context"
	"errors"
	"fmt"
	"shop/notification/internal/infra/rabbitmq"
	"testing"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

const waitTimeout = 2 * time.Second

func newTestRouter(handlers map[string]HandlerFunc, consumers ...rabbitmq.EventConsumer) *EventRouter {
	return NewEventRouter(consumers, handlers, newTestLogger())
}

func startRouter(t *testing.T, r *EventRouter) (context.CancelFunc, <-chan error) {
	t.Helper()

	ctx, cancel := context.WithCancel(context.Background())
	t.Cleanup(cancel)

	done := make(chan error, 1)
	go func() { done <- r.Run(ctx) }()

	return cancel, done
}

func receive[T any](t *testing.T, ch <-chan T) T {
	t.Helper()

	select {
	case v := <-ch:
		return v
	case <-time.After(waitTimeout):
		t.Fatal("timed out waiting for value")
		var zero T

		return zero
	}
}

func TestEventRouter_Process_AckAndNackDecision(t *testing.T) {
	t.Parallel()

	const knownKey = "known.key"

	tests := []struct {
		name    string
		key     string
		handler HandlerFunc
		want    []ackRecord
	}{
		{
			name:    "successful handler is acked",
			key:     knownKey,
			handler: func(context.Context, []byte) error { return nil },
			want:    []ackRecord{{kind: ackKindAck}},
		},
		{
			name:    "unknown routing key is nacked without requeue",
			key:     "unknown.key",
			handler: func(context.Context, []byte) error { return nil },
			want:    []ackRecord{{kind: ackKindNack, requeue: false}},
		},
		{
			name:    "temporary error is nacked with requeue",
			key:     knownKey,
			handler: func(context.Context, []byte) error { return errors.New("db down") },
			want:    []ackRecord{{kind: ackKindNack, requeue: true}},
		},
		{
			name: "unrecoverable error is nacked without requeue",
			key:  knownKey,
			handler: func(context.Context, []byte) error {
				return fmt.Errorf("bad payload: %w", ErrUnrecoverable)
			},
			want: []ackRecord{{kind: ackKindNack, requeue: false}},
		},
		{
			name:    "handler panic is nacked with requeue",
			key:     knownKey,
			handler: func(context.Context, []byte) error { panic("boom") },
			want:    []ackRecord{{kind: ackKindNack, requeue: true}},
		},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			ack := &fakeAcknowledger{}
			r := newTestRouter(map[string]HandlerFunc{knownKey: tc.handler})
			d := newDelivery(tc.key, []byte("{}"), ack)

			require.NotPanics(t, func() { r.process(context.Background(), "q", &d) })

			assert.Equal(t, tc.want, ack.snapshot())
		})
	}
}

func TestEventRouter_Process_PassesBodyAndAttemptToHandler(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name        string
		headers     amqp.Table
		wantAttempt int
	}{
		{name: "first delivery", headers: nil, wantAttempt: 1},
		{name: "second delivery", headers: amqp.Table{headerDeliveryCount: int64(1)}, wantAttempt: 2},
		{name: "fourth delivery", headers: amqp.Table{headerDeliveryCount: int64(3)}, wantAttempt: 4},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			var (
				gotBody    []byte
				gotAttempt int
			)

			r := newTestRouter(map[string]HandlerFunc{
				"key": func(ctx context.Context, body []byte) error {
					gotBody = body
					gotAttempt = attemptFromContext(ctx)

					return nil
				},
			})

			d := newDelivery("key", []byte(`{"id":1}`), &fakeAcknowledger{})
			d.Headers = tc.headers

			r.process(context.Background(), "q", &d)

			assert.Equal(t, []byte(`{"id":1}`), gotBody)
			assert.Equal(t, tc.wantAttempt, gotAttempt)
		})
	}
}

func TestEventRouter_Run_RoutesByKeyAcrossConsumers(t *testing.T) {
	t.Parallel()

	userCh := make(chan amqp.Delivery, 1)
	orderCh := make(chan amqp.Delivery, 1)
	userGot := make(chan []byte, 1)
	orderGot := make(chan []byte, 1)
	ack := &fakeAcknowledger{}

	r := newTestRouter(
		map[string]HandlerFunc{
			"user.event": func(_ context.Context, body []byte) error {
				userGot <- body

				return nil
			},
			"order.event": func(_ context.Context, body []byte) error {
				orderGot <- body

				return nil
			},
		},
		rabbitmq.EventConsumer{Queue: "user.q", Deliveries: userCh},
		rabbitmq.EventConsumer{Queue: "order.q", Deliveries: orderCh},
	)

	cancel, done := startRouter(t, r)

	userCh <- newDelivery("user.event", []byte("user-body"), ack)
	orderCh <- newDelivery("order.event", []byte("order-body"), ack)

	assert.Equal(t, []byte("user-body"), receive(t, userGot))
	assert.Equal(t, []byte("order-body"), receive(t, orderGot))

	cancel()

	require.NoError(t, receiveErr(t, done))
	assert.Equal(t, []ackRecord{{kind: ackKindAck}, {kind: ackKindAck}}, ack.snapshot())
}

func TestEventRouter_Run_StopsWithoutErrorOnContextCancel(t *testing.T) {
	t.Parallel()

	ch := make(chan amqp.Delivery)
	r := newTestRouter(nil, rabbitmq.EventConsumer{Queue: "q", Deliveries: ch})

	cancel, done := startRouter(t, r)
	cancel()

	require.NoError(t, receiveErr(t, done))
}

func TestEventRouter_Run_ReturnsErrorWhenDeliveriesChannelClosed(t *testing.T) {
	t.Parallel()

	ch := make(chan amqp.Delivery)
	close(ch)

	r := newTestRouter(nil, rabbitmq.EventConsumer{Queue: "broken.q", Deliveries: ch})

	_, done := startRouter(t, r)

	err := receiveErr(t, done)
	require.Error(t, err)
	assert.Contains(t, err.Error(), "broken.q")
}

func receiveErr(t *testing.T, ch <-chan error) error {
	t.Helper()

	select {
	case err := <-ch:
		return err
	case <-time.After(waitTimeout):
		t.Fatal("timed out waiting for router to stop")

		return nil
	}
}
