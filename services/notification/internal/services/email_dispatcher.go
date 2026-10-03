package services

import (
	"context"
	"errors"
	"fmt"
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mailer"
	"time"
)

const channelEmail = "email"

type emailMessage struct {
	Payload   any
	EventType string
	To        string
	Subject   string
	Body      string
}

type emailDispatcher struct {
	sender MailSender
	evRepo EventSaver
}

func newEmailDispatcher(sender MailSender, evRepo EventSaver) *emailDispatcher {
	return &emailDispatcher{
		sender: sender,
		evRepo: evRepo,
	}
}

func (d *emailDispatcher) dispatch(ctx context.Context, msg *emailMessage) error {
	eventModel := domain.EventLog{
		Type:      msg.EventType,
		Channel:   channelEmail,
		Recipient: msg.To,
		Status:    mailer.SendStatusSent,
		Attempts:  attemptFromContext(ctx),
		Payload:   msg.Payload,
		Error:     "",
		CreatedAt: time.Now().UTC(),
	}

	sendErr := d.sender.SendHTML(msg.To, msg.Subject, msg.Body)
	if sendErr != nil {
		eventModel.Status = mailer.SendStatusFailed
		eventModel.Error = sendErr.Error()
		sendErr = fmt.Errorf("email send error: %w", sendErr)
	}

	saveErr := d.evRepo.SaveEvent(ctx, eventModel)
	if saveErr != nil {
		saveErr = fmt.Errorf("mongo audit log error: %w", saveErr)
	}

	return errors.Join(sendErr, saveErr)
}
