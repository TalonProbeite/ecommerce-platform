package rabbitmq

import (
	"context"
	"fmt"
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

	return pub, nil
}

func (ev *EventPublisher) PublishEvent(eventKey, eventExchange string, payload []byte) error {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	err := ev.client.Chan.PublishWithContext(
		ctx,
		eventExchange,
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
