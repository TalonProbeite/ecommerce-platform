package mailer_test

import (
	"testing"

	"shop/notification/internal/config"
	"shop/notification/internal/infra/mailer"
)

func TestNewSenderRejectsAnInvalidPort(t *testing.T) {
	t.Parallel()

	_, err := mailer.NewSender(&config.SMTPConfig{
		SMTPHost: "localhost",
		SMTPPort: "not-a-port",
		SMTPUser: "user",
		SMTPPass: "secret",
		From:     "noreply@example.com",
	})
	if err == nil {
		t.Fatal("NewSender() = nil error, want a port parsing error")
	}
}

func TestSendStatusValues(t *testing.T) {
	t.Parallel()

	if mailer.SendStatusSent != "sent" {
		t.Fatalf("SendStatusSent = %q", mailer.SendStatusSent)
	}

	if mailer.SendStatusFailed != "failed" {
		t.Fatalf("SendStatusFailed = %q", mailer.SendStatusFailed)
	}
}
