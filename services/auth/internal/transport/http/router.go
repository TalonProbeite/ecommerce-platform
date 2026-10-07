package http

import (
	"io"
	"log/slog"
	"net/http"
	"shop/auth/internal/config"
	"shop/auth/internal/infra/validator"
	"shop/auth/internal/transport/http/handler"
	"shop/shared/middleware"

	json "encoding/json/v2"

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
	HealthHandler  *handler.HealthHandler
	ReadyzHandler  *handler.ReadyzHandler
	AuthHandler    *handler.AuthHandler
	ProfileHandler *handler.ProfileHandler
	AdminHandler   *handler.AdminHandler
}

type Middlewares struct {
	AuthCheck echo.MiddlewareFunc
	AdminOnly echo.MiddlewareFunc
}

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
	admin := e.Group("/api/admin")

	private.Use(m.AuthCheck)
	private.POST("/email/verify", handler.WithUserID(h.AuthHandler.VerifyEmail))
	private.POST("/email/resend", handler.WithUserID(h.AuthHandler.ResendCode))
	private.GET("/profile", handler.WithUserID(h.ProfileHandler.GetUserProfile))
	private.PATCH("/profile", handler.WithUserID(h.ProfileHandler.PatchUserProfile))
	private.PUT("/profile/password", handler.WithUserID(h.ProfileHandler.ResetPassword))

	admin.Use(m.AuthCheck, m.AdminOnly)
	admin.PATCH("/users/:id/role", handler.WithUserID(h.AdminHandler.UpdateRole))
	admin.PATCH("/users/:id/ban", handler.WithUserID(h.AdminHandler.BanUser))

	public.GET("/healthz", h.HealthHandler.Check)
	public.GET("/readyz", h.ReadyzHandler.Check)
	public.POST("/login", h.AuthHandler.Login)
	public.POST("/register", h.AuthHandler.Register)
	public.POST("/logout", h.AuthHandler.Logout)
	public.POST("/refresh", h.AuthHandler.Refresh)
	public.GET("/oauth/google", h.AuthHandler.GoogleAuth)
	public.GET("/oauth/google/callback", h.AuthHandler.GoogleCallback)
	public.POST("/oauth/complete", h.AuthHandler.CompleteOAuthRegistration)

	return e
}
