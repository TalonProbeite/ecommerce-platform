package rabbitmq

import (
	"context"
	"fmt"
	"shop/shared/events"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
)

type EventPublisher struct {
	client *RabbitClient
}

func NewEventPublisher(client *RabbitClient) (*EventPublisher, error) {
	pub := &EventPublisher{
		client: client,
	}

	if err := pub.InitExchange(); err != nil {
		return nil, fmt.Errorf("failed to init exchange: %w", err)
	}

	return pub, nil
}

func (ev *EventPublisher) InitExchange() error {
	return ev.client.Chan.ExchangeDeclare(
		events.ExchangeUser,
		events.ExchangeKindTopic,
		true,
		false,
		false,
		false,
		nil,
	)
}

func (ev *EventPublisher) PublishEvent(eventKey string, payload []byte) error {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	err := ev.client.Chan.PublishWithContext(
		ctx,
		events.ExchangeUser,
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
