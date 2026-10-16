package services

import (
	"context"
	json "encoding/json/v2"
	"fmt"
	"shop/shared/events"
)

const verificationCodeTTLMinutes = 15

type UserEventHandler struct {
	renderer   MailRenderer
	dispatcher *emailDispatcher
}

func NewUserEventHandler(
	renderer MailRenderer,
	sender MailSender,
	evRepo EventSaver,
) *UserEventHandler {
	return &UserEventHandler{
		renderer:   renderer,
		dispatcher: newEmailDispatcher(sender, evRepo),
	}
}

func (h *UserEventHandler) HandleEmailVerificationRequested(
	ctx context.Context,
	body []byte,
) error {
	var event events.EmailVerificationRequestedEvent

	if err := json.Unmarshal(body, &event); err != nil {
		return fmt.Errorf(
			"unmarshal user.email_verification_requested event: %w: %w",
			err,
			ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderVerification(event.Code, verificationCodeTTLMinutes)
	if err != nil {
		return fmt.Errorf("error rendering verification template: %w", err)
	}

	event.Code = ""

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: events.EmailVerificationRequestedEventKey,
		To:        event.Email,
		Subject:   events.EmailVerificationSubject,
		Body:      mailBody,
		Payload:   event,
	})
}

func (h *UserEventHandler) HandleWelcomeEmailRequested(
	ctx context.Context,
	body []byte,
) error {
	var event events.WelcomeEmailRequestedEvent

	if err := json.Unmarshal(body, &event); err != nil {
		return fmt.Errorf(
			"unmarshal user.welcome_email_requested event: %w: %w",
			err,
			ErrUnrecoverable,
		)
	}

	mailBody, err := h.renderer.RenderWelcome(event.Name)
	if err != nil {
		return fmt.Errorf("error rendering welcome template: %w", err)
	}

	return h.dispatcher.dispatch(ctx, &emailMessage{
		EventType: events.WelcomeEmailRequestedEventKey,
		To:        event.Email,
		Subject:   events.WelcomeEmailSubject,
		Body:      mailBody,
		Payload:   event,
	})
}
