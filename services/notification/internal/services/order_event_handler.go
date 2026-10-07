package services

import (
	"context"
	json "encoding/json/v2"
	"fmt"
	"shop/shared/events"
)

type OrderEventHandler struct {
	renderer   MailRenderer
	dispatcher *emailDispatcher
}

func NewOrderEventHandler(
	renderer MailRenderer,
	sender MailSender,
	evRepo EventSaver,
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
	var event events.OrderConfirmedEvent

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
		EventType: events.OrderConfirmedEventKey,
		To:        event.Email,
		Subject:   events.OrderConfirmedSubject,
		Body:      mailBody,
		Payload:   event,
	})
}

func (h *OrderEventHandler) HandleOrderPaid(
	ctx context.Context,
	body []byte,
) error {
	var event events.OrderPaidEvent

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
		EventType: events.OrderPaidEventKey,
		To:        event.Email,
		Subject:   events.OrderPaidSubject,
		Body:      mailBody,
		Payload:   event,
	})
}

func (h *OrderEventHandler) HandleOrderCancelled(
	ctx context.Context,
	body []byte,
) error {
	var event events.OrderCancelledPayload

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
		EventType: events.OrderCancelledEventKey,
		To:        event.Email,
		Subject:   events.OrderCancelledSubject,
		Body:      mailBody,
		Payload:   event,
	})
}
