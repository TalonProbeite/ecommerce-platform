package middleware

import (
	"net/http"
	"shop/auth/internal/domain"

	"github.com/labstack/echo/v4"
)

func AdminOnly(next echo.HandlerFunc) echo.HandlerFunc {
	return func(c echo.Context) error {
		role, ok := c.Get("role").(string)

		if !ok || role != domain.RoleAdmin.String() {
			return c.JSON(http.StatusForbidden, map[string]string{
				"error": "insufficient permissions",
			})
		}

		return next(c)
	}
}
