package services

import (
	"context"
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/repository"
)

type AnalyticsService struct {
	repo *repository.EventRepo
}

func NewAnalyticsService(repo *repository.EventRepo) *AnalyticsService {
	return &AnalyticsService{
		repo: repo,
	}
}

func (s *AnalyticsService) GetNotificationByID(ctx context.Context, id string) (domain.EventLog, error) {
	if id == "" {
		return domain.EventLog{}, domain.ErrInvalidFilters
	}

	return s.repo.GetByID(ctx, id)
}

func (s *AnalyticsService) GetNotificationsHistory(ctx context.Context, filters *domain.HistoryFilters) ([]domain.EventLog, error) {
	if filters == nil {
		return nil, domain.ErrInvalidFilters
	}

	if !filters.StartDate.IsZero() && !filters.EndDate.IsZero() {
		if filters.StartDate.After(filters.EndDate) {
			return nil, domain.ErrInvalidFilters
		}
	}

	return s.repo.GetHistory(ctx, filters)
}
