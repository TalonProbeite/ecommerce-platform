package services

import (
	"context"
	json "encoding/json/v2"
	"errors"
	"fmt"
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mailer"
	"shop/notification/internal/infra/repository"
	"shop/notification/internal/infra/template"
	"time"
)

type OrderEventHandler struct {
	renderer *template.MessageBuilder
	sender   *mailer.Sender
	evRepo   *repository.EventRepo
}

func NewOrderEventHandler(
	renderer *template.MessageBuilder,
	sender *mailer.Sender,
	evRepo *repository.EventRepo,
) *OrderEventHandler {
	return &OrderEventHandler{
		renderer: renderer,
		sender:   sender,
		evRepo:   evRepo,
	}
}

func (h *OrderEventHandler) HandleOrderConfirmed(
	ctx context.Context,
	body []byte,
) error {
	var event domain.OrderConfirmedEvent

	if err := json.Unmarshal(body, &event); err != nil {
		return fmt.Errorf(
			"unmarshal user.registered event: %w: %w",
			err,
			ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderConfirmed(event.OrderID)
	if err != nil {
		return fmt.Errorf("error rendering template: %w", err)
	}
	return h.sendAndLog(
		ctx,
		domain.UserEmailVerifiedEventKey,
		event.Email,
		domain.UserEmailVerifiedSubject,
		mailBody,
		event,
	)
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
		return fmt.Errorf("error rendering template: %w", err)
	}

	return h.sendAndLog(
		ctx,
		domain.OrderPaidEventKey,
		event.Email,
		domain.OrderPaidSubject,
		mailBody,
		event,
	)
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
		return fmt.Errorf("error rendering template: %w", err)
	}

	return h.sendAndLog(
		ctx,
		domain.OrderCancelledEventKey,
		event.Email,
		domain.OrderCancelledSubject,
		mailBody,
		event,
	)
}

func (h *OrderEventHandler) sendAndLog(
	ctx context.Context,
	eventType string,
	to string,
	subject string,
	mailBody string,
	payload any,
) error {
	eventModel := domain.EventLog{
		Type:      eventType,
		Status:    mailer.SendStatusSent,
		Payload:   payload,
		Error:     "",
		CreatedAt: time.Now().UTC(),
	}

	sendErr := h.sender.SendHTML(to, subject, mailBody)
	if sendErr != nil {
		eventModel.Status = mailer.SendStatusFailed
		eventModel.Error = sendErr.Error()
		sendErr = fmt.Errorf("email send error: %w", sendErr)
	}

	saveErr := h.evRepo.SaveEvent(ctx, eventModel)
	if saveErr != nil {
		saveErr = fmt.Errorf("mongo audit log error: %w", saveErr)
	}

	return errors.Join(sendErr, saveErr)
}
