package services

import (
	"context"
	json "encoding/json/v2"
	"fmt"
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mailer"
	"shop/notification/internal/infra/repository"
	"shop/notification/internal/infra/template"
)

type OrderEventHandler struct {
	renderer   *template.MessageBuilder
	dispatcher *emailDispatcher
}

func NewOrderEventHandler(
	renderer *template.MessageBuilder,
	sender *mailer.Sender,
	evRepo *repository.EventRepo,
) *OrderEventHandler {
	return &OrderEventHandler{
		renderer:   renderer,
		dispatcher: newEmailDispatcher(sender, evRepo),
	}
}

func (h *OrderEventHandler) HandleOrderConfirmed(
	ctx context.Context,
	body []byte,
) error {
	var event domain.OrderConfirmedEvent

	if err := json.Unmarshal(body, &event); err != nil {
		return fmt.Errorf(
			"unmarshal order.confirmed event: %w: %w",
			err,
			ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderConfirmed(event.OrderID)
	if err != nil {
		return fmt.Errorf("error rendering confirmed template: %w", err)
	}

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: domain.OrderConfirmedEventKey,
		To:        event.Email,
		Subject:   domain.OrderConfirmedSubject,
		Body:      mailBody,
		Payload:   event,
	})
}

func (h *OrderEventHandler) HandleOrderPaid(
	ctx context.Context,
	body []byte,
) error {
	var event domain.OrderPaidEvent

	if err := json.Unmarshal(body, &event); err != nil {
		return fmt.Errorf(
			"unmarshal order.paid event: %w: %w",
			err,
			ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderPaid(event.OrderID, event.Amount)
	if err != nil {
		return fmt.Errorf("error rendering paid template: %w", err)
	}

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: domain.OrderPaidEventKey,
		To:        event.Email,
		Subject:   domain.OrderPaidSubject,
		Body:      mailBody,
		Payload:   event,
	})
}

func (h *OrderEventHandler) HandleOrderCancelled(
	ctx context.Context,
	body []byte,
) error {
	var event domain.OrderCancelledPayload

	if err := json.Unmarshal(body, &event); err != nil {
		return fmt.Errorf(
			"unmarshal order.cancelled event: %w: %w",
			err,
			ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderCancelled(event.OrderID, event.Reason)
	if err != nil {
		return fmt.Errorf("error rendering canceled template: %w", err)
	}

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: domain.OrderCancelledEventKey,
		To:        event.Email,
		Subject:   domain.OrderCancelledSubject,
		Body:      mailBody,
		Payload:   event,
	})
}
