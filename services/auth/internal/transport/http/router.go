// Package http provides HTTP routing and handlers for the auth service.
package http

import (
	"log/slog"
	"shop/auth/internal/config"
	"shop/auth/internal/infra/validator"
	"shop/auth/internal/transport/http/handler"
	"shop/auth/internal/transport/http/middleware"

	"github.com/labstack/echo/v4"
	echomw "github.com/labstack/echo/v4/middleware"
)

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
