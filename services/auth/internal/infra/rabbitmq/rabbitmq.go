// Package rabbitmq provides RabbitMQ client connection management and message publishing.
package rabbitmq

import (
	"errors"
	"fmt"

	amqp "github.com/rabbitmq/amqp091-go"
)

// RabbitClient wraps an AMQP connection and channel.
type RabbitClient struct {
	Conn *amqp.Connection
	Chan *amqp.Channel
}

// NewRabbitClient connects to RabbitMQ and opens a channel.
func NewRabbitClient(url string) (*RabbitClient, error) {
	conn, err := amqp.Dial(url)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to rabbitmq: %w", err)
	}

	ch, err := conn.Channel()
	if err != nil {
		if closeErr := conn.Close(); closeErr != nil {
			return nil, fmt.Errorf("failed to open channel (%w) and failed to close connection (%w)", err, closeErr)
		}
		return nil, fmt.Errorf("failed to open rabbitmq channel: %w", err)
	}

	return &RabbitClient{
		Conn: conn,
		Chan: ch,
	}, nil
}

// Close closes the AMQP channel and connection.
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
