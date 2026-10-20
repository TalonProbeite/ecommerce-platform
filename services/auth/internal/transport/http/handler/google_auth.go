package handler

import (
	"errors"
	"net/http"
	"net/url"
	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"

	"github.com/labstack/echo/v4"
)

func (h *AuthHandler) GoogleAuth(c echo.Context) error {
	c.Response().Header().Set("Cache-Control", "no-store")
	start, err := h.authService.Google(c.Request().Context())
	if err != nil {
		return h.oauthError(c, err)
	}
	setCookie(c, "oauth_state", start.State, oauthCookieTTL, http.SameSiteLaxMode)
	return c.Redirect(http.StatusSeeOther, start.URL)
}

func (h *AuthHandler) GoogleCallback(c echo.Context) error {
	c.Response().Header().Set("Cache-Control", "no-store")
	// Do not forward the Google code/state URL as a Referer to the frontend.
	c.Response().Header().Set("Referrer-Policy", "no-referrer")
	browserState := ""
	if cookie, err := c.Cookie("oauth_state"); err == nil {
		browserState = cookie.Value
	}
	state := c.QueryParam("state")
	if providerError := c.QueryParam("error"); providerError != "" {
		// Consume a bound canceled transaction, without exchanging any code.
		if state == "" || browserState == "" || state != browserState {
			return h.oauthRedirectError(c, "oauth_invalid_state")
		}
		clearCookie(c, "oauth_state", http.SameSiteLaxMode)
		if err := h.authService.CancelOAuth(c.Request().Context(), state, browserState); err != nil {
			return h.oauthRedirectError(c, "oauth_invalid_state")
		}
		if providerError == "access_denied" {
			return h.oauthRedirectError(c, "oauth_canceled")
		}
		return h.oauthRedirectError(c, "oauth_failed")
	}
	result, err := h.authService.GoogleCallback(c.Request().Context(), state, c.QueryParam("code"), browserState)
	clearCookie(c, "oauth_state", http.SameSiteLaxMode)
	if err != nil {
		code := "oauth_failed"
		if errors.Is(err, domain.ErrOAuthStateInvalid) {
			code = "oauth_invalid_state"
		}
		if errors.Is(err, domain.ErrUserNotActive) {
			code = "account_inactive"
		}
		h.log.Info("Google callback failed")
		return h.oauthRedirectError(c, code)
	}
	if result.RegistrationKey != "" {
		setCookie(c, "profile_key", result.RegistrationKey, oauthCookieTTL, http.SameSiteStrictMode)
		return c.Redirect(http.StatusSeeOther, h.frontendURL+"/register/complete")
	}
	setAuthCookies(c, *result.Tokens)
	clearCookie(c, "profile_key", http.SameSiteStrictMode)
	return c.Redirect(http.StatusSeeOther, h.frontendURL+"/oauth/success")
}

func (h *AuthHandler) oauthRedirectError(c echo.Context, code string) error {
	return c.Redirect(http.StatusSeeOther, h.frontendURL+"/login?oauth_error="+url.QueryEscape(code))
}

func (h *AuthHandler) GetOAuthProfile(c echo.Context) error {
	c.Response().Header().Set("Cache-Control", "no-store")
	cookie, err := c.Cookie("profile_key")
	if err != nil || cookie.Value == "" {
		return h.oauthError(c, domain.ErrOAuthRegistrationExpired)
	}
	profile, err := h.authService.GetOAuthProfile(c.Request().Context(), cookie.Value)
	if err != nil {
		return h.oauthError(c, err)
	}
	return c.JSON(http.StatusOK, profile)
}

func (h *AuthHandler) CompleteOAuthRegistration(c echo.Context) error {
	c.Response().Header().Set("Cache-Control", "no-store")
	// Credentialed browser POSTs must come from the configured frontend origin.
	if c.Request().Header.Get("Origin") != h.frontendURL {
		return c.JSON(http.StatusForbidden, map[string]string{"code": "invalid_origin", "error": "request origin is not allowed"})
	}
	cookie, err := c.Cookie("profile_key")
	if err != nil || cookie.Value == "" {
		return h.oauthError(c, domain.ErrOAuthRegistrationExpired)
	}
	var req dto.CompleteReq
	if bindErr := c.Bind(&req); bindErr != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{"code": "invalid_request", "error": "invalid request format"})
	}
	data := domain.OAuthRegistrationData{Key: cookie.Value, Phone: req.Phone}
	if req.FirstName != nil {
		data.FirstName = *req.FirstName
		data.FirstNameProvided = true
	}
	if req.LastName != nil {
		data.LastName = *req.LastName
		data.LastNameProvided = true
	}
	tokens, err := h.authService.CompleteOAuthRegistration(c.Request().Context(), data)
	if err != nil {
		return h.oauthError(c, err)
	}
	setAuthCookies(c, tokens)
	clearCookie(c, "profile_key", http.SameSiteStrictMode)
	return c.JSON(http.StatusCreated, map[string]string{"message": "successfully registered"})
}

func (h *AuthHandler) oauthError(c echo.Context, err error) error {
	status, code, message := http.StatusInternalServerError, "internal_error", "could not complete Google sign-in; please try again"
	switch {
	case errors.Is(err, domain.ErrOAuthRegistrationExpired):
		status, code, message = http.StatusUnauthorized, "oauth_registration_expired", "Google registration expired; please start again"
		clearCookie(c, "profile_key", http.SameSiteStrictMode)
	case errors.Is(err, domain.ErrValidationFailed):
		status, code, message = http.StatusUnprocessableEntity, "validation_failed", "check your name and phone number"
	case errors.Is(err, domain.ErrEmailAlreadyExists):
		status, code, message = http.StatusConflict, "email_already_exists", "this email already has an account; sign in using your existing method"
	case errors.Is(err, domain.ErrOAuthRegistrationBusy):
		status, code, message = http.StatusConflict, "oauth_registration_busy", "registration is being processed; please try again shortly"
	case errors.Is(err, domain.ErrUserNotActive):
		status, code, message = http.StatusForbidden, "account_inactive", "account is not active"
	}
	if status == http.StatusInternalServerError {
		h.log.Error("OAuth operation failed")
	}
	return c.JSON(status, map[string]string{"code": code, "error": message})
}
