package services

import (
	"context"
	json "encoding/json/v2"
	"fmt"
	"shop/shared/events"
	"shop/shared/rabbitmq"
)

const verificationCodeTTLMinutes = 15

type UserEventHandler struct {
	renderer   MailRenderer
	dispatcher *emailDispatcher
	contacts   ContactStore
}

func NewUserEventHandler(
	renderer MailRenderer,
	sender MailSender,
	evRepo EventSaver,
	contacts ...ContactStore,
) *UserEventHandler {
	handler := &UserEventHandler{
		renderer:   renderer,
		dispatcher: newEmailDispatcher(sender, evRepo),
	}

	if len(contacts) > 0 {
		handler.contacts = contacts[0]
	}

	return handler
}

func (h *UserEventHandler) saveContact(ctx context.Context, userID, email string) error {
	if h.contacts == nil || userID == "" || email == "" {
		return nil
	}

	if err := h.contacts.SaveContact(ctx, userID, email); err != nil {
		return fmt.Errorf("save user contact: %w", err)
	}

	return nil
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
			rabbitmq.ErrUnrecoverable,
		)
	}

	if err := h.saveContact(ctx, event.UserID, event.Email); err != nil {
		return err
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

func (h *UserEventHandler) HandleEmailChanged(
	ctx context.Context,
	body []byte,
) error {
	var event events.EmailChangedEvent

	if err := json.Unmarshal(body, &event); err != nil {
		return fmt.Errorf(
			"unmarshal user.email_changed event: %w: %w",
			err,
			rabbitmq.ErrUnrecoverable,
		)
	}

	if event.UserID == "" || event.Email == "" {
		return fmt.Errorf(
			"user.email_changed requires user_id and email: %w",
			rabbitmq.ErrUnrecoverable,
		)
	}

	return h.saveContact(ctx, event.UserID, event.Email)
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
			rabbitmq.ErrUnrecoverable,
		)
	}

	if err := h.saveContact(ctx, event.UserID, event.Email); err != nil {
		return err
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
