package services

import (
	"context"
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mailer"
	"shop/notification/internal/infra/repository"
	"shop/notification/internal/infra/template"
)

type MailSender interface {
	SendHTML(to, subject, body string) error
}

type EventSaver interface {
	SaveEvent(ctx context.Context, event any) error
}

type EventReader interface {
	GetByID(ctx context.Context, id string) (domain.EventLog, error)
	GetHistory(ctx context.Context, filters *domain.HistoryFilters) ([]domain.EventLog, error)
}

type MailRenderer interface {
	RenderVerification(code string, ttlMinutes int) (string, error)
	RenderWelcome(name string) (string, error)
	RenderConfirmed(orderID string) (string, error)
	RenderPaid(orderID string, amount float64) (string, error)
	RenderCancelled(orderID, reason string) (string, error)
}

var (
	_ MailSender   = (*mailer.Sender)(nil)
	_ EventSaver   = (*repository.EventRepo)(nil)
	_ EventReader  = (*repository.EventRepo)(nil)
	_ MailRenderer = (*template.MessageBuilder)(nil)
)
