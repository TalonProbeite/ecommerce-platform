package handler

import (
	"errors"
	"log/slog"
	"net/http"
	"shop/auth/internal/application"
	"shop/auth/internal/domain"

	"github.com/labstack/echo/v4"
)

type ProfileHandler struct {
	profileService *application.ProfileService
	log            *slog.Logger
}

func NewProfileHandlers(profileService *application.ProfileService, log *slog.Logger) *ProfileHandler {
	return &ProfileHandler{
		profileService: profileService,
		log:            log,
	}
}

func (ph *ProfileHandler) GetUserProfile(c echo.Context, userID string) error {
	user, err := ph.profileService.GetUserProfile(c.Request().Context(), userID)
	if err != nil {
		switch {
		case errors.Is(err, domain.ErrUserNotActive):
			return c.JSON(http.StatusForbidden, map[string]string{"error": "account is not active"})

		case errors.Is(err, domain.ErrUserNotFound):
			return c.JSON(http.StatusUnauthorized, map[string]string{"error": "invalid user ID"})

		default:
			ph.log.Error("failed to get user profile", slog.String("err", err.Error()))
			return c.JSON(http.StatusInternalServerError, map[string]string{"error": "internal server error"})
		}
	}

	return c.JSON(http.StatusOK, user)
}
