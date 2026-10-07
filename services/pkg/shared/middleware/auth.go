package middleware

import (
	"net/http"

	"shop/shared/roles"

	"github.com/labstack/echo/v4"
)

const (
	AccessTokenCookie = "access_token"
	UserIDContextKey  = "UserID"
	RoleContextKey    = "role"
)

type TokenVerifier interface {
	VerifyToken(
		token string,
	) (userID string, role roles.Role, err error)
}

func AuthCheck(
	verifier TokenVerifier,
) echo.MiddlewareFunc {
	return func(next echo.HandlerFunc) echo.HandlerFunc {
		return func(c echo.Context) error {
			cookie, err := c.Cookie(AccessTokenCookie)
			if err != nil {
				return c.JSON(
					http.StatusUnauthorized,
					map[string]string{
						"error": "missing access token",
					},
				)
			}

			userID, role, err := verifier.VerifyToken(cookie.Value)
			if err != nil {
				return c.JSON(
					http.StatusUnauthorized,
					map[string]string{
						"error": "invalid token",
					},
				)
			}

			c.Set(UserIDContextKey, userID)
			c.Set(RoleContextKey, role)

			return next(c)
		}
	}
}

func RequireRoles(
	allowedRoles ...roles.Role,
) echo.MiddlewareFunc {
	return func(next echo.HandlerFunc) echo.HandlerFunc {
		return func(c echo.Context) error {
			role, ok := c.Get(RoleContextKey).(roles.Role)
			if !ok {
				return c.JSON(
					http.StatusForbidden,
					map[string]string{
						"error": "insufficient permissions",
					},
				)
			}

			for _, allowedRole := range allowedRoles {
				if role == allowedRole {
					return next(c)
				}
			}

			return c.JSON(
				http.StatusForbidden,
				map[string]string{
					"error": "insufficient permissions",
				},
			)
		}
	}
}

func AdminOrAnalyst(
	next echo.HandlerFunc,
) echo.HandlerFunc {
	return RequireRoles(
		roles.RoleAdmin,
		roles.RoleAnalyst,
	)(next)
}

func AdminOnly(next echo.HandlerFunc) echo.HandlerFunc {
	return func(c echo.Context) error {
		role, ok := c.Get(RoleContextKey).(roles.Role)
		if !ok || role != roles.RoleAdmin {
			return c.JSON(
				http.StatusForbidden,
				map[string]string{
					"error": "insufficient permissions",
				},
			)
		}

		return next(c)
	}
}
