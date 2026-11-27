package rabbitmq

import (
	"errors"
	"fmt"
	"shop/shared/events"

	sharedrabbit "shop/shared/rabbitmq"

	amqp "github.com/rabbitmq/amqp091-go"
)

const (
	QueueUserNotifications = "user.notifications.q"
	QueueOrderProcessing   = "order.processing.q"
	QueueFailedMessages    = "failed.messages"

	argDeliveryLimit = "x-delivery-limit"
	argDeadLetterExc = "x-dead-letter-exchange"

	deliveryLimit = 5
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
	{Name: events.ExchangeUser, Kind: events.ExchangeKindTopic},
	{Name: events.ExchangeOrder, Kind: events.ExchangeKindTopic},
	{Name: events.ExchangeDLX, Kind: events.ExchangeKindFanout},
}

var queues = []queueSpec{
	{
		Name:     QueueUserNotifications,
		Exchange: events.ExchangeUser,
		Keys: []string{
			events.UserRegisteredEventKey,
			events.UserEmailVerifiedEventKey,
			events.EmailVerificationRequestedEventKey,
			events.WelcomeEmailRequestedEventKey,
			events.EmailChangedEventKey,
		},
	},
	{
		Name:     QueueOrderProcessing,
		Exchange: events.ExchangeOrder,
		Keys: []string{
			events.OrderPaidEventKey,
			events.OrderConfirmedEventKey,
			events.OrderCancelledEventKey,
		},
	},
}

type topologyDeclarer interface {
	ExchangeDeclare(name, kind string, durable, autoDelete, internal, noWait bool, args amqp.Table) error
	QueueDeclare(name string, durable, autoDelete, exclusive, noWait bool, args amqp.Table) (amqp.Queue, error)
	QueueBind(queue, key, exchange string, noWait bool, args amqp.Table) error
}

// InitTopology declares the RabbitMQ exchanges and queues required by the notification service.
func InitTopology(client *sharedrabbit.RabbitClient) error {
	if client == nil || client.Chan == nil {
		return errors.New("rabbitmq client or channel is nil")
	}

	return initTopology(client.Chan)
}

func initTopology(ch topologyDeclarer) error {
	if err := declareExchanges(ch); err != nil {
		return err
	}

	if err := declareDLQ(ch); err != nil {
		return err
	}

	return declareQueues(ch)
}

// QueueNames returns the queues the notification consumer must subscribe to.
func QueueNames() []string {
	names := make([]string, 0, len(queues))

	for _, spec := range queues {
		names = append(names, spec.Name)
	}

	return names
}

func declareExchanges(ch topologyDeclarer) error {
	for _, e := range exchanges {
		if err := ch.ExchangeDeclare(e.Name, e.Kind, true, false, false, false, nil); err != nil {
			return fmt.Errorf("declare exchange %s: %w", e.Name, err)
		}
	}

	return nil
}

func declareDLQ(ch topologyDeclarer) error {
	q, err := ch.QueueDeclare(QueueFailedMessages, true, false, false, false, nil)
	if err != nil {
		return fmt.Errorf("declare queue %s: %w", QueueFailedMessages, err)
	}

	if err := ch.QueueBind(q.Name, "", events.ExchangeDLX, false, nil); err != nil {
		return fmt.Errorf("bind queue %s to %s: %w", q.Name, events.ExchangeDLX, err)
	}

	return nil
}

func declareQueues(ch topologyDeclarer) error {
	for _, spec := range queues {
		if err := declareQueue(ch, spec); err != nil {
			return err
		}
	}

	return nil
}

func declareQueue(ch topologyDeclarer, spec queueSpec) error {
	q, err := ch.QueueDeclare(spec.Name, true, false, false, false, workQueueArgs())
	if err != nil {
		return fmt.Errorf("declare queue %s: %w", spec.Name, err)
	}

	for _, key := range spec.Keys {
		if err := ch.QueueBind(q.Name, key, spec.Exchange, false, nil); err != nil {
			return fmt.Errorf("bind queue %s to %s with key %s: %w", q.Name, spec.Exchange, key, err)
		}
	}

	return nil
}

func workQueueArgs() amqp.Table {
	return amqp.Table{
		amqp.QueueTypeArg: amqp.QueueTypeQuorum,
		argDeliveryLimit:  deliveryLimit,
		argDeadLetterExc:  events.ExchangeDLX,
	}
}
