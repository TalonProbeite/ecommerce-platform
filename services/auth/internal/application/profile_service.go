package application

import (
	"context"
	"encoding/json/v2"
	"fmt"
	"shop/auth/internal/domain"
	"shop/auth/internal/infra/crypto"
	"shop/auth/internal/transport/http/dto"
	"time"
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

	err := ps.userRepo.UpdateProfile(
		ctx,
		userID,
		email,
		firstName,
		lastName,
		phone,
	)
	if err != nil {
		return fmt.Errorf("error updating profile: %w", err)
	}

	if email != "" {
		code, err := crypto.GenerateCode(10)
		if err != nil {
			return fmt.Errorf("error generating confirmation code: %w", err)
		}

		verKey := fmt.Sprintf("ver:%s", userID)
		err = ps.sessRepo.SaveEntry(ctx, verKey, code, 15*time.Minute)
		if err != nil {
			return fmt.Errorf("error saving verification code: %w", err)
		}

		payload, err := json.Marshal(domain.UserRegisteredEvent{
			Email: email,
			Code:  code,
		})
		if err != nil {
			return fmt.Errorf("error while creating json struct for event: %w", err)
		}

		err = ps.publisher.PublishEvent(domain.UserRegistredEventKey, payload)
		if err != nil {
			return fmt.Errorf("error while publishing event: %w", err)
		}
	}

	return nil
}

func (ps *ProfileService) ResetPassword(ctx context.Context, userID, oldPass, newPass, refresh, access string) error {
	pass, err := ps.userRepo.GetPassByUserID(ctx, userID)
	if err != nil {
		return fmt.Errorf("error while searching for the password: %w", err)
	}

	if !crypto.CheckPasswordHash(oldPass, pass) {
		return domain.ErrInvalidCredentials
	}

	hashedNewPass, err := crypto.HashPassword(newPass)
	if err != nil {
		return fmt.Errorf("error while hashing new password: %w", err)
	}

	err = ps.userRepo.ResetPassword(ctx, userID, hashedNewPass)
	if err != nil {
		return fmt.Errorf("failed to change password: %w", err)
	}

	session, err := ps.sessRepo.GetSessionByRefreshToken(
		ctx,
		refresh,
	)
	if err != nil {
		return fmt.Errorf(
			"error searching for session: %w",
			err,
		)
	}

	if session.AccessToken != access {
		return fmt.Errorf("access token does not belong to session")
	}

	if err := ps.sessRepo.RevokeAllSessions(ctx, userID); err != nil {
		return fmt.Errorf(
			"error occurred while revoking all auth sessions: %w",
			err,
		)
	}

	return nil
}
