package handler

import (
	"errors"
	"log/slog"
	"net/http"
	"shop/auth/internal/application"
	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"

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

func (ph *ProfileHandler) PatchUserProfile(c echo.Context, userID string) error {
	var req dto.PatchUser

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{
			"error": "invalid request format",
		})
	}

	if err := req.Validate(); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{
			"error": err.Error(),
		})
	}

	if err := ph.profileService.PatchUserProfile(
		c.Request().Context(),
		userID,
		&req,
	); err != nil {
		switch {
		case errors.Is(err, domain.ErrEmailLockedByOAuth):
			return c.JSON(http.StatusForbidden, map[string]string{
				"error": "email cannot be changed for oauth account",
			})
		case errors.Is(err, domain.ErrUserNotFound):
			return c.JSON(http.StatusUnauthorized, map[string]string{
				"error": "invalid user ID",
			})
		default:
			ph.log.Error(
				"failed to patch user profile",
				slog.String("err", err.Error()),
			)
			return c.JSON(http.StatusInternalServerError, map[string]string{
				"error": "internal server error",
			})
		}
	}

	return c.JSON(http.StatusOK, map[string]string{
		"message": "profile updated successfully",
	})
}

func (ph *ProfileHandler) ResetPassword(c echo.Context, userID string) error {
	var req dto.ResetPass

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{
			"error": "invalid request format",
		})
	}

	if err := c.Validate(&req); err != nil {
		return c.JSON(http.StatusUnprocessableEntity, map[string]string{"error": err.Error()})
	}

	var access, refresh string
	if accessCookie, err := c.Cookie("access_token"); err == nil {
		access = accessCookie.Value
	}
	if refreshCookie, err := c.Cookie("refresh_token"); err == nil {
		refresh = refreshCookie.Value
	}

	err := ph.profileService.ResetPassword(c.Request().Context(), userID, req.OldPass, req.NewPass, refresh, access)
	if err != nil {
		if errors.Is(err, domain.ErrInvalidCredentials) {
			return c.JSON(http.StatusUnauthorized, map[string]string{"error": "invalid old password"})
		}
		ph.log.Error("failed to reset password", slog.String("err", err.Error()))
		return c.JSON(http.StatusInternalServerError, map[string]string{"error": "internal server error"})
	}

	c.SetCookie(&http.Cookie{
		Name:     "access_token",
		Value:    "",
		Path:     "/",
		MaxAge:   -1,
		HttpOnly: true,
		Secure:   true,
		SameSite: http.SameSiteStrictMode,
	})

	c.SetCookie(&http.Cookie{
		Name:     "refresh_token",
		Value:    "",
		Path:     "/",
		MaxAge:   -1,
		HttpOnly: true,
		Secure:   true,
		SameSite: http.SameSiteStrictMode,
	})

	return c.JSON(http.StatusOK, map[string]string{
		"message": "password has been successfully reset",
	})
}
