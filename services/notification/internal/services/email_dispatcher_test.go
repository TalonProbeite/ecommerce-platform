package services

import (
	"context"
	"errors"
	"shop/notification/internal/infra/mailer"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func newDispatcherFixture() (*emailDispatcher, *fakeSender, *fakeSaver) {
	sender := &fakeSender{}
	saver := &fakeSaver{}

	return newEmailDispatcher(sender, saver), sender, saver
}

func testEmailMessage() *emailMessage {
	return &emailMessage{
		EventType: "order.paid",
		To:        "customer@example.com",
		Subject:   "Order paid",
		Body:      "<p>paid</p>",
		Payload:   map[string]string{"order_id": "order-1"},
	}
}

func TestEmailDispatcher_Dispatch_Success(t *testing.T) {
	t.Parallel()

	d, sender, saver := newDispatcherFixture()
	msg := testEmailMessage()

	err := d.dispatch(context.Background(), msg)

	require.NoError(t, err)
	assert.Equal(t, []sentMail{{To: msg.To, Subject: msg.Subject, Body: msg.Body}}, sender.calls)

	require.Len(t, saver.saved, 1)
	got := saver.saved[0]
	assert.Equal(t, msg.EventType, got.Type)
	assert.Equal(t, channelEmail, got.Channel)
	assert.Equal(t, msg.To, got.Recipient)
	assert.Equal(t, mailer.SendStatusSent, got.Status)
	assert.Equal(t, 1, got.Attempts)
	assert.Equal(t, msg.Payload, got.Payload)
	assert.Empty(t, got.Error)
	assert.Equal(t, time.UTC, got.CreatedAt.Location())
	assert.WithinDuration(t, time.Now(), got.CreatedAt, 5*time.Second)
}

func TestEmailDispatcher_Dispatch_Failures(t *testing.T) {
	t.Parallel()

	errSend := errors.New("smtp unavailable")
	errSave := errors.New("mongo unavailable")

	tests := []struct {
		name    string
		sendErr error
		saveErr error
	}{
		{name: "send fails", sendErr: errSend},
		{name: "save fails", saveErr: errSave},
		{name: "both fail", sendErr: errSend, saveErr: errSave},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			d, sender, saver := newDispatcherFixture()
			sender.err = tc.sendErr
			saver.err = tc.saveErr

			err := d.dispatch(context.Background(), testEmailMessage())

			require.Error(t, err)
			if tc.sendErr != nil {
				require.ErrorIs(t, err, tc.sendErr)
			}
			if tc.saveErr != nil {
				require.ErrorIs(t, err, tc.saveErr)
			}

			require.Len(t, saver.saved, 1)
			got := saver.saved[0]

			if tc.sendErr != nil {
				assert.Equal(t, mailer.SendStatusFailed, got.Status)
				assert.Equal(t, tc.sendErr.Error(), got.Error)
			} else {
				assert.Equal(t, mailer.SendStatusSent, got.Status)
				assert.Empty(t, got.Error)
			}
		})
	}
}

func TestEmailDispatcher_Dispatch_UsesAttemptFromContext(t *testing.T) {
	t.Parallel()

	d, _, saver := newDispatcherFixture()
	ctx := withAttempt(context.Background(), 3)

	require.NoError(t, d.dispatch(ctx, testEmailMessage()))

	require.Len(t, saver.saved, 1)
	assert.Equal(t, 3, saver.saved[0].Attempts)
}
