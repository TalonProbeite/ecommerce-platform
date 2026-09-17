package handler

import (
	"net/http"

	"github.com/labstack/echo/v4"
)

func (h *AuthHandler) Logout(c echo.Context) error {
	var access, refresh string

	if accessCookie, err := c.Cookie("access_token"); err == nil {
		access = accessCookie.Value
	}
	if refreshCookie, err := c.Cookie("refresh_token"); err == nil {
		refresh = refreshCookie.Value
	}

	if access != "" || refresh != "" {
		err := h.authService.Logout(c.Request().Context(), refresh, access)
		if err != nil {
			h.log.Info("failed to remove tokens from the session store")
		}
	}
	c.SetCookie(&http.Cookie{
		Name:     "access_token",
		Value:    "",
		Path:     "/",
		MaxAge:   -1,
		HttpOnly: true,
		Secure:   true,
		SameSite: http.SameSiteStrictMode,
	})

	c.SetCookie(&http.Cookie{
		Name:     "refresh_token",
		Value:    "",
		Path:     "/auth/api/auth/refresh",
		MaxAge:   -1,
		HttpOnly: true,
		Secure:   true,
		SameSite: http.SameSiteStrictMode,
	})

	return c.JSON(http.StatusOK, map[string]string{"message": "successfully logged out"})
}
