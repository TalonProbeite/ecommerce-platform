package services

import (
	"context"

	amqp "github.com/rabbitmq/amqp091-go"
)

const headerDeliveryCount = "x-delivery-count"

type attemptKey struct{}

func withAttempt(ctx context.Context, attempt int) context.Context {
	return context.WithValue(ctx, attemptKey{}, attempt)
}

func attemptFromContext(ctx context.Context) int {
	attempt, ok := ctx.Value(attemptKey{}).(int)
	if !ok || attempt < 1 {
		return 1
	}

	return attempt
}

func deliveryAttempt(d *amqp.Delivery) int {
	switch v := d.Headers[headerDeliveryCount].(type) {
	case int64:
		return int(v) + 1
	case int32:
		return int(v) + 1
	case int:
		return v + 1
	default:
		return 1
	}
}
