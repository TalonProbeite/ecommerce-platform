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

type AdminHandler struct {
	adminService *application.AdminService
	log          *slog.Logger
}

func NewAdminHandlers(adminService *application.AdminService, log *slog.Logger) *AdminHandler {
	return &AdminHandler{
		adminService: adminService,
		log:          log,
	}
}

func (ah *AdminHandler) UpdateRole(c echo.Context, adminID string) error {
	targetUserID := c.Param("id")
	var req dto.UpdateRoleReq

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{
			"error": "invalid request format",
		})
	}

	if err := c.Validate(&req); err != nil {
		return c.JSON(http.StatusUnprocessableEntity, map[string]string{
			"error": err.Error(),
		})
	}

	if err := ah.adminService.UpdateRole(
		c.Request().Context(),
		targetUserID,
		adminID,
		domain.Role(req.Role),
	); err != nil {
		switch {
		case errors.Is(err, domain.ErrUserNotFound):
			return c.JSON(http.StatusNotFound, map[string]string{
				"error": "user not found",
			})
		default:
			ah.log.Error(
				"failed to update user role",
				slog.String("err", err.Error()),
			)
			return c.JSON(http.StatusInternalServerError, map[string]string{
				"error": "internal server error",
			})
		}
	}

	return c.JSON(http.StatusOK, map[string]string{
		"message": "user role updated successfully",
	})
}

func (ah *AdminHandler) BanUser(c echo.Context, adminID string) error {
	targetUserID := c.Param("id")
	var req dto.BanUserReq

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, map[string]string{
			"error": "invalid request format",
		})
	}

	if err := c.Validate(&req); err != nil {
		return c.JSON(http.StatusUnprocessableEntity, map[string]string{
			"error": err.Error(),
		})
	}

	if err := ah.adminService.BanUser(
		c.Request().Context(),
		targetUserID,
		adminID,
		*req.IsBanned,
	); err != nil {
		switch {
		case errors.Is(err, domain.ErrUserNotFound):
			return c.JSON(http.StatusNotFound, map[string]string{
				"error": "user not found",
			})
		default:
			ah.log.Error(
				"failed to update ban status",
				slog.String("err", err.Error()),
			)
			return c.JSON(http.StatusInternalServerError, map[string]string{
				"error": "internal server error",
			})
		}
	}

	return c.JSON(http.StatusOK, map[string]string{
		"message": "user ban status updated successfully",
	})
}
