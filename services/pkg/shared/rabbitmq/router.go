package rabbitmq

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"sync"

	amqp "github.com/rabbitmq/amqp091-go"
)

var ErrUnrecoverable = errors.New("unrecoverable event error")

type HandlerFunc func(ctx context.Context, body []byte) error

type EventRouter struct {
	handlers  map[string]HandlerFunc
	log       *slog.Logger
	consumers []EventConsumer
}

func NewEventRouter(
	consumers []EventConsumer,
	handlers map[string]HandlerFunc,
	log *slog.Logger,
) *EventRouter {
	return &EventRouter{
		consumers: consumers,
		handlers:  handlers,
		log:       log,
	}
}

func (r *EventRouter) Run(ctx context.Context) error {
	ctx, cancel := context.WithCancel(ctx)
	defer cancel()

	var (
		wg     sync.WaitGroup
		once   sync.Once
		runErr error
	)

	for _, c := range r.consumers {
		wg.Add(1)

		go func() {
			defer wg.Done()

			if err := r.consume(ctx, c); err != nil {
				once.Do(func() {
					runErr = err
					cancel()
				})
			}
		}()
	}

	wg.Wait()

	return runErr
}

func (r *EventRouter) consume(ctx context.Context, c EventConsumer) error {
	for {
		select {
		case <-ctx.Done():
			return nil
		case d, ok := <-c.Deliveries:
			if !ok {
				return fmt.Errorf("deliveries channel closed for queue %s", c.Queue)
			}

			r.process(ctx, c.Queue, &d)
		}
	}
}

func (r *EventRouter) process(ctx context.Context, queue string, d *amqp.Delivery) {
	handler, ok := r.handlers[d.RoutingKey]
	if !ok {
		r.log.Warn("no handler for routing key", "queue", queue, "key", d.RoutingKey)
		r.reject(d, false)
		return
	}

	ctx = WithAttempt(ctx, deliveryAttempt(d))

	err := r.safeHandle(ctx, handler, d.Body)
	if err == nil {
		r.ack(d)
		return
	}

	requeue := !errors.Is(err, ErrUnrecoverable)

	r.log.Error(
		"event handling failed",
		"queue", queue,
		"key", d.RoutingKey,
		"requeue", requeue,
		"error", err,
	)

	r.reject(d, requeue)
}

func (r *EventRouter) safeHandle(ctx context.Context, h HandlerFunc, body []byte) (err error) {
	defer func() {
		if rec := recover(); rec != nil {
			err = fmt.Errorf("handler panic: %v", rec)
		}
	}()

	return h(ctx, body)
}

func (r *EventRouter) ack(d *amqp.Delivery) {
	if err := d.Ack(false); err != nil {
		r.log.Error("ack failed", "key", d.RoutingKey, "error", err)
	}
}

func (r *EventRouter) reject(d *amqp.Delivery, requeue bool) {
	if err := d.Nack(false, requeue); err != nil {
		r.log.Error("nack failed", "key", d.RoutingKey, "error", err)
	}
}
