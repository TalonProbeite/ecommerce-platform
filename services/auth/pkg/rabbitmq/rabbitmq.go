package rabbitmq

import (
	"errors"
	"fmt"

	amqp "github.com/rabbitmq/amqp091-go"
)

type RabbitClient struct {
	Conn *amqp.Connection
	Chan *amqp.Channel
}

func NewRabbitClient(url string) (*RabbitClient, error) {
	conn, err := amqp.Dial(url)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to rabbitmq: %w", err)
	}

	ch, err := conn.Channel()
	if err != nil {
		_ = conn.Close()
		return nil, fmt.Errorf("failed to open rabbitmq channel: %w", err)
	}

	return &RabbitClient{
		Conn: conn,
		Chan: ch,
	}, nil
}

func (r *RabbitClient) Close() error {
	if r == nil {
		return nil
	}
	var err error
	if r.Chan != nil {
		err = errors.Join(err, r.Chan.Close())
	}
	if r.Conn != nil {
		err = errors.Join(err, r.Conn.Close())
	}
	return err
}
