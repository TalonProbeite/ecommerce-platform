package middleware

import (
	"crypto/rsa"

	"github.com/labstack/echo/v4"
	echojwt "github.com/labstack/echo-jwt/v4"
)

func RequireAuth(pubKey *rsa.PublicKey) echo.MiddlewareFunc {
	config := echojwt.Config{
		SigningKey:    pubKey,
		SigningMethod: "RS256",
		TokenLookup:   "cookie:access_token",
		ContextKey:    "user_jwt",
	}

	return echojwt.WithConfig(config)
}