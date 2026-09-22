package application

import (
	"context"
	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"
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

func (ps *ProfileService) PatchUserProfile(
	ctx context.Context,
	userID string,
	userData *dto.PatchUser,
) error {
	email, firstName, lastName, phone := "", "", "", ""

	if userData.Email != nil {
		email = *userData.Email
	}
	if userData.FirstName != nil {
		firstName = *userData.FirstName
	}
	if userData.LastName != nil {
		lastName = *userData.LastName
	}
	if userData.Phone != nil {
		phone = *userData.Phone
	}

	return ps.userRepo.UpdateProfile(
		ctx,
		userID,
		email,
		firstName,
		lastName,
		phone,
	)
}
