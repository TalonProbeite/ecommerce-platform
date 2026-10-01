package services

import (
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
