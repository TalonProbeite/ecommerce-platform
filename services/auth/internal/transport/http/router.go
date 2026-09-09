// Package http provides HTTP routing and handlers for the auth service.
package http

import (
	"io"
	"log/slog"
	"net/http"

	json "encoding/json/v2"

	"github.com/labstack/echo/v4"
	echomw "github.com/labstack/echo/v4/middleware"

	"shop/auth/internal/config"
	"shop/auth/internal/infra/validator"
	"shop/auth/internal/transport/http/handler"
	"shop/auth/internal/transport/http/middleware"
)

// JSONV2Serializer implements the echo.JSONSerializer interface using encoding/json/v2.
type JSONV2Serializer struct{}

// Serialize converts an object to JSON and writes it to the HTTP response.
func (s *JSONV2Serializer) Serialize(c echo.Context, i interface{}, indent string) error {
	b, err := json.Marshal(i)
	if err != nil {
		return err
	}
	_, err = c.Response().Write(b)
	return err
}

// Deserialize reads JSON from the HTTP request body into an object.
func (s *JSONV2Serializer) Deserialize(c echo.Context, i interface{}) error {
	b, err := io.ReadAll(c.Request().Body)
	if err != nil {
		return err
	}

	err = json.Unmarshal(b, i)
	if err != nil {
		return echo.NewHTTPError(http.StatusBadRequest, err.Error())
	}
	return nil
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
	e.JSONSerializer = &JSONV2Serializer{}
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
