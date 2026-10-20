package rabbitmq

import "testing"

func TestUserQueueBindsBothEventGenerations(t *testing.T) {
	keys := map[string]bool{}
	for _, spec := range queues {
		if spec.Name == QueueUserNotifications {
			for _, key := range spec.Keys {
				keys[key] = true
			}
		}
	}
	for _, key := range []string{"user.registered", "user.email_verified", "user.email_verification_requested", "user.welcome_email_requested"} {
		if !keys[key] {
			t.Errorf("user queue missing binding for %s", key)
		}
	}
}
