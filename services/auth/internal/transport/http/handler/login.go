package handler

import (
	"errors"
	"log/slog"
	"net/http"
	"time"

	"github.com/labstack/echo/v4"

	"shop/auth/internal/application"
	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"
)

type AuthHandler struct {
	authService *application.AuthService
	log         *slog.Logger
}

func NewAuthHandler(authService *application.AuthService, log *slog.Logger) *AuthHandler {
	return &AuthHandler{
		authService: authService,
		log:         log,
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

	tokens, err := h.authService.Login(c.Request().Context(), &req)
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

	c.SetCookie(&http.Cookie{
		Name:     "access_token",
		Value:    tokens.AccessToken,
		Path:     "/",
		Expires:  time.Now().Add(15 * time.Minute),
		HttpOnly: true,
		Secure:   true, 
		SameSite: http.SameSiteStrictMode,
	})

	c.SetCookie(&http.Cookie{
		Name:     "refresh_token",
		Value:    tokens.RefreshToken,
		Path:     "/api/auth/refresh",
		Expires:  time.Now().Add(7 * 24 * time.Hour),
		HttpOnly: true,
		Secure:   true,
		SameSite: http.SameSiteStrictMode,
	})

	return c.JSON(http.StatusOK, map[string]string{"message": "successfully logged in"})
}