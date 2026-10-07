package application

import (
	"context"
	"encoding/json"
	"fmt"
	"shop/auth/internal/domain"
	"shop/shared/events"
	"shop/shared/roles"
	"time"
)

type AdminService struct {
	userRepo  domain.AdminUserRepository
	sesRepo   domain.SessionRepository
	publisher domain.EventPublisher
}

func NewAdminService(
	us domain.AdminUserRepository,
	sr domain.SessionRepository,
	pb domain.EventPublisher,
) *AdminService {
	return &AdminService{
		userRepo:  us,
		sesRepo:   sr,
		publisher: pb,
	}
}

func (ads *AdminService) UpdateRole(ctx context.Context, userID, adminID string, newRole roles.Role) error {
	err := ads.userRepo.UpdateRole(ctx, userID, newRole)
	if err != nil {
		return fmt.Errorf("error updating role: %w", err)
	}

	err = ads.sesRepo.RevokeAllSessions(ctx, userID)
	if err != nil {
		return fmt.Errorf("error occurred while revoking all auth sessions: %w", err)
	}

	payload, err := json.Marshal(events.UserRoleChangedEvent{
		UserID:    userID,
		AdminID:   adminID,
		NewRole:   string(newRole),
		Timestamp: time.Now().Unix(),
	})
	if err != nil {
		return fmt.Errorf("error while creating json struct for event: %w", err)
	}

	err = ads.publisher.PublishEvent(events.RoutingKeyUserRoleChanged, payload)
	if err != nil {
		return fmt.Errorf("error while publishing event: %w", err)
	}

	return nil
}

func (ads *AdminService) BanUser(ctx context.Context, userID, adminID string, isBanned bool) error {
	err := ads.userRepo.UpdateBanStatus(ctx, userID, isBanned)
	if err != nil {
		return fmt.Errorf("error updating ban status: %w", err)
	}

	if isBanned {
		err = ads.sesRepo.RevokeAllSessions(ctx, userID)
		if err != nil {
			return fmt.Errorf("error occurred while revoking all auth sessions: %w", err)
		}
	}

	payload, err := json.Marshal(events.UserBannedEvent{
		UserID:    userID,
		AdminID:   adminID,
		IsBanned:  isBanned,
		Timestamp: time.Now().Unix(),
	})
	if err != nil {
		return fmt.Errorf("error while creating json struct for event: %w", err)
	}

	err = ads.publisher.PublishEvent(events.RoutingKeyUserBanned, payload)
	if err != nil {
		return fmt.Errorf("error while publishing event: %w", err)
	}

	return nil
}
