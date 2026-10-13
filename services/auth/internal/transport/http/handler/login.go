package handler

import (
	"errors"
	"log/slog"
	"net/http"
	"shop/auth/internal/application"
	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"

	"github.com/labstack/echo/v4"
)

type AuthHandler struct {
	authService *application.AuthService
	log         *slog.Logger
	frontendURL string
}

func NewAuthHandler(authService *application.AuthService, log *slog.Logger, frontendURL ...string) *AuthHandler {
	origin := "https://localhost"
	if len(frontendURL) > 0 {
		origin = frontendURL[0]
	}
	return &AuthHandler{
		authService: authService,
		log:         log,
		frontendURL: origin,
	}
}

func (h *AuthHandler) Login(c echo.Context) error {
	var req dto.LoginRequest

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{"error": "invalid request format"})
	}

	if err := c.Validate(&req); err != nil {
		return c.JSON(http.StatusUnprocessableEntity, map[string]string{"error": err.Error()})
	}

	tokens, profile, err := h.authService.Login(c.Request().Context(), &req)
	if err != nil {
		h.log.Error("login failed", slog.Any("error", err), slog.String("email", req.Email))

		switch {
		case errors.Is(err, domain.ErrUserNotFound), errors.Is(err, domain.ErrInvalidCredentials):
			return c.JSON(http.StatusUnauthorized, map[string]string{"error": "invalid email or password"})

		case errors.Is(err, domain.ErrUserNotActive):
			return c.JSON(http.StatusForbidden, map[string]string{"error": "account is not active"})

		default:
			return c.JSON(http.StatusInternalServerError, map[string]string{"error": "internal server error"})
		}
	}

	setAuthCookies(c, tokens)

	return c.JSON(http.StatusOK, profile)
}
