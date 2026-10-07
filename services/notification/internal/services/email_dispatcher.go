package services

import (
	"context"
	"errors"
	"fmt"
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mailer"
	"time"
)

const (
	channelEmail = "email"

	saveAttempts       = 3
	defaultSaveBackoff = 200 * time.Millisecond
)

type emailMessage struct {
	Payload   any
	EventType string
	To        string
	Subject   string
	Body      string
}

type emailDispatcher struct {
	sender         MailSender
	evRepo         EventSaver
	saveRetryDelay time.Duration
}

func newEmailDispatcher(sender MailSender, evRepo EventSaver) *emailDispatcher {
	return &emailDispatcher{
		sender:         sender,
		evRepo:         evRepo,
		saveRetryDelay: defaultSaveBackoff,
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

	saveErr := d.saveWithRetry(ctx, &eventModel)
	if saveErr != nil {
		saveErr = fmt.Errorf("mongo audit log error: %w", saveErr)
	}

	if sendErr == nil && saveErr != nil {
		return fmt.Errorf("mail already sent, audit log lost: %w: %w", saveErr, ErrUnrecoverable)
	}

	return errors.Join(sendErr, saveErr)
}

func (d *emailDispatcher) saveWithRetry(ctx context.Context, event *domain.EventLog) error {
	var err error

	for attempt := 1; attempt <= saveAttempts; attempt++ {
		if err = d.evRepo.SaveEvent(ctx, *event); err == nil {
			return nil
		}

		if attempt == saveAttempts {
			break
		}

		select {
		case <-ctx.Done():
			return errors.Join(err, ctx.Err())
		case <-time.After(d.saveRetryDelay):
		}
	}

	return err
}
