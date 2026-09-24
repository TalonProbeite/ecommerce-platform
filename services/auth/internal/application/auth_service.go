package application

import (
	"context"
	"encoding/json/v2"
	"errors"
	"fmt"
	"shop/auth/internal/domain"
	"shop/auth/internal/infra/crypto"
	"shop/auth/internal/transport/http/dto"
	"time"

	"github.com/google/uuid"
	"github.com/jackc/pgx/v5/pgconn"
)

type AuthService struct {
	userRepo    domain.UserRepository
	sessRepo    domain.SessionRepository
	publisher   domain.EventPublisher
	tokenMng    domain.TokenManager
	oauthClient domain.GoogleClient
}

func NewAuthService(
	ur domain.UserRepository,
	sr domain.SessionRepository,
	ep domain.EventPublisher,
	tm domain.TokenManager,
	oa domain.GoogleClient,
) *AuthService {
	return &AuthService{
		userRepo: ur, sessRepo: sr, publisher: ep, tokenMng: tm, oauthClient: oa,
	}
}

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

	refresh, err := crypto.GenerateRandomToken(32)
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

func (as *AuthService) Login(ctx context.Context, userData *dto.LoginRequest) (domain.TokenPair, error) {
	user, err := as.userRepo.GetByEmail(ctx, userData.Email)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error while searching for user: %w", err)
	}

	if user == nil {
		return domain.TokenPair{}, fmt.Errorf("user not found error")
	}
	if user.Password == "" {
		return domain.TokenPair{}, fmt.Errorf("user registered via oauth")
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

	refresh, err := crypto.GenerateRandomToken(32)
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

func (as *AuthService) VerifyEmail(ctx context.Context, userCode, userID string) error {
	verKey := fmt.Sprintf("ver:%s", userID)
	code, err := as.sessRepo.GetValue(ctx, verKey)
	if err != nil {
		return fmt.Errorf("error when trying to get email confirmation code: %w", err)
	}

	if code != userCode {
		return fmt.Errorf("invalid verification code")
	}

	email, name, err := as.userRepo.SetVerified(ctx, userID)
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

	refreshNew, err := crypto.GenerateRandomToken(32)
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

func (as *AuthService) Google(ctx context.Context) (string, error) {
	state, err := crypto.GenerateRandomToken(32)
	if err != nil {
		return "", fmt.Errorf("state generation error: %w", err)
	}
	stateKey := fmt.Sprintf("state:%s", state)
	err = as.sessRepo.SaveEntry(ctx, stateKey, "", 15*time.Minute)
	if err != nil {
		return "", fmt.Errorf("error when saving state: %w", err)
	}

	url := as.oauthClient.AuthURL(state)

	return url, nil
}

func (as *AuthService) GoogleCallback(
	ctx context.Context,
	state, code string,
) (domain.OAuthResult, error) {
	stateKey := fmt.Sprintf("state:%s", state)

	_, err := as.sessRepo.GetValue(ctx, stateKey)
	if err != nil {
		return domain.OAuthResult{}, fmt.Errorf(
			"error when searching for state: %w",
			err,
		)
	}

	if err = as.sessRepo.DeleteEntry(ctx, stateKey); err != nil {
		return domain.OAuthResult{}, fmt.Errorf(
			"error deleting used state: %w",
			err,
		)
	}

	prof, err := as.oauthClient.GetProfile(ctx, code)
	if err != nil {
		return domain.OAuthResult{}, fmt.Errorf(
			"error when retrieving profile: %w",
			err,
		)
	}

	user, err := as.userRepo.GetByOAuth(
		ctx,
		domain.ProviderGoogle.String(),
		prof.ProviderUserID,
	)
	if err != nil && !errors.Is(err, domain.ErrUserNotFound) {
		return domain.OAuthResult{}, fmt.Errorf(
			"error verifying user: %w",
			err,
		)
	}

	if user != nil {
		var refresh, access string

		refresh, err = crypto.GenerateRandomToken(32)
		if err != nil {
			return domain.OAuthResult{}, fmt.Errorf(
				"error creating refresh token: %w",
				err,
			)
		}

		access, err = as.tokenMng.GenerateToken(
			user.ID,
			user.Role,
		)
		if err != nil {
			return domain.OAuthResult{}, fmt.Errorf(
				"access key generation error: %w",
				err,
			)
		}

		refKey := fmt.Sprintf("refresh:%s", refresh)
		acKey := fmt.Sprintf("access:%s", access)

		if err = as.sessRepo.SaveEntry(
			ctx,
			refKey,
			user.ID,
			7*24*time.Hour,
		); err != nil {
			return domain.OAuthResult{}, fmt.Errorf(
				"error saving refresh token: %w",
				err,
			)
		}

		if err = as.sessRepo.SaveEntry(
			ctx,
			acKey,
			user.ID,
			15*time.Minute,
		); err != nil {
			return domain.OAuthResult{}, fmt.Errorf(
				"error saving access token: %w",
				err,
			)
		}

		return domain.OAuthResult{
			Tokens: &domain.TokenPair{
				AccessToken:  access,
				RefreshToken: refresh,
			},
		}, nil
	}

	secret, err := crypto.GenerateRandomToken(32)
	if err != nil {
		return domain.OAuthResult{}, fmt.Errorf(
			"error generating key for profile: %w",
			err,
		)
	}

	profKey := fmt.Sprintf("oauth:pending:%s", secret)

	profJSON, err := json.Marshal(prof)
	if err != nil {
		return domain.OAuthResult{}, fmt.Errorf(
			"user profile serialization error: %w",
			err,
		)
	}

	if err := as.sessRepo.SaveEntry(
		ctx,
		profKey,
		string(profJSON),
		15*time.Minute,
	); err != nil {
		return domain.OAuthResult{}, fmt.Errorf(
			"profile save error: %w",
			err,
		)
	}

	return domain.OAuthResult{
		RegistrationKey: secret,
	}, nil
}

func (as *AuthService) CompleteOAuthRegistration(ctx context.Context, userData domain.OAuthRegistrationData) (domain.TokenPair, error) {
	profKey := fmt.Sprintf("oauth:pending:%s", userData.Key)
	profileJSON, err := as.sessRepo.GetValue(ctx, profKey)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("when completing the profile: %w", err)
	}
	var profile domain.OAuthProfile

	if err = json.Unmarshal([]byte(profileJSON), &profile); err != nil {
		return domain.TokenPair{}, fmt.Errorf("deserialization error: %w", err)
	}

	if userData.FirstName != "" {
		profile.FirstName = userData.FirstName
	}
	if userData.LastName != "" {
		profile.LastName = userData.LastName
	}

	userID, err := as.userRepo.CreateWithOauth(ctx,
		&domain.User{
			Email:          profile.Email,
			FirstName:      profile.FirstName,
			LastName:       profile.LastName,
			Phone:          userData.Phone,
			Provider:       profile.Provider,
			ProviderUserID: profile.ProviderUserID,
		})
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error creating oauth user: %w", err)
	}
	//nolint:errcheck // pending entry has TTL, deletion failure is non-critical
	as.sessRepo.DeleteEntry(ctx, profKey)
	refresh, err := crypto.GenerateRandomToken(32)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error creating refresh token: %w", err)
	}
	access, err := as.tokenMng.GenerateToken(userID.String(), domain.RoleCustomer)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("access key generation error: %w", err)
	}

	refKey := fmt.Sprintf("refresh:%s", refresh)
	acKey := fmt.Sprintf("access:%s", access)

	err = as.sessRepo.SaveEntry(ctx, refKey, userID.String(), 7*24*time.Hour)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving refresh token: %w", err)
	}

	err = as.sessRepo.SaveEntry(ctx, acKey, userID.String(), 15*time.Minute)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving access token: %w", err)
	}

	return domain.TokenPair{AccessToken: access, RefreshToken: refresh}, nil
}

func (as *AuthService) ResendVerCode(ctx context.Context, userID string) error {
	key := fmt.Sprintf("ver:%s", userID)

	code, err := crypto.GenerateCode(10)
	if err != nil {
		return fmt.Errorf("failed to generate code: %w", err)
	}

	err = as.sessRepo.SaveEntry(ctx, key, code, 15*time.Minute)
	if err != nil {
		return fmt.Errorf("failed to save code: %w", err)
	}

	email, err := as.userRepo.GetEmailByUserID(ctx, userID)
	if err != nil {
		return fmt.Errorf("failed to receive email: %w", err)
	}

	payload, err := json.Marshal(domain.UserRegisteredEvent{
		Email: email,
		Code:  code,
	})
	if err != nil {
		return fmt.Errorf("error while creating json struct for event: %w", err)
	}

	err = as.publisher.PublishEvent(domain.UserRegistredEventKey, payload)
	if err != nil {
		return fmt.Errorf("error while publishing event: %w", err)
	}

	return nil
}
