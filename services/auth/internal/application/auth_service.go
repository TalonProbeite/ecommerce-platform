// Package application provides business logic services for authentication and account operations.
package application

import (
	"context"
	"encoding/json/v2"
	"errors"
	"fmt"
	"time"

	"shop/auth/internal/domain"
	"shop/auth/internal/infra/crypto"
	"shop/auth/internal/transport/http/dto"

	"github.com/google/uuid"
	"github.com/jackc/pgx/v5/pgconn"
)

// AuthService handles authentication logic, tokens, and registration.
type AuthService struct {
	userRepo  domain.UserRepository
	sessRepo  domain.SessionRepository
	publisher domain.EventPublisher
	tokenMng  domain.TokenManager
}

// NewAuthService constructs a new AuthService instance.
func NewAuthService(
	ur domain.UserRepository,
	sr domain.SessionRepository,
	ep domain.EventPublisher,
	tm domain.TokenManager,
) *AuthService {
	return &AuthService{
		userRepo: ur, sessRepo: sr, publisher: ep, tokenMng: tm,
	}
}

// Registration handles user creation, event publishing, and initial session generation.
func (as *AuthService) Registration(ctx context.Context, userData *dto.RegisterRequest) (domain.TokenPair, error) {
	hashPassword, err := crypto.HashPassword(userData.Password)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error while hashing password: %w", err)
	}
	UserID, err := as.userRepo.Create(ctx, &domain.User{
		Email:           userData.Email,
		Password:        hashPassword,
		Role:            domain.RoleCustomer,
		IsActive:        true,
		IsEmailVerified: false,
		FirstName:       userData.FirstName,
		LastName:        userData.LastName,
		Phone:           userData.Phone,
	})
	if err != nil {
		var pgErr *pgconn.PgError
		if errors.As(err, &pgErr) && pgErr.Code == "23505" {
			return domain.TokenPair{}, fmt.Errorf("registration failed: %w", domain.ErrEmailAlreadyExists)
		}
		return domain.TokenPair{}, fmt.Errorf("error while trying to save user: %w", err)
	}

	if UserID == uuid.Nil {
		return domain.TokenPair{}, fmt.Errorf("user repository returned empty user id")
	}

	code, err := crypto.GenerateCode(10)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error generating confirmation code: %w", err)
	}
	payload, err := json.Marshal(domain.UserRegisteredEvent{
		Email: userData.Email,
		Code:  code,
	})
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error while creating json struct for event: %w", err)
	}

	err = as.publisher.PublishEvent(domain.UserRegistredEventKey, payload)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error while publishing event: %w", err)
	}

	refresh, err := crypto.GenerateRefreshToken()
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error creating refresh token: %w", err)
	}
	UserIDString := UserID.String()
	access, err := as.tokenMng.GenerateToken(UserIDString, domain.RoleCustomer)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("access key generation error: %w", err)
	}

	refKey := fmt.Sprintf("refresh:%s", refresh)
	acKey := fmt.Sprintf("access:%s", access)
	verKey := fmt.Sprintf("ver:%s", UserIDString)

	err = as.sessRepo.SaveEntry(ctx, refKey, UserIDString, 7*24*time.Hour)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving refresh token: %w", err)
	}

	err = as.sessRepo.SaveEntry(ctx, acKey, UserIDString, 15*time.Minute)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving access token: %w", err)
	}

	err = as.sessRepo.SaveEntry(ctx, verKey, code, 15*time.Minute)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving verification code: %w", err)
	}

	return domain.TokenPair{AccessToken: access, RefreshToken: refresh}, nil
}

// Login authenticates user credentials and issues tokens.
func (as *AuthService) Login(ctx context.Context, userData *dto.LoginRequest) (domain.TokenPair, error) {
	user, err := as.userRepo.GetByEmail(ctx, userData.Email)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error while searching for user: %w", err)
	}

	if user == nil {
		return domain.TokenPair{}, fmt.Errorf("user not found error")
	}

	if isValid := crypto.CheckPasswordHash(userData.Password, user.Password); !isValid {
		return domain.TokenPair{}, fmt.Errorf("incorrect password")
	}

	status, err := as.userRepo.GetStatus(ctx, user.ID)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error checking user status: %w", err)
	}
	if !status {
		return domain.TokenPair{}, fmt.Errorf("the user is not active")
	}

	access, err := as.tokenMng.GenerateToken(user.ID, user.Role)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("access key generation error: %w", err)
	}

	refresh, err := crypto.GenerateRefreshToken()
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error creating refresh token: %w", err)
	}

	refKey := fmt.Sprintf("refresh:%s", refresh)
	acKey := fmt.Sprintf("access:%s", access)

	err = as.sessRepo.SaveEntry(ctx, refKey, user.ID, 7*24*time.Hour)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving refresh token: %w", err)
	}

	err = as.sessRepo.SaveEntry(ctx, acKey, user.ID, 15*time.Minute)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving access token: %w", err)
	}

	return domain.TokenPair{AccessToken: access, RefreshToken: refresh}, nil
}

// VerifyEmail verifies the email confirmation code for a user.
func (as *AuthService) VerifyEmail(ctx context.Context, userCode, UserID string) error {
	verKey := fmt.Sprintf("ver:%s", UserID)
	code, err := as.sessRepo.GetValue(ctx, verKey)
	if err != nil {
		return fmt.Errorf("error when trying to get email confirmation code: %w", err)
	}

	if code != userCode {
		return fmt.Errorf("invalid verification code")
	}

	email, name, err := as.userRepo.SetVerified(ctx, UserID)
	if err != nil {
		return fmt.Errorf("error updating email confirmation field: %w", err)
	}

	err = as.sessRepo.DeleteEntry(ctx, verKey)
	if err != nil {
		return fmt.Errorf("error deleting verification code from redis: %w", err)
	}

	payload, err := json.Marshal(domain.UserEmailVerifiedEvent{
		Email: email,
		Name:  name,
	})
	if err != nil {
		return fmt.Errorf("error while creating json struct for event: %w", err)
	}

	err = as.publisher.PublishEvent(domain.UserEmailVerifiedEventKey, payload)
	if err != nil {
		return fmt.Errorf("error while publishing verified event: %w", err)
	}

	return nil
}

// Refresh handles token rotation using a valid refresh token.
func (as *AuthService) Refresh(ctx context.Context, refresh string) (domain.TokenPair, error) {
	refKey := fmt.Sprintf("refresh:%s", refresh)
	UserIDRedis, err := as.sessRepo.GetValue(ctx, refKey)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error searching for key: %w", err)
	}

	user, err := as.userRepo.GetByID(ctx, UserIDRedis)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error searching for user: %w", err)
	}

	if user == nil {
		return domain.TokenPair{}, fmt.Errorf("user not found error")
	}

	if !user.IsActive {
		return domain.TokenPair{}, fmt.Errorf("the user is not active")
	}

	err = as.sessRepo.DeleteEntry(ctx, refKey)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error deleting stale session: %w", err)
	}

	refreshNew, err := crypto.GenerateRefreshToken()
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error creating refresh token: %w", err)
	}

	access, err := as.tokenMng.GenerateToken(UserIDRedis, user.Role)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("access key generation error: %w", err)
	}

	newRefreshKey := fmt.Sprintf("refresh:%s", refreshNew)
	newAccesKey := fmt.Sprintf("access:%s", access)

	err = as.sessRepo.SaveEntry(ctx, newRefreshKey, UserIDRedis, 7*24*time.Hour)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving refresh token: %w", err)
	}

	err = as.sessRepo.SaveEntry(ctx, newAccesKey, UserIDRedis, 15*time.Minute)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving access token: %w", err)
	}

	return domain.TokenPair{AccessToken: access, RefreshToken: refreshNew}, nil
}

// Logout revokes access and refresh token sessions.
func (as *AuthService) Logout(ctx context.Context, refresh, access string) error {
	refreshKey := fmt.Sprintf("refresh:%s", refresh)
	accesKey := fmt.Sprintf("access:%s", access)

	err := as.sessRepo.DeleteEntry(ctx, refreshKey)
	if err != nil {
		return fmt.Errorf("error occurred while attempting to delete a refresh token: %w", err)
	}
	err = as.sessRepo.DeleteEntry(ctx, accesKey)
	if err != nil {
		return fmt.Errorf("error occurred while attempting to delete a access token: %w", err)
	}

	return nil
}
