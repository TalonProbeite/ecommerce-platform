// Package middleware provides Echo middleware components.
package middleware

import (
	"fmt"
	"net/http"
	"shop/auth/internal/infra/crypto"
	"shop/auth/internal/infra/repository"

	"github.com/labstack/echo/v4"
)

// AuthCheck returns Echo middleware to validate JWT access token cookies.
func AuthCheck(tokenMng *crypto.JWTManager, sessRepo *repository.SessionRepo) echo.MiddlewareFunc {
	return func(next echo.HandlerFunc) echo.HandlerFunc {
		return func(c echo.Context) error {
			cookie, err := c.Cookie("access_token")
			if err != nil {
				return c.JSON(http.StatusUnauthorized, map[string]string{"error": "missing access token"})
			}

			UserID, role, err := tokenMng.VerifyToken(cookie.Value)
			if err != nil {
				return c.JSON(http.StatusUnauthorized, map[string]string{"error": "invalid token"})
			}

			acKey := fmt.Sprintf("access:%s", cookie.Value)
			UserIDStorage, err := sessRepo.GetValue(c.Request().Context(), acKey)
			if err != nil || UserIDStorage != UserID {
				return c.JSON(http.StatusUnauthorized, map[string]string{"error": "session expired"})
			}
			c.Set("UserID", UserID)
			c.Set("role", role)

			return next(c)
		}
	}
}
