package http

import (
	"context"
	"io"
	"log/slog"
	nethttp "net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"shop/notification/internal/domain"
	"shop/notification/internal/services"
	"shop/notification/internal/transport/http/handlers"

	"github.com/labstack/echo/v4"
)

type fakeEventReader struct {
	byID       domain.EventLog
	byIDErr    error
	history    []domain.EventLog
	historyErr error
	gotFilters *domain.HistoryFilters
	gotID      string
}

func (f *fakeEventReader) GetByID(_ context.Context, id string) (domain.EventLog, error) {
	f.gotID = id

	if f.byIDErr != nil {
		return domain.EventLog{}, f.byIDErr
	}

	return f.byID, nil
}

func (f *fakeEventReader) GetHistory(_ context.Context, filters *domain.HistoryFilters) ([]domain.EventLog, error) {
	f.gotFilters = filters

	if f.historyErr != nil {
		return nil, f.historyErr
	}

	return f.history, nil
}

func passThrough(next echo.HandlerFunc) echo.HandlerFunc {
	return next
}

func buildRouter(reader *fakeEventReader, auth echo.MiddlewareFunc) *echo.Echo {
	log := slog.New(slog.NewTextHandler(io.Discard, nil))

	return NewRouter(nil, log, Handlers{
		HealthHandler:    handlers.NewHealthHandler(nil, nil),
		AnalyticsHandler: handlers.NewAnalyticsHandlers(services.NewAnalyticsService(reader)),
	}, Middlewares{
		AuthCheck:      auth,
		AdminOrAnalyst: passThrough,
	})
}

func doRequest(t *testing.T, router *echo.Echo, path string) *httptest.ResponseRecorder {
	t.Helper()

	req := httptest.NewRequest(nethttp.MethodGet, path, nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	return rec
}

func TestRouterHealthEndpoints(t *testing.T) {
	t.Parallel()

	router := buildRouter(&fakeEventReader{}, passThrough)

	health := doRequest(t, router, "/api/healthz")
	if health.Code != nethttp.StatusOK {
		t.Fatalf("GET /api/healthz status = %d, want %d", health.Code, nethttp.StatusOK)
	}

	ready := doRequest(t, router, "/api/readyz")
	if ready.Code != nethttp.StatusServiceUnavailable {
		t.Fatalf("GET /api/readyz status = %d, want %d", ready.Code, nethttp.StatusServiceUnavailable)
	}
}

func TestRouterProtectsPrivateNotifications(t *testing.T) {
	t.Parallel()

	deny := func(next echo.HandlerFunc) echo.HandlerFunc {
		return func(c echo.Context) error {
			return c.JSON(nethttp.StatusUnauthorized, map[string]string{"error": "unauthorized"})
		}
	}

	router := buildRouter(&fakeEventReader{}, deny)

	rec := doRequest(t, router, "/api/private/notifications/abc123")
	if rec.Code != nethttp.StatusUnauthorized {
		t.Fatalf("GET notification status = %d, want %d", rec.Code, nethttp.StatusUnauthorized)
	}
}

func TestRouterGetNotificationByID(t *testing.T) {
	t.Parallel()

	reader := &fakeEventReader{byID: domain.EventLog{Type: "order.paid", Recipient: "jane@example.com"}}
	router := buildRouter(reader, passThrough)

	rec := doRequest(t, router, "/api/private/notifications/abc123")
	if rec.Code != nethttp.StatusOK {
		t.Fatalf("GET notification status = %d, want %d", rec.Code, nethttp.StatusOK)
	}

	if reader.gotID != "abc123" {
		t.Fatalf("repository got id %q, want %q", reader.gotID, "abc123")
	}

	if body := rec.Body.String(); !strings.Contains(body, "order.paid") {
		t.Fatalf("response body = %s", body)
	}
}

func TestRouterGetNotificationByIDNotFound(t *testing.T) {
	t.Parallel()

	reader := &fakeEventReader{byIDErr: domain.ErrNotificationNotFound}
	router := buildRouter(reader, passThrough)

	rec := doRequest(t, router, "/api/private/notifications/missing")
	if rec.Code != nethttp.StatusNotFound {
		t.Fatalf("GET notification status = %d, want %d", rec.Code, nethttp.StatusNotFound)
	}
}

func TestRouterGetNotificationsHistory(t *testing.T) {
	t.Parallel()

	reader := &fakeEventReader{history: []domain.EventLog{{Type: "order.paid"}}}
	router := buildRouter(reader, passThrough)

	rec := doRequest(t, router, "/api/private/notifications?limit=10&offset=5&status=sent&channel=email")
	if rec.Code != nethttp.StatusOK {
		t.Fatalf("GET notifications status = %d, want %d", rec.Code, nethttp.StatusOK)
	}

	if reader.gotFilters == nil {
		t.Fatal("repository did not receive filters")
	}

	if reader.gotFilters.Limit != 10 || reader.gotFilters.Offset != 5 {
		t.Fatalf("filters limit/offset = %d/%d, want 10/5", reader.gotFilters.Limit, reader.gotFilters.Offset)
	}

	if reader.gotFilters.Status != "sent" || reader.gotFilters.Channel != "email" {
		t.Fatalf("filters status/channel = %q/%q", reader.gotFilters.Status, reader.gotFilters.Channel)
	}
}

func TestRouterRejectsInvalidHistoryQueries(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name string
		path string
	}{
		{name: "limit above maximum", path: "/api/private/notifications?limit=101"},
		{name: "negative offset", path: "/api/private/notifications?offset=-1"},
		{name: "invalid start date", path: "/api/private/notifications?start_date=yesterday"},
		{name: "start after end", path: "/api/private/notifications?start_date=2026-11-20T12:00:00Z&end_date=2026-11-20T10:00:00Z"},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			router := buildRouter(&fakeEventReader{}, passThrough)

			rec := doRequest(t, router, tc.path)
			if rec.Code != nethttp.StatusBadRequest {
				t.Fatalf("%s status = %d, want %d", tc.path, rec.Code, nethttp.StatusBadRequest)
			}
		})
	}
}
