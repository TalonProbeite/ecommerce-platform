package handler

import (
	"errors"
	"log/slog"
	"net/http"
	"time"

	"github.com/labstack/echo/v4"

	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"
)

func (h *AuthHandler) Register(c echo.Context) error {
	var req dto.RegisterRequest

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{"error": "invalid request format"})
	}

	if err := c.Validate(&req); err != nil {
		return c.JSON(http.StatusUnprocessableEntity, map[string]string{"error": err.Error()})
	}

	tokens, err := h.authService.Registration(c.Request().Context(), &req)
	if err != nil {
		h.log.Error("registration failed", slog.Any("error", err), slog.String("email", req.Email))

		switch {
		case errors.Is(err, domain.ErrEmailAlreadyExists):
			return c.JSON(http.StatusConflict, map[string]string{"error": "email already in use"})

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

	return c.JSON(http.StatusCreated, map[string]string{"message": "successfully registered"})
}
