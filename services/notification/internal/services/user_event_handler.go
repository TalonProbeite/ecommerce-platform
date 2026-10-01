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

const verificationCodeTTLMinutes = 15

type UserEventHandler struct {
	renderer *template.MessageBuilder
	sender   *mailer.Sender
	evRepo   *repository.EventRepo
}

func NewUserEventHandler(
	renderer *template.MessageBuilder,
	sender *mailer.Sender,
	evRepo *repository.EventRepo,
) *UserEventHandler {
	return &UserEventHandler{
		renderer: renderer,
		sender:   sender,
		evRepo:   evRepo,
	}
}

func (h *UserEventHandler) HandleUserRegistered(
	ctx context.Context,
	body []byte,
) error {
	var event domain.UserRegisteredEvent

	if err := json.Unmarshal(body, &event); err != nil {
		return fmt.Errorf(
			"unmarshal user.registered event: %w: %w",
			err,
			ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderVerification(event.Code, verificationCodeTTLMinutes)
	if err != nil {
		return fmt.Errorf("error rendering verification template: %w", err)
	}

	return h.sendAndLog(
		ctx,
		domain.UserRegisteredEventKey,
		event.Email,
		domain.UserRegisteredSubject,
		mailBody,
		event,
	)
}

func (h *UserEventHandler) HandleEmailVerified(
	ctx context.Context,
	body []byte,
) error {
	var event domain.UserEmailVerifiedEvent

	if err := json.Unmarshal(body, &event); err != nil {
		return fmt.Errorf(
			"unmarshal user.email_verified event: %w: %w",
			err,
			ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderWelcome(event.Name)
	if err != nil {
		return fmt.Errorf("error rendering welcome template: %w", err)
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

func (h *UserEventHandler) sendAndLog(
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
