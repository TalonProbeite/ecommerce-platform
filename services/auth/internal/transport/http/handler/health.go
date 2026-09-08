// Package handler provides HTTP request handlers for the auth service.
package handler

import (
	"net/http"

	"github.com/labstack/echo/v4"
)

// HealthHandler handles health check endpoint requests.
type HealthHandler struct{}

// NewHealthHandler constructs a new HealthHandler.
func NewHealthHandler() *HealthHandler {
	return &HealthHandler{}
}

// Check returns HTTP 200 OK for liveness probe.
func (h *HealthHandler) Check(c echo.Context) error {
	return c.JSON(http.StatusOK, map[string]string{
		"status": "ok",
	})
}
