package services

import (
	"encoding/json"
	"errors"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"shop/notification/internal/infra/mailer"
	"shop/shared/events"
	"shop/shared/rabbitmq"
)

const testRecipient = "customer@example.com"

type handlerFixture struct {
	renderer *fakeRenderer
	sender   *fakeSender
	saver    *fakeSaver
	contacts *fakeContactStore
	user     *UserEventHandler
	order    *OrderEventHandler
}

func newHandlerFixture() *handlerFixture {
	renderer := &fakeRenderer{}
	sender := &fakeSender{}
	saver := &fakeSaver{}
	contacts := &fakeContactStore{email: testRecipient}

	return &handlerFixture{
		renderer: renderer,
		sender:   sender,
		saver:    saver,
		contacts: contacts,
		user:     NewUserEventHandler(renderer, sender, saver, contacts),
		order:    NewOrderEventHandler(renderer, sender, saver, contacts),
	}
}

type handlerCase struct {
	name       string
	handle     func(f *handlerFixture) rabbitmq.HandlerFunc
	event      any
	eventKey   string
	subject    string
	wantRender string

	wantPayload any
}

func handlerCases() []handlerCase {
	return []handlerCase{
		{
			name:       "email verification requested",
			handle:     func(f *handlerFixture) rabbitmq.HandlerFunc { return f.user.HandleEmailVerificationRequested },
			event:      events.EmailVerificationRequestedEvent{UserID: "user-1", Email: testRecipient, Code: "123456"},
			eventKey:   events.EmailVerificationRequestedEventKey,
			subject:    events.EmailVerificationSubject,
			wantRender: "RenderVerification(123456,15)",

			wantPayload: events.EmailVerificationRequestedEvent{UserID: "user-1", Email: testRecipient},
		},
		{
			name:       "welcome email requested",
			handle:     func(f *handlerFixture) rabbitmq.HandlerFunc { return f.user.HandleWelcomeEmailRequested },
			event:      events.WelcomeEmailRequestedEvent{UserID: "user-1", Email: testRecipient, Name: "John"},
			eventKey:   events.WelcomeEmailRequestedEventKey,
			subject:    events.WelcomeEmailSubject,
			wantRender: "RenderWelcome(John)",
		},
		{
			name:       "order confirmed",
			handle:     func(f *handlerFixture) rabbitmq.HandlerFunc { return f.order.HandleOrderConfirmed },
			event:      events.OrderConfirmedEvent{OrderID: "order-1", Email: testRecipient},
			eventKey:   events.OrderConfirmedEventKey,
			subject:    events.OrderConfirmedSubject,
			wantRender: "RenderConfirmed(order-1)",
		},
		{
			name:       "order paid",
			handle:     func(f *handlerFixture) rabbitmq.HandlerFunc { return f.order.HandleOrderPaid },
			event:      events.OrderPaidEvent{OrderID: "order-1", Email: testRecipient, Amount: 100},
			eventKey:   events.OrderPaidEventKey,
			subject:    events.OrderPaidSubject,
			wantRender: "RenderPaid(order-1,100)",
		},
		{
			name:       "order cancelled",
			handle:     func(f *handlerFixture) rabbitmq.HandlerFunc { return f.order.HandleOrderCancelled },
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

func TestUserHandlers_EmailChangedUpdatesContactMapping(t *testing.T) {
	t.Parallel()

	f := newHandlerFixture()
	event := events.EmailChangedEvent{UserID: "user-1", Email: "new@example.com"}

	require.NoError(t, f.user.HandleEmailChanged(t.Context(), mustMarshal(t, event)))

	assert.Equal(t, "user-1", f.contacts.savedID)
	assert.Equal(t, "new@example.com", f.contacts.savedEmail)
	assert.Empty(t, f.sender.calls)
	assert.Empty(t, f.saver.saved)
}

func TestUserHandlers_EmailChangedRequiresFields(t *testing.T) {
	t.Parallel()

	f := newHandlerFixture()

	err := f.user.HandleEmailChanged(t.Context(), mustMarshal(t, events.EmailChangedEvent{UserID: "user-1"}))

	require.ErrorIs(t, err, rabbitmq.ErrUnrecoverable)
	assert.Empty(t, f.contacts.savedID)
}

func TestOrderHandlers_ResolveRecipientFromCustomerID(t *testing.T) {
	t.Parallel()

	for _, tc := range []struct {
		name     string
		handle   func(*OrderEventHandler) rabbitmq.HandlerFunc
		event    any
		wantSubj string
	}{
		{
			name:     "order paid",
			handle:   func(h *OrderEventHandler) rabbitmq.HandlerFunc { return h.HandleOrderPaid },
			event:    events.OrderPaidEvent{OrderID: "order-1", CustomerID: "customer-1", Amount: 100},
			wantSubj: events.OrderPaidSubject,
		},
		{
			name:     "order cancelled",
			handle:   func(h *OrderEventHandler) rabbitmq.HandlerFunc { return h.HandleOrderCancelled },
			event:    events.OrderCancelledPayload{OrderID: "order-1", CustomerID: "customer-1", Reason: "out of stock"},
			wantSubj: events.OrderCancelledSubject,
		},
	} {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			f := newHandlerFixture()
			contacts := &fakeContactStore{email: testRecipient}
			handler := NewOrderEventHandler(f.renderer, f.sender, f.saver, contacts)

			require.NoError(t, tc.handle(handler)(t.Context(), mustMarshal(t, tc.event)))
			assert.Equal(t, "customer-1", contacts.gotID)
			assert.Equal(t, []sentMail{{To: testRecipient, Subject: tc.wantSubj, Body: fakeMailBody}}, f.sender.calls)
		})
	}
}

func TestOrderHandlers_ContactLookupFailureIsRetryable(t *testing.T) {
	t.Parallel()

	f := newHandlerFixture()
	contacts := &fakeContactStore{err: errors.New("contact store unavailable")}
	handler := NewOrderEventHandler(f.renderer, f.sender, f.saver, contacts)

	err := handler.HandleOrderPaid(t.Context(), mustMarshal(t, events.OrderPaidEvent{OrderID: "order-1", CustomerID: "customer-1"}))

	require.Error(t, err)
	require.NotErrorIs(t, err, rabbitmq.ErrUnrecoverable)
	assert.Empty(t, f.sender.calls)
}

func TestHandlers_InvalidPayload_IsUnrecoverableAndHasNoSideEffects(t *testing.T) {
	t.Parallel()

	for _, tc := range handlerCases() {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			f := newHandlerFixture()

			err := tc.handle(f)(t.Context(), []byte("{not json"))

			require.ErrorIs(t, err, rabbitmq.ErrUnrecoverable)
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
			require.NotErrorIs(t, err, rabbitmq.ErrUnrecoverable)
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

func TestHandlers_EmailVerificationRequested_DoesNotPersistVerificationCode(t *testing.T) {
	t.Parallel()

	f := newHandlerFixture()
	event := events.EmailVerificationRequestedEvent{UserID: "user-1", Email: testRecipient, Code: "123456"}

	require.NoError(t, f.user.HandleEmailVerificationRequested(t.Context(), mustMarshal(t, event)))

	assert.Equal(t, []string{"RenderVerification(123456,15)"}, f.renderer.calls)

	require.Len(t, f.saver.saved, 1)
	assert.Equal(t, events.EmailVerificationRequestedEvent{UserID: "user-1", Email: testRecipient}, f.saver.saved[0].Payload)
	assert.Equal(t, "user-1", f.contacts.savedID)
	assert.Equal(t, testRecipient, f.contacts.savedEmail)
}
