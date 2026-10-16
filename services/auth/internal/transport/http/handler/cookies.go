package handler

import (
	"net/http"
	"shop/auth/internal/domain"
	"time"

	"github.com/labstack/echo/v4"
)

const (
	accessCookieTTL  = 15 * time.Minute
	refreshCookieTTL = 7 * 24 * time.Hour
	oauthCookieTTL   = 15 * time.Minute
)

func setCookie(c echo.Context, name, value string, ttl time.Duration, sameSite http.SameSite) {
	cookie := &http.Cookie{Name: name, Value: value, Path: "/", Expires: time.Now().Add(ttl), MaxAge: int(ttl.Seconds()), HttpOnly: true, Secure: true, SameSite: http.SameSiteStrictMode}
	if sameSite == http.SameSiteLaxMode {
		cookie.SameSite = http.SameSiteLaxMode
	}
	c.SetCookie(cookie)
}

func clearCookie(c echo.Context, name string, sameSite http.SameSite) {
	cookie := &http.Cookie{Name: name, Value: "", Path: "/", Expires: time.Unix(1, 0), MaxAge: -1, HttpOnly: true, Secure: true, SameSite: http.SameSiteStrictMode}
	if sameSite == http.SameSiteLaxMode {
		cookie.SameSite = http.SameSiteLaxMode
	}
	c.SetCookie(cookie)
}

func setAuthCookies(c echo.Context, tokens domain.TokenPair) {
	setCookie(c, "access_token", tokens.AccessToken, accessCookieTTL, http.SameSiteStrictMode)
	setCookie(c, "refresh_token", tokens.RefreshToken, refreshCookieTTL, http.SameSiteStrictMode)
}
