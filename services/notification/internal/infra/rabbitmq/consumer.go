package rabbitmq

import (
	"fmt"
	"shop/notification/internal/domain"

	amqp "github.com/rabbitmq/amqp091-go"
)

const (
	ExchangeUser  = "user.events"
	ExchangeOrder = "order.events"
	ExchangeDLX   = "dlx.exchange"

	exchangeKindTopic  = "topic"
	exchangeKindFanout = "fanout"

	QueueUserNotifications = "user.notifications.q"
	QueueOrderProcessing   = "order.processing.q"
	QueueFailedMessages    = "failed.messages"

	argDeliveryLimit = "x-delivery-limit"
	argDeadLetterExc = "x-dead-letter-exchange"

	deliveryLimit = 5
	prefetchCount = 10
)

type exchangeSpec struct {
	Name string
	Kind string
}

type queueSpec struct {
	Name     string
	Exchange string
	Keys     []string
}

var exchanges = []exchangeSpec{
	{Name: ExchangeUser, Kind: exchangeKindTopic},
	{Name: ExchangeOrder, Kind: exchangeKindTopic},
	{Name: ExchangeDLX, Kind: exchangeKindFanout},
}

var queues = []queueSpec{
	{
		Name:     QueueUserNotifications,
		Exchange: ExchangeUser,
		Keys: []string{
			domain.UserRegisteredEventKey,
			domain.UserEmailVerifiedEventKey,
		},
	},
	{
		Name:     QueueOrderProcessing,
		Exchange: ExchangeOrder,
		Keys: []string{
			domain.OrderPaidEventKey,
			domain.OrderConfirmedEventKey,
			domain.OrderCancelledEventKey,
		},
	},
}

type EventConsumer struct {
	Deliveries <-chan amqp.Delivery
	Queue      string
}

type Consumer struct {
	client       *RabbitClient
	ConsumerList []EventConsumer
}

func NewConsumer(client *RabbitClient) (*Consumer, error) {
	c := &Consumer{client: client}

	if err := c.declareTopology(); err != nil {
		return nil, err
	}

	if err := c.client.Chan.Qos(prefetchCount, 0, false); err != nil {
		return nil, fmt.Errorf("set qos: %w", err)
	}

	list, err := c.subscribe()
	if err != nil {
		return nil, err
	}

	c.ConsumerList = list

	return c, nil
}

func (c *Consumer) subscribe() ([]EventConsumer, error) {
	result := make([]EventConsumer, 0, len(queues))

	for _, q := range queues {
		deliveries, err := c.client.Chan.Consume(q.Name, "", false, false, false, false, nil)
		if err != nil {
			return nil, fmt.Errorf("consume queue %s: %w", q.Name, err)
		}

		result = append(result, EventConsumer{Queue: q.Name, Deliveries: deliveries})
	}

	return result, nil
}

func (c *Consumer) declareTopology() error {
	if err := c.declareExchanges(); err != nil {
		return err
	}

	if err := c.declareDLQ(); err != nil {
		return err
	}

	return c.declareQueues()
}

func (c *Consumer) declareExchanges() error {
	for _, e := range exchanges {
		err := c.client.Chan.ExchangeDeclare(e.Name, e.Kind, true, false, false, false, nil)
		if err != nil {
			return fmt.Errorf("declare exchange %s: %w", e.Name, err)
		}
	}

	return nil
}

func (c *Consumer) declareDLQ() error {
	q, err := c.client.Chan.QueueDeclare(QueueFailedMessages, true, false, false, false, nil)
	if err != nil {
		return fmt.Errorf("declare queue %s: %w", QueueFailedMessages, err)
	}

	if err := c.client.Chan.QueueBind(q.Name, "", ExchangeDLX, false, nil); err != nil {
		return fmt.Errorf("bind queue %s to %s: %w", q.Name, ExchangeDLX, err)
	}

	return nil
}

func (c *Consumer) declareQueues() error {
	for _, spec := range queues {
		if err := c.declareQueue(spec); err != nil {
			return err
		}
	}

	return nil
}

func (c *Consumer) declareQueue(spec queueSpec) error {
	q, err := c.client.Chan.QueueDeclare(spec.Name, true, false, false, false, workQueueArgs())
	if err != nil {
		return fmt.Errorf("declare queue %s: %w", spec.Name, err)
	}

	for _, key := range spec.Keys {
		if err := c.client.Chan.QueueBind(q.Name, key, spec.Exchange, false, nil); err != nil {
			return fmt.Errorf("bind queue %s to %s with key %s: %w", q.Name, spec.Exchange, key, err)
		}
	}

	return nil
}

func workQueueArgs() amqp.Table {
	return amqp.Table{
		amqp.QueueTypeArg: amqp.QueueTypeQuorum,
		argDeliveryLimit:  deliveryLimit,
		argDeadLetterExc:  ExchangeDLX,
	}
}
