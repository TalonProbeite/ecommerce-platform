package app

import (
	"context"
	"shop/notification/internal/services"
	"testing"
)

type migrationRenderer struct{ services.MailRenderer }

func (migrationRenderer) RenderVerification(code string, _ int) (string, error) {
	return "verify:" + code, nil
}
func (migrationRenderer) RenderWelcome(name string) (string, error) { return "welcome:" + name, nil }

type migrationDelivery struct{ to, subject, body string }

func (d *migrationDelivery) SendHTML(to, subject, body string) error {
	d.to, d.subject, d.body = to, subject, body
	return nil
}

type migrationSaver struct{ count int }

func (s *migrationSaver) SaveEvent(context.Context, any) error { s.count++; return nil }

func TestUserEventRoutingDuringMigration(t *testing.T) {
	for _, tc := range []struct{ key, payload, subject, body string }{
		{"user.registered", `{"email":"user@example.com","code":"123456"}`, "Verify your email", "verify:123456"},
		{"user.email_verification_requested", `{"email":"user@example.com","code":"123456"}`, "Verify your email", "verify:123456"},
		{"user.email_verified", `{"email":"user@example.com","name":"Jane"}`, "Welcome aboard", "welcome:Jane"},
		{"user.welcome_email_requested", `{"email":"user@example.com","name":"Jane"}`, "Welcome aboard", "welcome:Jane"},
	} {
		t.Run(tc.key, func(t *testing.T) {
			sender, saver := &migrationDelivery{}, &migrationSaver{}
			user := services.NewUserEventHandler(migrationRenderer{}, sender, saver)
			handler := eventHandlers(user, &services.OrderEventHandler{})[tc.key]
			if handler == nil {
				t.Fatalf("missing migration handler for %s", tc.key)
			}
			if err := handler(context.Background(), []byte(tc.payload)); err != nil {
				t.Fatal(err)
			}
			if sender.to != "user@example.com" || sender.subject != tc.subject || sender.body != tc.body {
				t.Fatalf("unexpected delivery: %+v", sender)
			}
			if saver.count != 1 {
				t.Fatalf("saved %d events, want 1", saver.count)
			}
		})
	}
}
