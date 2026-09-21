package handler

import (
	"net/http"

	"github.com/labstack/echo/v4"
)

type UserIDHandlerFunc func(c echo.Context, userID string) error

func WithUserID(h UserIDHandlerFunc) echo.HandlerFunc {
	return func(c echo.Context) error {
		userID, ok := c.Get("UserID").(string)
		if !ok || userID == "" {
			return c.JSON(http.StatusUnauthorized, map[string]string{
				"error": "unauthorized",
			})
		}
		return h(c, userID)
	}
}
