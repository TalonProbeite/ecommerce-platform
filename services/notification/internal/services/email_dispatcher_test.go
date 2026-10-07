package services

import (
	"context"
	"errors"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"shop/notification/internal/infra/mailer"
)

func newDispatcherFixture() (*emailDispatcher, *fakeSender, *fakeSaver) {
	sender := &fakeSender{}
	saver := &fakeSaver{}

	d := newEmailDispatcher(sender, saver)
	d.saveRetryDelay = 0

	return d, sender, saver
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
	assert.Len(t, saver.calls, 1)

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
		name              string
		sendErr           error
		saveErr           error
		wantUnrecoverable bool
		wantSaveCalls     int
		wantStatus        mailer.SendStatus
	}{
		{
			name:              "send fails and log is saved",
			sendErr:           errSend,
			wantUnrecoverable: false,
			wantSaveCalls:     1,
			wantStatus:        mailer.SendStatusFailed,
		},
		{
			name:              "mail sent but log cannot be saved",
			saveErr:           errSave,
			wantUnrecoverable: true,
			wantSaveCalls:     saveAttempts,
			wantStatus:        mailer.SendStatusSent,
		},
		{
			name:              "send and save both fail",
			sendErr:           errSend,
			saveErr:           errSave,
			wantUnrecoverable: false,
			wantSaveCalls:     saveAttempts,
			wantStatus:        mailer.SendStatusFailed,
		},
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

			if tc.wantUnrecoverable {
				require.ErrorIs(t, err, ErrUnrecoverable)
			} else {
				require.NotErrorIs(t, err, ErrUnrecoverable)
			}

			assert.Len(t, sender.calls, 1)
			require.Len(t, saver.calls, tc.wantSaveCalls)

			got := saver.calls[0]
			assert.Equal(t, tc.wantStatus, got.Status)

			if tc.sendErr != nil {
				assert.Equal(t, tc.sendErr.Error(), got.Error)
			} else {
				assert.Empty(t, got.Error)
			}
		})
	}
}

func TestEmailDispatcher_Dispatch_RecoversFromTransientSaveFailure(t *testing.T) {
	t.Parallel()

	d, sender, saver := newDispatcherFixture()
	saver.err = errors.New("mongo blip")
	saver.failFirst = saveAttempts - 1

	err := d.dispatch(context.Background(), testEmailMessage())

	require.NoError(t, err)
	assert.Len(t, sender.calls, 1)
	assert.Len(t, saver.calls, saveAttempts)
	assert.Len(t, saver.saved, 1)
}

func TestEmailDispatcher_Dispatch_StopsSaveRetriesWhenContextIsCancelled(t *testing.T) {
	t.Parallel()

	d, sender, saver := newDispatcherFixture()
	d.saveRetryDelay = time.Hour
	saver.err = errors.New("mongo unavailable")

	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	err := d.dispatch(ctx, testEmailMessage())

	require.ErrorIs(t, err, context.Canceled)
	require.ErrorIs(t, err, ErrUnrecoverable)
	assert.Len(t, sender.calls, 1)
	assert.Len(t, saver.calls, 1)
}

func TestEmailDispatcher_Dispatch_UsesAttemptFromContext(t *testing.T) {
	t.Parallel()

	d, _, saver := newDispatcherFixture()
	ctx := withAttempt(context.Background(), 3)

	require.NoError(t, d.dispatch(ctx, testEmailMessage()))

	require.Len(t, saver.saved, 1)
	assert.Equal(t, 3, saver.saved[0].Attempts)
}
