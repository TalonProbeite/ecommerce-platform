package template

import (
	"bytes"
	"fmt"
	"html/template"
)

type MessageBuilder struct {
	templates  *template.Template
	welcomeURL string
}

func NewMessageBuilder(env string) (*MessageBuilder, error) {
	var welcomeURL string
	var templatesPath string

	switch env {
	case "prod", "production":
		welcomeURL = "https://yourdomain.com/dashboard"
		templatesPath = "templates/*.html"
	default:
		welcomeURL = "http://localhost/catalog"
		templatesPath = "templates/*.html"
	}

	tmpl, err := template.ParseGlob(templatesPath)
	if err != nil {
		return nil, fmt.Errorf("failed to parse email templates: %w", err)
	}

	return &MessageBuilder{
		welcomeURL: welcomeURL,
		templates:  tmpl,
	}, nil
}

func (m *MessageBuilder) RenderWelcome(name string) (string, error) {
	data := struct {
		Name         string
		DashboardURL string
	}{
		Name:         name,
		DashboardURL: m.welcomeURL,
	}

	var buf bytes.Buffer
	if err := m.templates.ExecuteTemplate(&buf, "wellcome.html", data); err != nil {
		return "", fmt.Errorf("failed to render welcome template: %w", err)
	}

	return buf.String(), nil
}

func (m *MessageBuilder) RenderVerification(code string, expiresInMinutes int) (string, error) {
	data := struct {
		Code      string
		ExpiresIn int
	}{
		Code:      code,
		ExpiresIn: expiresInMinutes,
	}

	var buf bytes.Buffer
	if err := m.templates.ExecuteTemplate(&buf, "verification.html", data); err != nil {
		return "", fmt.Errorf("failed to render verification template: %w", err)
	}

	return buf.String(), nil
}
