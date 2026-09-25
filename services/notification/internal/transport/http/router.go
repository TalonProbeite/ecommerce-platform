package http

import (
	json "encoding/json/v2"
	"io"
	"log/slog"
	"net/http"
	"shop/notification/internal/config"
	"shop/notification/internal/infra/validator"
	"shop/notification/internal/transport/http/handlers"
	"shop/notification/internal/transport/http/middleware"

	"github.com/labstack/echo/v4"
	echomw "github.com/labstack/echo/v4/middleware"
)

type JSONV2Serializer struct{}

func (s *JSONV2Serializer) Serialize(c echo.Context, i interface{}, indent string) error {
	b, err := json.Marshal(i)
	if err != nil {
		return err
	}
	_, err = c.Response().Write(b)
	return err
}

func (s *JSONV2Serializer) Deserialize(c echo.Context, i interface{}) error {
	limitedBody := io.LimitReader(c.Request().Body, 1<<20)
	b, err := io.ReadAll(limitedBody)
	if err != nil {
		return err
	}

	err = json.Unmarshal(b, i)
	if err != nil {
		return echo.NewHTTPError(http.StatusBadRequest, err.Error())
	}
	return nil
}

type Handlers struct {
	HealthHandler *handlers.HealthHandler
}

func NewRouter(_ *config.Config, log *slog.Logger, h Handlers) *echo.Echo {
	e := echo.New()
	e.HideBanner = true
	e.HidePort = true
	e.JSONSerializer = &JSONV2Serializer{}
	e.Validator = validator.New()

	e.Use(echomw.Recover())
	e.Use(middleware.RequestLogger(log))

	public := e.Group("/api")

	public.GET("/healthz", h.HealthHandler.Healthz)
	public.GET("/readyz", h.HealthHandler.Readyz)

	return e
}
