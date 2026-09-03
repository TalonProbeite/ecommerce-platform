package rabbitmq

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
)

const (
	exchangeName    = "events_exchange"
	exchangeType    = "direct"
	eventRegistered = "user.registered"
)

type UserRegisteredEvent struct {
	Email string `json:"email"`
	Name  string `json:"json"`
}

type EventPublisher struct {
	client *RabbitClient
}

func NewEventPublisher(client *RabbitClient) (*EventPublisher, error) {
	pub := &EventPublisher{client: client}
	
	if err := pub.InitExchange(); err != nil {
		return nil, fmt.Errorf("failed to init exchange: %w", err)
	}

	return pub, nil
}

func (ev *EventPublisher) InitExchange() error {
	return ev.client.Chan.ExchangeDeclare(
		exchangeName,
		exchangeType,
		true, 
		false, 
		false,
		false,
		nil,  
	)
}

func (ev *EventPublisher) PublishRegistration(email, name string) error {
	payload := UserRegisteredEvent{
		Email: email,
		Name:  name,
	}

	body, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal registration event: %w", err)
	}

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	err = ev.client.Chan.PublishWithContext(
		ctx,
		exchangeName,
		eventRegistered,
		false,
		false,
		amqp.Publishing{
			ContentType:  "application/json",
			DeliveryMode: amqp.Persistent,
			Body:         body,
			Timestamp:    time.Now(),
		},
	)

	if err != nil {
		return fmt.Errorf("failed to publish registration event: %w", err)
	}

	return nil
}