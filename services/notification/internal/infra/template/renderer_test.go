package template

import (
	"strings"
	"testing"
)

func newTestBuilder(t *testing.T) *MessageBuilder {
	t.Helper()

	t.Chdir("../../..")

	builder, err := NewMessageBuilder("local")
	if err != nil {
		t.Fatalf("NewMessageBuilder returned an error: %v", err)
	}

	return builder
}

func TestRenderWelcome(t *testing.T) {

	body, err := newTestBuilder(t).RenderWelcome("Jane Doe")
	if err != nil {
		t.Fatalf("RenderWelcome returned an error: %v", err)
	}

	if !strings.Contains(body, "Jane Doe") {
		t.Fatal("welcome template does not contain the customer name")
	}

	if !strings.Contains(body, "http://localhost/catalog") {
		t.Fatal("welcome template does not contain the local dashboard URL")
	}
}

func TestRenderVerification(t *testing.T) {

	body, err := newTestBuilder(t).RenderVerification("ABCDEFGHIJ", 15)
	if err != nil {
		t.Fatalf("RenderVerification returned an error: %v", err)
	}

	if !strings.Contains(body, "ABCDEFGHIJ") {
		t.Fatal("verification template does not contain the code")
	}

	if !strings.Contains(body, "15") {
		t.Fatal("verification template does not contain the expiration")
	}
}

func TestRenderOrderTemplates(t *testing.T) {

	builder := newTestBuilder(t)

	confirmed, err := builder.RenderConfirmed("order-123")
	if err != nil {
		t.Fatalf("RenderConfirmed returned an error: %v", err)
	}

	if !strings.Contains(confirmed, "order-123") {
		t.Fatal("confirmed template does not contain the order id")
	}

	paid, err := builder.RenderPaid("order-456", 19.99)
	if err != nil {
		t.Fatalf("RenderPaid returned an error: %v", err)
	}

	if !strings.Contains(paid, "order-456") {
		t.Fatal("paid template does not contain the order id")
	}

	cancelled, err := builder.RenderCancelled("order-789", "customer request")
	if err != nil {
		t.Fatalf("RenderCancelled returned an error: %v", err)
	}

	if !strings.Contains(cancelled, "order-789") {
		t.Fatal("cancelled template does not contain the order id")
	}

	if !strings.Contains(cancelled, "customer request") {
		t.Fatal("cancelled template does not contain the cancellation reason")
	}
}

func TestNewMessageBuilderProductionWelcomeURL(t *testing.T) {

	t.Chdir("../../..")

	builder, err := NewMessageBuilder("production")
	if err != nil {
		t.Fatalf("NewMessageBuilder returned an error: %v", err)
	}

	if builder.welcomeURL != "https://yourdomain.com/dashboard" {
		t.Fatalf("welcome URL = %q", builder.welcomeURL)
	}
}
