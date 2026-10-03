package services

import (
	"context"
	"fmt"
	"io"
	"log/slog"
	"sync"

	amqp "github.com/rabbitmq/amqp091-go"

	"shop/notification/internal/domain"
)

const fakeMailBody = "<html>rendered</html>"

func newTestLogger() *slog.Logger {
	return slog.New(slog.NewTextHandler(io.Discard, nil))
}

type sentMail struct {
	To      string
	Subject string
	Body    string
}

type fakeSender struct {
	err   error
	calls []sentMail
}

func (f *fakeSender) SendHTML(to, subject, body string) error {
	f.calls = append(f.calls, sentMail{To: to, Subject: subject, Body: body})

	return f.err
}

type fakeSaver struct {
	err   error
	saved []domain.EventLog
}

func (f *fakeSaver) SaveEvent(_ context.Context, event any) error {
	log, ok := event.(domain.EventLog)
	if !ok {
		panic(fmt.Sprintf("SaveEvent got %T, want domain.EventLog", event))
	}

	f.saved = append(f.saved, log)

	return f.err
}

type fakeRenderer struct {
	err   error
	calls []string
}

func (f *fakeRenderer) record(format string, args ...any) (string, error) {
	f.calls = append(f.calls, fmt.Sprintf(format, args...))

	if f.err != nil {
		return "", f.err
	}

	return fakeMailBody, nil
}

func (f *fakeRenderer) RenderVerification(code string, ttlMinutes int) (string, error) {
	return f.record("RenderVerification(%v,%v)", code, ttlMinutes)
}

func (f *fakeRenderer) RenderWelcome(name string) (string, error) {
	return f.record("RenderWelcome(%v)", name)
}

func (f *fakeRenderer) RenderConfirmed(orderID string) (string, error) {
	return f.record("RenderConfirmed(%v)", orderID)
}

func (f *fakeRenderer) RenderPaid(orderID string, amount float64) (string, error) {
	return f.record("RenderPaid(%v,%v)", orderID, amount)
}

func (f *fakeRenderer) RenderCancelled(orderID string, reason string) (string, error) {
	return f.record("RenderCancelled(%v,%v)", orderID, reason)
}

type fakeReader struct {
	err          error
	byID         domain.EventLog
	history      []domain.EventLog
	gotID        string
	gotFilters   *domain.HistoryFilters
	getByIDCalls int
	historyCalls int
}

func (f *fakeReader) GetByID(_ context.Context, id string) (domain.EventLog, error) {
	f.getByIDCalls++
	f.gotID = id

	return f.byID, f.err
}

func (f *fakeReader) GetHistory(_ context.Context, filters *domain.HistoryFilters) ([]domain.EventLog, error) {
	f.historyCalls++
	f.gotFilters = filters

	return f.history, f.err
}

const (
	ackKindAck    = "ack"
	ackKindNack   = "nack"
	ackKindReject = "reject"
)

type ackRecord struct {
	kind    string
	requeue bool
}

type fakeAcknowledger struct {
	mu      sync.Mutex
	records []ackRecord
}

func (f *fakeAcknowledger) Ack(_ uint64, _ bool) error {
	f.add(ackRecord{kind: ackKindAck})

	return nil
}

func (f *fakeAcknowledger) Nack(_ uint64, _ bool, requeue bool) error {
	f.add(ackRecord{kind: ackKindNack, requeue: requeue})

	return nil
}

func (f *fakeAcknowledger) Reject(_ uint64, requeue bool) error {
	f.add(ackRecord{kind: ackKindReject, requeue: requeue})

	return nil
}

func (f *fakeAcknowledger) add(r ackRecord) {
	f.mu.Lock()
	defer f.mu.Unlock()

	f.records = append(f.records, r)
}

func (f *fakeAcknowledger) snapshot() []ackRecord {
	f.mu.Lock()
	defer f.mu.Unlock()

	return append([]ackRecord(nil), f.records...)
}

func newDelivery(key string, body []byte, ack *fakeAcknowledger) amqp.Delivery {
	return amqp.Delivery{
		Acknowledger: ack,
		RoutingKey:   key,
		Body:         body,
		DeliveryTag:  1,
	}
}
