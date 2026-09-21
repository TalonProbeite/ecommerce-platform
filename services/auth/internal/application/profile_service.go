package application

import (
	"context"
	"shop/auth/internal/domain"
)

type ProfileService struct {
	userRepo  domain.ProfileUserRepository
	sessRepo  domain.SessionRepository
	publisher domain.EventPublisher
}

func NewProfileService(
	us domain.ProfileUserRepository,
	sr domain.SessionRepository,
	ep domain.EventPublisher,
) *ProfileService {
	return &ProfileService{
		userRepo: us, sessRepo: sr, publisher: ep,
	}
}

func (ps *ProfileService) GetUserProfile(ctx context.Context, userID string) (domain.UserProfile, error) {
	user, err := ps.userRepo.GetByIDProfile(ctx, userID)
	if err != nil {
		return domain.UserProfile{}, domain.ErrUserNotFound
	}
	if !user.IsActive {
		return domain.UserProfile{}, domain.ErrUserNotActive
	}
	return domain.UserProfile{
		Email:     user.Email,
		FirstName: user.FirstName,
		LastName:  user.LastName,
		Phone:     user.Phone,
	}, nil
}
