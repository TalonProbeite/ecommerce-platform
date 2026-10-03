package middleware

import (
	"net/http"

	"shop/auth/internal/infra/crypto"

	"github.com/labstack/echo/v4"
)

func AuthCheck(
	tokenMng *crypto.JWTManager,
) echo.MiddlewareFunc {
	return func(next echo.HandlerFunc) echo.HandlerFunc {
		return func(c echo.Context) error {
			cookie, err := c.Cookie("access_token")
			if err != nil {
				return c.JSON(
					http.StatusUnauthorized,
					map[string]string{"error": "missing access token"},
				)
			}

			userID, role, err := tokenMng.VerifyToken(cookie.Value)
			if err != nil {
				return c.JSON(
					http.StatusUnauthorized,
					map[string]string{"error": "invalid token"},
				)
			}

			c.Set("UserID", userID)
			c.Set("role", role)

			return next(c)
		}
	}
}
