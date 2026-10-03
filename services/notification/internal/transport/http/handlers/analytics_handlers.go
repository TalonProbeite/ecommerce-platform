package handlers

import (
	"errors"
	"net/http"
	"shop/notification/internal/domain"
	"shop/notification/internal/services"
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
	id := c.Param("id")
	if id == "" {
		return c.JSON(http.StatusBadRequest, echo.Map{"error": "id parameter is required"})
	}

	log, err := h.service.GetNotificationByID(c.Request().Context(), id)
	if err != nil {
		if errors.Is(err, domain.ErrNotificationNotFound) {
			return c.JSON(http.StatusNotFound, echo.Map{"error": err.Error()})
		}

		return c.JSON(http.StatusBadRequest, echo.Map{"error": err.Error()})
	}

	return c.JSON(http.StatusOK, log)
}

func (h *AnalyticsHandlers) GetNotificationsHistory(c echo.Context) error {
	filters := domain.HistoryFilters{
		Status:    c.QueryParam("status"),
		Channel:   c.QueryParam("channel"),
		Recipient: c.QueryParam("recipient"),
	}

	if startDateStr := c.QueryParam("start_date"); startDateStr != "" {
		parsedDate, err := time.Parse(time.RFC3339, startDateStr)
		if err != nil {
			return c.JSON(http.StatusBadRequest, echo.Map{"error": "invalid start_date format, expected RFC3339"})
		}
		filters.StartDate = parsedDate
	}

	if endDateStr := c.QueryParam("end_date"); endDateStr != "" {
		parsedDate, err := time.Parse(time.RFC3339, endDateStr)
		if err != nil {
			return c.JSON(http.StatusBadRequest, echo.Map{"error": "invalid end_date format, expected RFC3339"})
		}
		filters.EndDate = parsedDate
	}

	logs, err := h.service.GetNotificationsHistory(c.Request().Context(), &filters)
	if err != nil {
		return c.JSON(http.StatusInternalServerError, echo.Map{"error": err.Error()})
	}

	return c.JSON(http.StatusOK, logs)
}
