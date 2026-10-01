package handlers

import "shop/notification/internal/services"

type AnalyticsHandlers struct {
	service *services.AnalyticsService
}

func NewAnalyticsHandlers(service *services.AnalyticsService) *AnalyticsHandlers {
	return &AnalyticsHandlers{
		service: service,
	}
}
