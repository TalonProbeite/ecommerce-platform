package services

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"shop/notification/internal/infra/rabbitmq"

	amqp "github.com/rabbitmq/amqp091-go"
	"golang.org/x/sync/errgroup"
)

var ErrUnrecoverable = errors.New("unrecoverable event error")

type HandlerFunc func(ctx context.Context, body []byte) error

type EventRouter struct {
	handlers  map[string]HandlerFunc
	log       *slog.Logger
	consumers []rabbitmq.EventConsumer
}

func NewEventRouter(
	consumers []rabbitmq.EventConsumer,
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
	g, ctx := errgroup.WithContext(ctx)

	for _, c := range r.consumers {
		g.Go(func() error {
			return r.consume(ctx, c)
		})
	}

	return g.Wait()
}

func (r *EventRouter) consume(ctx context.Context, c rabbitmq.EventConsumer) error {
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
