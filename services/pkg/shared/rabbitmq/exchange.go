package rabbitmq

import (
	"errors"
	"fmt"

	amqp "github.com/rabbitmq/amqp091-go"
)

type exchangeDeclarer interface {
	ExchangeDeclare(name, kind string, durable, autoDelete, internal, noWait bool, args amqp.Table) error
}

// DeclareExchange declares a durable exchange with no extra arguments.
// The caller owns the exchange name and kind, so this function is not tied
// to any service-specific routing keys.
func DeclareExchange(client *RabbitClient, name, kind string) error {
	if client == nil || client.Chan == nil {
		return errors.New("rabbitmq client or channel is nil")
	}

	return declareExchange(client.Chan, name, kind)
}

func declareExchange(ch exchangeDeclarer, name, kind string) error {
	if name == "" {
		return errors.New("exchange name is empty")
	}
	if kind == "" {
		return errors.New("exchange kind is empty")
	}

	if err := ch.ExchangeDeclare(name, kind, true, false, false, false, nil); err != nil {
		return fmt.Errorf("failed to declare exchange %q: %w", name, err)
	}

	return nil
}
