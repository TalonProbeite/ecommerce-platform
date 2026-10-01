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

const verificationCodeTTLMinutes = 15

type UserEventHandler struct {
	renderer   *template.MessageBuilder
	dispatcher *emailDispatcher
}

func NewUserEventHandler(
	renderer *template.MessageBuilder,
	sender *mailer.Sender,
	evRepo *repository.EventRepo,
) *UserEventHandler {
	return &UserEventHandler{
		renderer:   renderer,
		dispatcher: newEmailDispatcher(sender, evRepo),
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

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: domain.UserRegisteredEventKey,
		To:        event.Email,
		Subject:   domain.UserRegisteredSubject,
		Body:      mailBody,
		Payload:   event,
	})
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

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: domain.UserEmailVerifiedEventKey,
		To:        event.Email,
		Subject:   domain.UserEmailVerifiedSubject,
		Body:      mailBody,
		Payload:   event,
	})
}
