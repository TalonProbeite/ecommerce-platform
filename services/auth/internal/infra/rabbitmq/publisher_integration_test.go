//go:build integration

package rabbitmq

import (
	"os"
	"shop/shared/events"
	"testing"
	"time"
)

// Use a dedicated broker: the test declares a temporary queue and the user exchange.
func TestPublisherDeliversUserEvent(t *testing.T) {
	url := os.Getenv("TEST_RABBITMQ_URL")
	if url == "" {
		t.Skip("TEST_RABBITMQ_URL is required")
	}
	client, err := NewRabbitClient(url)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = client.Close() })
	publisher, err := NewEventPublisher(client)
	if err != nil {
		t.Fatal(err)
	}
	queue, err := client.Chan.QueueDeclare("", false, true, true, false, nil)
	if err != nil {
		t.Fatal(err)
	}
	if err := client.Chan.QueueBind(queue.Name, events.EmailVerificationRequestedEventKey, events.ExchangeUser, false, nil); err != nil {
		t.Fatal(err)
	}
	deliveries, err := client.Chan.Consume(queue.Name, "", true, false, false, false, nil)
	if err != nil {
		t.Fatal(err)
	}
	body := []byte(`{"email":"test@example.com","code":"123456"}`)
	if err := publisher.PublishEvent(events.EmailVerificationRequestedEventKey, body); err != nil {
		t.Fatal(err)
	}
	select {
	case d := <-deliveries:
		if string(d.Body) != string(body) || d.RoutingKey != events.EmailVerificationRequestedEventKey {
			t.Fatalf("unexpected delivery: %s (%s)", d.Body, d.RoutingKey)
		}
	case <-time.After(5 * time.Second):
		t.Fatal("user event did not reach the notification binding")
	}
}
