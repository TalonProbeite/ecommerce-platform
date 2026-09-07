package http

import (
	"log/slog"

	"github.com/labstack/echo/v4"
	echomw "github.com/labstack/echo/v4/middleware"

	"shop/auth/internal/config"
	"shop/auth/internal/infra/validator"
	"shop/auth/internal/transport/http/handler"
	"shop/auth/internal/transport/http/middleware"
)

type Handlers struct {
	HealthHandler *handler.HealthHandler
	ReadyzHandler *handler.ReadyzHandler
	AuthHandler *handler.AuthHandler
}

type Middlewares struct {
	AuthCheck echo.MiddlewareFunc
}

func NewRouter(cfg *config.Config, log *slog.Logger, h Handlers, m Middlewares) *echo.Echo {
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

	return e
}
