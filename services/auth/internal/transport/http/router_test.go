package http

import (
	"io"
	"log/slog"
	nethttp "net/http"
	"net/http/httptest"
	"testing"

	"shop/auth/internal/transport/http/handler"

	"github.com/labstack/echo/v4"
)

func testLogger() *slog.Logger {
	return slog.New(slog.NewTextHandler(io.Discard, nil))
}

func request(t *testing.T, router *echo.Echo, method, path string) *httptest.ResponseRecorder {
	t.Helper()

	req := httptest.NewRequest(method, path, nil)
	rec := httptest.NewRecorder()
	router.ServeHTTP(rec, req)

	return rec
}

func TestNewRouterHealthEndpoints(t *testing.T) {
	t.Parallel()

	deny := func(next echo.HandlerFunc) echo.HandlerFunc {
		return func(c echo.Context) error {
			return c.JSON(nethttp.StatusUnauthorized, map[string]string{"error": "unauthorized"})
		}
	}

	router := NewRouter(nil, testLogger(), Handlers{
		HealthHandler: handler.NewHealthHandler(),
		ReadyzHandler: handler.NewReadyzHandler(nil, nil, nil),
	}, Middlewares{
		AuthCheck: deny,
		AdminOnly: deny,
	})

	health := request(t, router, nethttp.MethodGet, "/api/healthz")
	if health.Code != nethttp.StatusOK {
		t.Fatalf("GET /api/healthz status = %d, want %d", health.Code, nethttp.StatusOK)
	}

	if body := health.Body.String(); body != `{"status":"ok"}` {
		t.Fatalf("GET /api/healthz body = %s", body)
	}

	ready := request(t, router, nethttp.MethodGet, "/api/readyz")
	if ready.Code != nethttp.StatusServiceUnavailable {
		t.Fatalf("GET /api/readyz status = %d, want %d", ready.Code, nethttp.StatusServiceUnavailable)
	}
}

func TestNewRouterProtectsPrivateAndAdminRoutes(t *testing.T) {
	t.Parallel()

	deny := func(next echo.HandlerFunc) echo.HandlerFunc {
		return func(c echo.Context) error {
			return c.JSON(nethttp.StatusUnauthorized, map[string]string{"error": "unauthorized"})
		}
	}

	router := NewRouter(nil, testLogger(), Handlers{
		HealthHandler: handler.NewHealthHandler(),
		ReadyzHandler: handler.NewReadyzHandler(nil, nil, nil),
	}, Middlewares{
		AuthCheck: deny,
		AdminOnly: deny,
	})

	tests := []struct {
		method string
		path   string
	}{
		{method: nethttp.MethodGet, path: "/api/private/profile"},
		{method: nethttp.MethodPatch, path: "/api/admin/users/11111111-1111-4111-8111-111111111111/role"},
	}

	for _, tc := range tests {
		rec := request(t, router, tc.method, tc.path)
		if rec.Code != nethttp.StatusUnauthorized {
			t.Fatalf("%s %s status = %d, want %d", tc.method, tc.path, rec.Code, nethttp.StatusUnauthorized)
		}
	}
}

func TestNewRouterAdminRouteRequiresAdminRole(t *testing.T) {
	t.Parallel()

	authenticated := func(next echo.HandlerFunc) echo.HandlerFunc {
		return func(c echo.Context) error {
			c.Set("UserID", "11111111-1111-4111-8111-111111111111")

			return next(c)
		}
	}

	forbidAdmin := func(next echo.HandlerFunc) echo.HandlerFunc {
		return func(c echo.Context) error {
			return c.JSON(nethttp.StatusForbidden, map[string]string{"error": "forbidden"})
		}
	}

	router := NewRouter(nil, testLogger(), Handlers{
		HealthHandler: handler.NewHealthHandler(),
		ReadyzHandler: handler.NewReadyzHandler(nil, nil, nil),
	}, Middlewares{
		AuthCheck: authenticated,
		AdminOnly: forbidAdmin,
	})

	rec := request(t, router, nethttp.MethodPatch, "/api/admin/users/11111111-1111-4111-8111-111111111111/role")
	if rec.Code != nethttp.StatusForbidden {
		t.Fatalf("PATCH admin route status = %d, want %d", rec.Code, nethttp.StatusForbidden)
	}
}

func TestNewRouterUnknownRouteReturnsNotFound(t *testing.T) {
	t.Parallel()

	router := NewRouter(nil, testLogger(), Handlers{
		HealthHandler: handler.NewHealthHandler(),
		ReadyzHandler: handler.NewReadyzHandler(nil, nil, nil),
	}, Middlewares{})

	rec := request(t, router, nethttp.MethodGet, "/api/unknown")
	if rec.Code != nethttp.StatusNotFound {
		t.Fatalf("GET /api/unknown status = %d, want %d", rec.Code, nethttp.StatusNotFound)
	}
}
