package handler

import (
	"net/http"
	"time"

	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"

	"github.com/labstack/echo/v4"
)

func (h *AuthHandler) GoogleAuth(c echo.Context) error {
	url, err := h.authService.Google(c.Request().Context())
	if err != nil {
		h.log.Info("Failed to create redirect URL when attempting to log in via oauth")
		return c.JSON(http.StatusInternalServerError, map[string]string{"error": "failed to create URL"})
	}

	return c.Redirect(http.StatusTemporaryRedirect, url)
}

func (h *AuthHandler) GoogleCallback(c echo.Context) error {
	code := c.QueryParam("code")
	state := c.QueryParam("state")

	if code == "" || state == "" {
		return c.JSON(
			http.StatusBadRequest,
			map[string]string{"error": "missing oauth parameters"},
		)
	}

	result, err := h.authService.GoogleCallback(c.Request().Context(), state, code)
	if err != nil {
		h.log.Info("Google callback request error")
		return c.JSON(http.StatusInternalServerError, map[string]string{"error": "internal server error"})
	}

	if result.RegistrationKey == "" {
		c.SetCookie(&http.Cookie{
			Name:     "access_token",
			Value:    result.Tokens.AccessToken,
			Path:     "/",
			Expires:  time.Now().Add(15 * time.Minute),
			HttpOnly: true,
			Secure:   true,
			SameSite: http.SameSiteStrictMode,
		})

		c.SetCookie(&http.Cookie{
			Name:     "refresh_token",
			Value:    result.Tokens.RefreshToken,
			Path:     "/",
			Expires:  time.Now().Add(7 * 24 * time.Hour),
			HttpOnly: true,
			Secure:   true,
			SameSite: http.SameSiteStrictMode,
		})

		return c.JSON(http.StatusOK, map[string]string{"message": "successfully logged in"})
	}
	c.SetCookie(&http.Cookie{
		Name:     "profile_key",
		Value:    result.RegistrationKey,
		Path:     "/",
		Expires:  time.Now().Add(15 * time.Minute),
		HttpOnly: true,
		Secure:   true,
		SameSite: http.SameSiteStrictMode,
	})

	return c.JSON(http.StatusCreated, map[string]string{"message": "profile key successfully created"})
}

func (h *AuthHandler) CompleteOAuthRegistration(c echo.Context) error {
	var req dto.CompleteReq

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{"error": "invalid request format"})
	}
	key, err := c.Cookie("profile_key")
	if err != nil {
		return c.JSON(http.StatusUnauthorized, map[string]string{"error": "missing profile key"})
	}

	tokens, err := h.authService.CompleteOAuthRegistration(c.Request().Context(),
		domain.OAuthRegistrationData{
			Key:       key.Value,
			FirstName: req.FirstName,
			LastName:  req.LastName,
			Phone:     req.Phone,
		})
	if err != nil {
		h.log.Info("error completing registration")
		return c.JSON(http.StatusInternalServerError, map[string]string{"error": "failed to complete registration"})
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
		Path:     "/",
		Expires:  time.Now().Add(7 * 24 * time.Hour),
		HttpOnly: true,
		Secure:   true,
		SameSite: http.SameSiteStrictMode,
	})

	return c.JSON(http.StatusCreated, map[string]string{"message": "successfully registered"})
}
