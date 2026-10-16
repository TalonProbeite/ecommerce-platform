package handler

import (
	"errors"
	"log/slog"
	"net/http"
	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"

	"github.com/labstack/echo/v4"
)

func (h *AuthHandler) Register(c echo.Context) error {
	var req dto.RegisterRequest

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{"error": "invalid request format"})
	}

	if err := c.Validate(&req); err != nil {
		return c.JSON(http.StatusUnprocessableEntity, map[string]string{"error": err.Error()})
	}

	tokens, profile, err := h.authService.Registration(c.Request().Context(), &req)
	if err != nil {
		h.log.Error("registration failed", slog.Any("error", err), slog.String("email", req.Email))

		switch {
		case errors.Is(err, domain.ErrEmailAlreadyExists):
			return c.JSON(http.StatusConflict, map[string]string{"error": "email already in use"})

		default:
			return c.JSON(http.StatusInternalServerError, map[string]string{"error": "internal server error"})
		}
	}

	setAuthCookies(c, tokens)

	return c.JSON(http.StatusCreated, profile)
}
