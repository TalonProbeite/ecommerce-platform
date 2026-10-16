package handler

import (
	"log/slog"
	"net/http"

	"github.com/labstack/echo/v4"
)

func (h *AuthHandler) Refresh(c echo.Context) error {
	cookie, err := c.Cookie("refresh_token")
	if err != nil || cookie.Value == "" {
		return c.JSON(http.StatusUnauthorized, map[string]string{
			"error": "missing or invalid refresh token",
		})
	}

	tokens, err := h.authService.Refresh(c.Request().Context(), cookie.Value)
	if err != nil {
		h.log.Error("failed to refresh tokens", slog.Any("error", err))
		return c.JSON(http.StatusUnauthorized, map[string]string{
			"error": "invalid refresh token",
		})
	}

	setAuthCookies(c, tokens)

	return c.JSON(http.StatusOK, map[string]string{"message": "successfully refreshed"})
}
