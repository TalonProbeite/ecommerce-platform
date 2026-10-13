package application

import (
	"encoding/json/v2"
	"fmt"
	"shop/shared/events"
)

// publishWelcomeEmail keeps password and OAuth registration on the same
// notification contract. The notification service owns rendering and delivery.
func (as *AuthService) publishWelcomeEmail(email, name string) error {
	payload, err := json.Marshal(events.WelcomeEmailRequestedEvent{Email: email, Name: name})
	if err != nil {
		return fmt.Errorf("error while creating json struct for event: %w", err)
	}
	if err := as.publisher.PublishEvent(events.WelcomeEmailRequestedEventKey, payload); err != nil {
		return fmt.Errorf("error while publishing event: %w", err)
	}
	return nil
}
