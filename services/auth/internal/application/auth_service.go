package application

import (
	"context"
	"encoding/json/v2"
	"errors"
	"fmt"
	"shop/auth/internal/domain"
	"shop/auth/internal/infra/crypto"
	"shop/auth/internal/transport/http/dto"
	"shop/shared/events"
	"shop/shared/roles"
	"time"

	"github.com/google/uuid"
	"github.com/jackc/pgx/v5/pgconn"
)

type AuthService struct {
	userRepo    domain.AuthUserRepository
	sessRepo    domain.SessionRepository
	publisher   domain.EventPublisher
	tokenMng    domain.TokenManager
	oauthClient domain.GoogleClient
}

func NewAuthService(
	ur domain.AuthUserRepository,
	sr domain.SessionRepository,
	ep domain.EventPublisher,
	tm domain.TokenManager,
	oa domain.GoogleClient,
) *AuthService {
	return &AuthService{
		userRepo: ur, sessRepo: sr, publisher: ep, tokenMng: tm, oauthClient: oa,
	}
}

func (as *AuthService) Registration(
	ctx context.Context,
	userData *dto.RegisterRequest,
) (domain.TokenPair, domain.UserProfile, error) {
	hashPassword, err := crypto.HashPassword(userData.Password)
	if err != nil {
		return domain.TokenPair{}, domain.UserProfile{}, fmt.Errorf("error while hashing password: %w", err)
	}

	userID, err := as.userRepo.Create(ctx, &domain.User{
		Email:           userData.Email,
		Password:        hashPassword,
		Role:            roles.RoleCustomer,
		IsActive:        true,
		IsEmailVerified: false,
		FirstName:       userData.FirstName,
		LastName:        userData.LastName,
		Phone:           userData.Phone,
	})
	if err != nil {
		var pgErr *pgconn.PgError
		if errors.As(err, &pgErr) && pgErr.Code == "23505" {
			return domain.TokenPair{}, domain.UserProfile{}, fmt.Errorf(
				"registration failed: %w",
				domain.ErrEmailAlreadyExists,
			)
		}
		return domain.TokenPair{}, domain.UserProfile{}, fmt.Errorf(
			"error while trying to save user: %w",
			err,
		)
	}

	if userID == uuid.Nil {
		return domain.TokenPair{}, domain.UserProfile{}, fmt.Errorf("user repository returned empty user id")
	}

	if welcomeErr := as.publishWelcomeEmail(userData.Email, userData.FirstName); welcomeErr != nil {
		return domain.TokenPair{}, domain.UserProfile{}, welcomeErr
	}

	userIDString := userID.String()

	tokens, err := as.CreateSession(
		ctx,
		userIDString,
		roles.RoleCustomer,
	)
	if err != nil {
		return domain.TokenPair{}, domain.UserProfile{}, err
	}

	return tokens, domain.UserProfile{
		Email:     userData.Email,
		FirstName: userData.FirstName,
		LastName:  userData.LastName,
		Phone:     userData.Phone,
		Role:      roles.RoleCustomer,
	}, nil
}

func (as *AuthService) Login(
	ctx context.Context,
	userData *dto.LoginRequest,
) (domain.TokenPair, domain.UserProfile, error) {
	user, err := as.userRepo.GetByEmail(ctx, userData.Email)
	if err != nil {
		return domain.TokenPair{}, domain.UserProfile{}, fmt.Errorf(
			"error while searching for user: %w",
			err,
		)
	}

	if user == nil {
		return domain.TokenPair{}, domain.UserProfile{}, fmt.Errorf("user not found error")
	}

	if user.Password == "" {
		return domain.TokenPair{}, domain.UserProfile{}, fmt.Errorf("user registered via oauth")
	}

	if isValid := crypto.CheckPasswordHash(
		userData.Password,
		user.Password,
	); !isValid {
		return domain.TokenPair{}, domain.UserProfile{}, fmt.Errorf(
			"incorrect password: %w",
			domain.ErrInvalidCredentials,
		)
	}

	status, err := as.userRepo.GetStatus(ctx, user.ID)
	if err != nil {
		return domain.TokenPair{}, domain.UserProfile{}, fmt.Errorf(
			"error checking user status: %w",
			err,
		)
	}

	if !status {
		return domain.TokenPair{}, domain.UserProfile{}, domain.ErrUserNotActive
	}

	tokens, err := as.CreateSession(ctx, user.ID, user.Role)
	if err != nil {
		return domain.TokenPair{}, domain.UserProfile{}, err
	}

	return tokens, domain.UserProfile{
		Email:      user.Email,
		FirstName:  user.FirstName,
		LastName:   user.LastName,
		Phone:      user.Phone,
		Role:       user.Role,
		IsVerified: user.IsEmailVerified,
	}, nil
}

func (as *AuthService) VerifyEmail(
	ctx context.Context,
	userCode, userID string,
) error {
	verKey := fmt.Sprintf("ver:%s", userID)

	code, err := as.sessRepo.GetValue(ctx, verKey)
	if err != nil {
		return fmt.Errorf(
			"error when trying to get email confirmation code: %w",
			err,
		)
	}

	if code != userCode {
		return fmt.Errorf("invalid verification code")
	}

	_, _, err = as.userRepo.SetVerified(ctx, userID)
	if err != nil {
		return fmt.Errorf(
			"error updating email confirmation field: %w",
			err,
		)
	}

	err = as.sessRepo.DeleteEntry(ctx, verKey)
	if err != nil {
		return fmt.Errorf(
			"error deleting verification code from redis: %w",
			err,
		)
	}

	return nil
}

func (as *AuthService) Refresh(
	ctx context.Context,
	refresh string,
) (domain.TokenPair, error) {
	session, err := as.sessRepo.GetSessionByRefreshToken(
		ctx,
		refresh,
	)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error searching for refresh session: %w",
			err,
		)
	}

	user, err := as.userRepo.GetByID(ctx, session.UserID)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error searching for user: %w",
			err,
		)
	}

	if user == nil {
		return domain.TokenPair{}, fmt.Errorf("user not found error")
	}

	if !user.IsActive {
		return domain.TokenPair{}, fmt.Errorf("the user is not active")
	}

	oldAccessToken := session.AccessToken
	oldRefreshToken := session.RefreshToken

	newRefreshToken, err := crypto.GenerateRandomToken(32)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error creating refresh token: %w",
			err,
		)
	}

	newAccessToken, err := as.tokenMng.GenerateToken(
		session.UserID,
		user.Role,
	)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"access key generation error: %w",
			err,
		)
	}

	session.AccessToken = newAccessToken
	session.RefreshToken = newRefreshToken

	err = as.sessRepo.UpdateSessionTokens(
		ctx,
		session,
		oldAccessToken,
		oldRefreshToken,
		15*time.Minute,
		7*24*time.Hour,
	)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error updating auth session: %w",
			err,
		)
	}

	return domain.TokenPair{
		AccessToken:  newAccessToken,
		RefreshToken: newRefreshToken,
	}, nil
}

func (as *AuthService) Logout(
	ctx context.Context,
	refresh, access string,
) error {
	session, err := as.sessRepo.GetSessionByRefreshToken(
		ctx,
		refresh,
	)
	if err != nil {
		return fmt.Errorf(
			"error searching for session: %w",
			err,
		)
	}

	if access != "" && session.AccessToken != access {
		return fmt.Errorf("access token does not belong to session")
	}

	err = as.sessRepo.DeleteSession(ctx, session)
	if err != nil {
		return fmt.Errorf(
			"error occurred while deleting auth session: %w",
			err,
		)
	}

	return nil
}

func (as *AuthService) SendVerificationEmail(
	ctx context.Context,
	userID string,
) error {
	key := fmt.Sprintf("ver:%s", userID)

	code, err := crypto.GenerateCode(10)
	if err != nil {
		return fmt.Errorf(
			"failed to generate code: %w",
			err,
		)
	}

	err = as.sessRepo.SaveEntry(
		ctx,
		key,
		code,
		15*time.Minute,
	)
	if err != nil {
		return fmt.Errorf(
			"failed to save code: %w",
			err,
		)
	}

	email, err := as.userRepo.GetEmailByUserID(ctx, userID)
	if err != nil {
		return fmt.Errorf(
			"failed to receive email: %w",
			err,
		)
	}

	payload, err := json.Marshal(events.EmailVerificationRequestedEvent{
		Email: email,
		Code:  code,
	})
	if err != nil {
		return fmt.Errorf(
			"error while creating json struct for event: %w",
			err,
		)
	}

	err = as.publisher.PublishEvent(
		events.EmailVerificationRequestedEventKey,
		payload,
	)
	if err != nil {
		return fmt.Errorf(
			"error while publishing event: %w",
			err,
		)
	}

	return nil
}
