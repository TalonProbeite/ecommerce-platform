package services

import (
	"context"
	json "encoding/json/v2"
	"fmt"
	"shop/shared/events"
	"shop/shared/rabbitmq"
)

type OrderEventHandler struct {
	renderer   MailRenderer
	dispatcher *emailDispatcher
	contacts   ContactStore
}

func NewOrderEventHandler(
	renderer MailRenderer,
	sender MailSender,
	evRepo EventSaver,
	contacts ...ContactStore,
) *OrderEventHandler {
	handler := &OrderEventHandler{
		renderer:   renderer,
		dispatcher: newEmailDispatcher(sender, evRepo),
	}

	if len(contacts) > 0 {
		handler.contacts = contacts[0]
	}

	return handler
}

func (h *OrderEventHandler) recipient(ctx context.Context, email, customerID string) (string, error) {
	if email != "" {
		return email, nil
	}

	if customerID == "" || h.contacts == nil {
		return "", fmt.Errorf("order event has no recipient email: %w", rabbitmq.ErrUnrecoverable)
	}

	resolved, err := h.contacts.GetEmail(ctx, customerID)
	if err != nil {
		return "", fmt.Errorf("resolve customer email: %w", err)
	}

	return resolved, nil
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
			rabbitmq.ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderConfirmed(event.OrderID)
	if err != nil {
		return fmt.Errorf("error rendering confirmed template: %w", err)
	}

	recipient, err := h.recipient(ctx, event.Email, event.CustomerID)
	if err != nil {
		return err
	}

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: events.OrderConfirmedEventKey,
		To:        recipient,
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
			rabbitmq.ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderPaid(event.OrderID, event.Amount)
	if err != nil {
		return fmt.Errorf("error rendering paid template: %w", err)
	}

	recipient, err := h.recipient(ctx, event.Email, event.CustomerID)
	if err != nil {
		return err
	}

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: events.OrderPaidEventKey,
		To:        recipient,
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
			rabbitmq.ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderCancelled(event.OrderID, event.Reason)
	if err != nil {
		return fmt.Errorf("error rendering canceled template: %w", err)
	}

	recipient, err := h.recipient(ctx, event.Email, event.CustomerID)
	if err != nil {
		return err
	}

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: events.OrderCancelledEventKey,
		To:        recipient,
		Subject:   events.OrderCancelledSubject,
		Body:      mailBody,
		Payload:   event,
	})
}
