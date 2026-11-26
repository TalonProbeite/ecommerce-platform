package rabbitmq

import (
	"errors"
	"fmt"
	"shop/shared/events"
	sharedrabbit "shop/shared/rabbitmq"

	amqp "github.com/rabbitmq/amqp091-go"
)

type exchangeDeclarer interface {
	ExchangeDeclare(name, kind string, durable, autoDelete, internal, noWait bool, args amqp.Table) error
}

// InitTopology declares the RabbitMQ exchanges required by the auth service.
func InitTopology(client *sharedrabbit.RabbitClient) error {
	if client == nil || client.Chan == nil {
		return errors.New("rabbitmq client or channel is nil")
	}

	return initTopology(client.Chan)
}

func initTopology(ch exchangeDeclarer) error {
	if err := ch.ExchangeDeclare(
		events.ExchangeUser,
		events.ExchangeKindTopic,
		true,
		false,
		false,
		false,
		nil,
	); err != nil {
		return fmt.Errorf("failed to declare exchange %s: %w", events.ExchangeUser, err)
	}

	return nil
}
