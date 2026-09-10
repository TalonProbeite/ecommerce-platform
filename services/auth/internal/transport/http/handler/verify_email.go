package handler

import (
	"net/http"
	"shop/auth/internal/transport/http/dto"

	"github.com/labstack/echo/v4"
)

func (h *AuthHandler) VerifyEmail(c echo.Context) error {
	var req dto.VerifyEmailRequest

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{"error": "invalid code format"})
	}

	if err := req.Validate(); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{"error": "invalid code format"})
	}

	userID, ok := c.Get("UserID").(string)
	if !ok || userID == "" {
		return c.JSON(http.StatusUnauthorized, map[string]string{"error": "unauthorized request"})
	}

	if err := h.authService.VerifyEmail(c.Request().Context(), req.Code, userID); err != nil {
		return c.JSON(http.StatusUnprocessableEntity, map[string]string{"error": "invalid confirmation code"})
	}

	return c.JSON(http.StatusOK, map[string]string{"message": "email verification was successful"})
}
