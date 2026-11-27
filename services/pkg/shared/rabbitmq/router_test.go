package rabbitmq

import (
	"bytes"
	"context"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"reflect"
	"strings"
	"sync"
	"testing"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
)

const waitTimeout = 2 * time.Second

func newTestRouter(handlers map[string]HandlerFunc, consumers ...EventConsumer) *EventRouter {
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

			requireNotPanics(t, func() { r.process(context.Background(), "q", &d) })

			if got := ack.snapshot(); !reflect.DeepEqual(got, tc.want) {
				t.Fatalf("expected ack records %v, got %v", tc.want, got)
			}
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
					gotAttempt = AttemptFromContext(ctx)

					return nil
				},
			})

			d := newDelivery("key", []byte(`{"id":1}`), &fakeAcknowledger{})
			d.Headers = tc.headers

			r.process(context.Background(), "q", &d)

			if !bytes.Equal(gotBody, []byte(`{"id":1}`)) {
				t.Errorf("unexpected body: %s", gotBody)
			}
			if gotAttempt != tc.wantAttempt {
				t.Errorf("expected attempt %d, got %d", tc.wantAttempt, gotAttempt)
			}
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
		EventConsumer{Queue: "user.q", Deliveries: userCh},
		EventConsumer{Queue: "order.q", Deliveries: orderCh},
	)

	cancel, done := startRouter(t, r)

	userCh <- newDelivery("user.event", []byte("user-body"), ack)
	orderCh <- newDelivery("order.event", []byte("order-body"), ack)

	if got := receive(t, userGot); !bytes.Equal(got, []byte("user-body")) {
		t.Errorf("unexpected user body: %s", got)
	}
	if got := receive(t, orderGot); !bytes.Equal(got, []byte("order-body")) {
		t.Errorf("unexpected order body: %s", got)
	}

	cancel()

	if err := receiveErr(t, done); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	want := []ackRecord{{kind: ackKindAck}, {kind: ackKindAck}}
	if got := ack.snapshot(); !reflect.DeepEqual(got, want) {
		t.Fatalf("expected ack records %v, got %v", want, got)
	}
}

func TestEventRouter_Run_StopsWithoutErrorOnContextCancel(t *testing.T) {
	t.Parallel()

	ch := make(chan amqp.Delivery)
	r := newTestRouter(nil, EventConsumer{Queue: "q", Deliveries: ch})

	cancel, done := startRouter(t, r)
	cancel()

	if err := receiveErr(t, done); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
}

func TestEventRouter_Run_ReturnsErrorWhenDeliveriesChannelClosed(t *testing.T) {
	t.Parallel()

	ch := make(chan amqp.Delivery)
	close(ch)

	r := newTestRouter(nil, EventConsumer{Queue: "broken.q", Deliveries: ch})

	_, done := startRouter(t, r)

	err := receiveErr(t, done)
	if err == nil {
		t.Fatal("expected error, got nil")
	}
	if !strings.Contains(err.Error(), "broken.q") {
		t.Fatalf("expected error to mention queue, got %v", err)
	}
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

func requireNotPanics(t *testing.T, f func()) {
	t.Helper()

	defer func() {
		if rec := recover(); rec != nil {
			t.Fatalf("unexpected panic: %v", rec)
		}
	}()

	f()
}

func newTestLogger() *slog.Logger {
	return slog.New(slog.NewTextHandler(io.Discard, nil))
}

const (
	ackKindAck  = "ack"
	ackKindNack = "nack"
)

type ackRecord struct {
	kind    string
	requeue bool
}

type fakeAcknowledger struct {
	mu      sync.Mutex
	records []ackRecord
}

func (f *fakeAcknowledger) Ack(_ uint64, _ bool) error {
	f.add(ackRecord{kind: ackKindAck})

	return nil
}

func (f *fakeAcknowledger) Nack(_ uint64, _ bool, requeue bool) error {
	f.add(ackRecord{kind: ackKindNack, requeue: requeue})

	return nil
}

func (f *fakeAcknowledger) Reject(_ uint64, requeue bool) error {
	f.add(ackRecord{kind: ackKindNack, requeue: requeue})

	return nil
}

func (f *fakeAcknowledger) add(r ackRecord) {
	f.mu.Lock()
	defer f.mu.Unlock()

	f.records = append(f.records, r)
}

func (f *fakeAcknowledger) snapshot() []ackRecord {
	f.mu.Lock()
	defer f.mu.Unlock()

	return append([]ackRecord(nil), f.records...)
}

func newDelivery(key string, body []byte, ack *fakeAcknowledger) amqp.Delivery {
	return amqp.Delivery{
		Acknowledger: ack,
		RoutingKey:   key,
		Body:         body,
		DeliveryTag:  1,
	}
}
