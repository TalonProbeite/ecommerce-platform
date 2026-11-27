package rabbitmq

import (
	"errors"
	"fmt"

	amqp "github.com/rabbitmq/amqp091-go"
)

const prefetchCount = 10

type EventConsumer struct {
	Deliveries <-chan amqp.Delivery
	Queue      string
}

type Consumer struct {
	ConsumerList []EventConsumer
}

type channelConsumer interface {
	Qos(prefetchCount, prefetchSize int, global bool) error
	Consume(queue, consumer string, autoAck, exclusive, noLocal, noWait bool, args amqp.Table) (<-chan amqp.Delivery, error)
}

// NewConsumer subscribes to the given queues and returns their delivery streams.
// The caller owns topology declaration, so this function stays service-agnostic.
func NewConsumer(client *RabbitClient, queues []string) (*Consumer, error) {
	if client == nil || client.Chan == nil {
		return nil, errors.New("rabbitmq client or channel is nil")
	}

	return newConsumer(client.Chan, queues)
}

func newConsumer(ch channelConsumer, queues []string) (*Consumer, error) {
	if err := ch.Qos(prefetchCount, 0, false); err != nil {
		return nil, fmt.Errorf("set qos: %w", err)
	}

	list, err := subscribe(ch, queues)
	if err != nil {
		return nil, err
	}

	return &Consumer{ConsumerList: list}, nil
}

func subscribe(ch channelConsumer, queues []string) ([]EventConsumer, error) {
	result := make([]EventConsumer, 0, len(queues))

	for _, name := range queues {
		deliveries, err := ch.Consume(name, "", false, false, false, false, nil)
		if err != nil {
			return nil, fmt.Errorf("consume queue %s: %w", name, err)
		}

		result = append(result, EventConsumer{Queue: name, Deliveries: deliveries})
	}

	return result, nil
}
