package services

import (
	"encoding/json"
	"errors"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"shop/notification/internal/infra/mailer"
	"shop/shared/events"
)

const testRecipient = "customer@example.com"

type handlerFixture struct {
	renderer *fakeRenderer
	sender   *fakeSender
	saver    *fakeSaver
	user     *UserEventHandler
	order    *OrderEventHandler
}

func newHandlerFixture() *handlerFixture {
	renderer := &fakeRenderer{}
	sender := &fakeSender{}
	saver := &fakeSaver{}

	return &handlerFixture{
		renderer: renderer,
		sender:   sender,
		saver:    saver,
		user:     NewUserEventHandler(renderer, sender, saver),
		order:    NewOrderEventHandler(renderer, sender, saver),
	}
}

type handlerCase struct {
	name       string
	handle     func(f *handlerFixture) HandlerFunc
	event      any
	eventKey   string
	subject    string
	wantRender string

	wantPayload any
}

func handlerCases() []handlerCase {
	return []handlerCase{
		{
			name:       "user registered",
			handle:     func(f *handlerFixture) HandlerFunc { return f.user.HandleUserRegistered },
			event:      events.UserRegisteredEvent{Email: testRecipient, Code: "123456"},
			eventKey:   events.UserRegisteredEventKey,
			subject:    events.UserRegisteredSubject,
			wantRender: "RenderVerification(123456,15)",

			wantPayload: events.UserRegisteredEvent{Email: testRecipient},
		},
		{
			name:       "user email verified",
			handle:     func(f *handlerFixture) HandlerFunc { return f.user.HandleEmailVerified },
			event:      events.UserEmailVerifiedEvent{Email: testRecipient, Name: "John"},
			eventKey:   events.UserEmailVerifiedEventKey,
			subject:    events.UserEmailVerifiedSubject,
			wantRender: "RenderWelcome(John)",
		},
		{
			name:       "order confirmed",
			handle:     func(f *handlerFixture) HandlerFunc { return f.order.HandleOrderConfirmed },
			event:      events.OrderConfirmedEvent{OrderID: "order-1", Email: testRecipient},
			eventKey:   events.OrderConfirmedEventKey,
			subject:    events.OrderConfirmedSubject,
			wantRender: "RenderConfirmed(order-1)",
		},
		{
			name:       "order paid",
			handle:     func(f *handlerFixture) HandlerFunc { return f.order.HandleOrderPaid },
			event:      events.OrderPaidEvent{OrderID: "order-1", Email: testRecipient, Amount: 100},
			eventKey:   events.OrderPaidEventKey,
			subject:    events.OrderPaidSubject,
			wantRender: "RenderPaid(order-1,100)",
		},
		{
			name:       "order cancelled",
			handle:     func(f *handlerFixture) HandlerFunc { return f.order.HandleOrderCancelled },
			event:      events.OrderCancelledPayload{OrderID: "order-1", Email: testRecipient, Reason: "out of stock"},
			eventKey:   events.OrderCancelledEventKey,
			subject:    events.OrderCancelledSubject,
			wantRender: "RenderCancelled(order-1,out of stock)",
		},
	}
}

func mustMarshal(t *testing.T, v any) []byte {
	t.Helper()

	body, err := json.Marshal(v)
	require.NoError(t, err)

	return body
}

func TestHandlers_ValidEvent_SendsMailAndLogsIt(t *testing.T) {
	t.Parallel()

	for _, tc := range handlerCases() {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			f := newHandlerFixture()

			err := tc.handle(f)(t.Context(), mustMarshal(t, tc.event))

			require.NoError(t, err)
			assert.Equal(t, []string{tc.wantRender}, f.renderer.calls)
			assert.Equal(t, []sentMail{{To: testRecipient, Subject: tc.subject, Body: fakeMailBody}}, f.sender.calls)

			require.Len(t, f.saver.saved, 1)
			got := f.saver.saved[0]
			assert.Equal(t, tc.eventKey, got.Type)
			assert.Equal(t, channelEmail, got.Channel)
			assert.Equal(t, testRecipient, got.Recipient)
			assert.Equal(t, mailer.SendStatusSent, got.Status)

			wantPayload := tc.wantPayload
			if wantPayload == nil {
				wantPayload = tc.event
			}

			assert.Equal(t, wantPayload, got.Payload)
		})
	}
}

func TestHandlers_InvalidPayload_IsUnrecoverableAndHasNoSideEffects(t *testing.T) {
	t.Parallel()

	for _, tc := range handlerCases() {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			f := newHandlerFixture()

			err := tc.handle(f)(t.Context(), []byte("{not json"))

			require.ErrorIs(t, err, ErrUnrecoverable)
			assert.Empty(t, f.renderer.calls)
			assert.Empty(t, f.sender.calls)
			assert.Empty(t, f.saver.saved)
		})
	}
}

func TestHandlers_RenderFailure_ReturnsRetryableErrorWithoutSending(t *testing.T) {
	t.Parallel()

	errRender := errors.New("template broken")

	for _, tc := range handlerCases() {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			f := newHandlerFixture()
			f.renderer.err = errRender

			err := tc.handle(f)(t.Context(), mustMarshal(t, tc.event))

			require.ErrorIs(t, err, errRender)
			require.NotErrorIs(t, err, ErrUnrecoverable)
			assert.Empty(t, f.sender.calls)
			assert.Empty(t, f.saver.saved)
		})
	}
}

func TestHandlers_SendFailure_IsLoggedAsFailedAndReturned(t *testing.T) {
	t.Parallel()

	errSend := errors.New("smtp unavailable")

	for _, tc := range handlerCases() {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			f := newHandlerFixture()
			f.sender.err = errSend

			err := tc.handle(f)(t.Context(), mustMarshal(t, tc.event))

			require.ErrorIs(t, err, errSend)
			require.Len(t, f.saver.saved, 1)
			assert.Equal(t, mailer.SendStatusFailed, f.saver.saved[0].Status)
			assert.Equal(t, errSend.Error(), f.saver.saved[0].Error)
		})
	}
}

func TestHandlers_UserRegistered_DoesNotPersistVerificationCode(t *testing.T) {
	t.Parallel()

	f := newHandlerFixture()
	event := events.UserRegisteredEvent{Email: testRecipient, Code: "123456"}

	require.NoError(t, f.user.HandleUserRegistered(t.Context(), mustMarshal(t, event)))

	assert.Equal(t, []string{"RenderVerification(123456,15)"}, f.renderer.calls)

	require.Len(t, f.saver.saved, 1)
	assert.Equal(t, events.UserRegisteredEvent{Email: testRecipient}, f.saver.saved[0].Payload)
}
