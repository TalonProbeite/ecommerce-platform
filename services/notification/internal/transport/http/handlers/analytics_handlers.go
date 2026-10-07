package handlers

import (
	"errors"
	"net/http"
	"shop/notification/internal/domain"
	"shop/notification/internal/services"
	"shop/notification/internal/transport/http/dto"
	"time"

	"github.com/labstack/echo/v4"
)

type AnalyticsHandlers struct {
	service *services.AnalyticsService
}

func NewAnalyticsHandlers(service *services.AnalyticsService) *AnalyticsHandlers {
	return &AnalyticsHandlers{
		service: service,
	}
}

func (h *AnalyticsHandlers) GetNotificationByID(c echo.Context) error {
	var req dto.GetNotificationByIDRequest

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, echo.Map{
			"error": "invalid request parameters",
		})
	}

	if err := c.Validate(&req); err != nil {
		return c.JSON(http.StatusBadRequest, echo.Map{
			"error": err.Error(),
		})
	}

	log, err := h.service.GetNotificationByID(
		c.Request().Context(),
		req.ID,
	)
	if err != nil {
		if errors.Is(err, domain.ErrNotificationNotFound) {
			return c.JSON(http.StatusNotFound, echo.Map{
				"error": err.Error(),
			})
		}

		return c.JSON(http.StatusBadRequest, echo.Map{
			"error": err.Error(),
		})
	}

	return c.JSON(http.StatusOK, log)
}

func (h *AnalyticsHandlers) GetNotificationsHistory(c echo.Context) error {
	var req dto.GetNotificationsHistoryRequest

	if err := c.Bind(&req); err != nil {
		return c.JSON(http.StatusBadRequest, echo.Map{
			"error": "invalid request parameters",
		})
	}

	if err := c.Validate(&req); err != nil {
		return c.JSON(http.StatusBadRequest, echo.Map{
			"error": err.Error(),
		})
	}

	filters := domain.HistoryFilters{
		Status:    req.Status,
		Channel:   req.Channel,
		Recipient: req.Recipient,
		Limit:     req.Limit,
		Offset:    req.Offset,
	}

	if req.StartDate != "" {
		startDate, err := time.Parse(time.RFC3339, req.StartDate)
		if err != nil {
			return c.JSON(http.StatusBadRequest, echo.Map{
				"error": "invalid start_date format, expected RFC3339",
			})
		}

		filters.StartDate = startDate
	}

	if req.EndDate != "" {
		endDate, err := time.Parse(time.RFC3339, req.EndDate)
		if err != nil {
			return c.JSON(http.StatusBadRequest, echo.Map{
				"error": "invalid end_date format, expected RFC3339",
			})
		}

		filters.EndDate = endDate
	}

	logs, err := h.service.GetNotificationsHistory(
		c.Request().Context(),
		&filters,
	)
	if err != nil {
		if errors.Is(err, domain.ErrInvalidFilters) {
			return c.JSON(http.StatusBadRequest, echo.Map{
				"error": err.Error(),
			})
		}

		return c.JSON(http.StatusInternalServerError, echo.Map{
			"error": err.Error(),
		})
	}

	return c.JSON(http.StatusOK, logs)
}
