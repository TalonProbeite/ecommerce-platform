package rabbitmq

import (
	"context"
	"fmt"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
)

// EventPublisher publishes domain events to RabbitMQ exchanges.
type EventPublisher struct {
	client       *RabbitClient
	exchangeName string
	exchangeType string
}

// NewEventPublisher constructs an EventPublisher and declares the exchange.
func NewEventPublisher(client *RabbitClient, exchangeName, exchangeType string) (*EventPublisher, error) {
	pub := &EventPublisher{
		client:       client,
		exchangeName: exchangeName,
		exchangeType: exchangeType,
	}

	if err := pub.InitExchange(); err != nil {
		return nil, fmt.Errorf("failed to init exchange: %w", err)
	}

	return pub, nil
}

// InitExchange declares the target exchange in RabbitMQ.
func (ev *EventPublisher) InitExchange() error {
	return ev.client.Chan.ExchangeDeclare(
		ev.exchangeName,
		ev.exchangeType,
		true,
		false,
		false,
		false,
		nil,
	)
}

// PublishEvent sends a JSON payload event with a routing key to RabbitMQ.
func (ev *EventPublisher) PublishEvent(eventKey string, payload []byte) error {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	err := ev.client.Chan.PublishWithContext(
		ctx,
		ev.exchangeName,
		eventKey,
		false,
		false,
		amqp.Publishing{
			ContentType:  "application/json",
			DeliveryMode: amqp.Persistent,
			Body:         payload,
			Timestamp:    time.Now(),
		},
	)
	if err != nil {
		return fmt.Errorf("failed to publish event: %w", err)
	}

	return nil
}
