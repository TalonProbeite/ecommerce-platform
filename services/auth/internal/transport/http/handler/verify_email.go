package handler

import (
	"net/http"
	"shop/auth/internal/transport/http/dto"

	"github.com/labstack/echo/v4"
)

func (h *AuthHandler) VerifyEmail(c echo.Context, userID string) error {
	var req dto.VerifyEmailRequest

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{
			"error": "invalid code format",
		})
	}

	if err := req.Validate(); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{
			"error": "invalid code format",
		})
	}

	if err := h.authService.VerifyEmail(
		c.Request().Context(),
		req.Code,
		userID,
	); err != nil {
		return c.JSON(http.StatusUnprocessableEntity, map[string]string{
			"error": "invalid confirmation code",
		})
	}

	return c.JSON(http.StatusOK, map[string]string{
		"message": "email verification was successful",
	})
}

func (h *AuthHandler) SendVerificationEmail(c echo.Context, userID string) error {
	if err := h.authService.SendVerificationEmail(
		c.Request().Context(),
		userID,
	); err != nil {
		return c.JSON(http.StatusUnprocessableEntity, map[string]string{
			"error": "failed to send verification email",
		})
	}

	return c.JSON(http.StatusOK, map[string]string{
		"message": "verification email sent successfully",
	})
}
