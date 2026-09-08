// Package http provides HTTP routing and handlers for the auth service.
package http

import (
	"log/slog"
	"net/http"

	json "github.com/goccy/go-json"
	"github.com/labstack/echo/v4"
	echomw "github.com/labstack/echo/v4/middleware"

	"shop/auth/internal/config"
	"shop/auth/internal/infra/validator"
	"shop/auth/internal/transport/http/handler"
	"shop/auth/internal/transport/http/middleware"
)

// FastJSONSerializer implements the echo.JSONSerializer interface using go-json.
type FastJSONSerializer struct{}

// Serialize converts an object to JSON and writes it to the HTTP response.
func (s *FastJSONSerializer) Serialize(c echo.Context, i interface{}, indent string) error {
	enc := json.NewEncoder(c.Response())
	if indent != "" {
		enc.SetIndent("", indent)
	}
	return enc.Encode(i)
}

// Deserialize reads JSON from the HTTP request body into an object.
func (s *FastJSONSerializer) Deserialize(c echo.Context, i interface{}) error {
	err := json.NewDecoder(c.Request().Body).Decode(i)
	if syntaxErr, ok := err.(*json.SyntaxError); ok {
		return echo.NewHTTPError(http.StatusBadRequest, syntaxErr.Error())
	}
	return err
}

// Handlers holds references to all HTTP handler instances.
type Handlers struct {
	HealthHandler *handler.HealthHandler
	ReadyzHandler *handler.ReadyzHandler
	AuthHandler   *handler.AuthHandler
}

// Middlewares holds middleware functions used by the router.
type Middlewares struct {
	AuthCheck echo.MiddlewareFunc
}

// NewRouter configures and returns a new Echo HTTP router.
func NewRouter(_ *config.Config, log *slog.Logger, h Handlers, m Middlewares) *echo.Echo {
	e := echo.New()
	e.HideBanner = true
	e.HidePort = true
	e.JSONSerializer = &FastJSONSerializer{}
	e.Validator = validator.New()

	e.Use(echomw.Recover())
	e.Use(middleware.RequestLogger(log))

	public := e.Group("/api")
	private := e.Group("/api/private")

	private.Use(m.AuthCheck)

	public.GET("/healthz", h.HealthHandler.Check)
	public.GET("/readyz", h.ReadyzHandler.Check)
	public.POST("/login", h.AuthHandler.Login)
	public.POST("/register", h.AuthHandler.Register)

	return e
}