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
) (domain.TokenPair, error) {
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
			return domain.TokenPair{}, fmt.Errorf(
				"registration failed: %w",
				domain.ErrEmailAlreadyExists,
			)
		}
		return domain.TokenPair{}, fmt.Errorf(
			"error while trying to save user: %w",
			err,
		)
	}

	if UserID == uuid.Nil {
		return domain.TokenPair{}, fmt.Errorf("user repository returned empty user id")
	}

	code, err := crypto.GenerateCode(10)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error generating confirmation code: %w",
			err,
		)
	}

	payload, err := json.Marshal(domain.UserRegisteredEvent{
		Email: userData.Email,
		Code:  code,
	})
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error while creating json struct for event: %w",
			err,
		)
	}

	err = as.publisher.PublishEvent(domain.UserRegistredEventKey, payload)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error while publishing event: %w",
			err,
		)
	}

	userID := UserID.String()

	tokens, err := as.CreateSession(
		ctx,
		userID,
		domain.RoleCustomer,
	)
	if err != nil {
		return domain.TokenPair{}, err
	}

	verKey := fmt.Sprintf("ver:%s", userID)

	err = as.sessRepo.SaveEntry(
		ctx,
		verKey,
		code,
		15*time.Minute,
	)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error saving verification code: %w",
			err,
		)
	}

	return tokens, nil
}

func (as *AuthService) Login(
	ctx context.Context,
	userData *dto.LoginRequest,
) (domain.TokenPair, error) {
	user, err := as.userRepo.GetByEmail(ctx, userData.Email)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error while searching for user: %w",
			err,
		)
	}

	if user == nil {
		return domain.TokenPair{}, fmt.Errorf("user not found error")
	}

	if user.Password == "" {
		return domain.TokenPair{}, fmt.Errorf("user registered via oauth")
	}

	if isValid := crypto.CheckPasswordHash(
		userData.Password,
		user.Password,
	); !isValid {
		return domain.TokenPair{}, fmt.Errorf(
			"incorrect password: %w",
			domain.ErrInvalidCredentials,
		)
	}

	status, err := as.userRepo.GetStatus(ctx, user.ID)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error checking user status: %w",
			err,
		)
	}

	if !status {
		return domain.TokenPair{}, fmt.Errorf("the user is not active")
	}

	return as.CreateSession(
		ctx,
		user.ID,
		user.Role,
	)
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

	email, name, err := as.userRepo.SetVerified(ctx, userID)
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

	payload, err := json.Marshal(domain.UserEmailVerifiedEvent{
		Email: email,
		Name:  name,
	})
	if err != nil {
		return fmt.Errorf(
			"error while creating json struct for event: %w",
			err,
		)
	}

	err = as.publisher.PublishEvent(
		domain.UserEmailVerifiedEventKey,
		payload,
	)
	if err != nil {
		return fmt.Errorf(
			"error while publishing verified event: %w",
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

	if session.AccessToken != access {
		return fmt.Errorf("access token does not belong to session")
	}

	if err := as.sessRepo.DeleteSession(ctx, session); err != nil {
		return fmt.Errorf(
			"error occurred while deleting auth session: %w",
			err,
		)
	}

	return nil
}

func (as *AuthService) Google(ctx context.Context) (string, error) {
	state, err := crypto.GenerateRandomToken(32)
	if err != nil {
		return "", fmt.Errorf("state generation error: %w", err)
	}

	stateKey := fmt.Sprintf("state:%s", state)

	err = as.sessRepo.SaveEntry(
		ctx,
		stateKey,
		"",
		15*time.Minute,
	)
	if err != nil {
		return "", fmt.Errorf(
			"error when saving state: %w",
			err,
		)
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
		var tokens domain.TokenPair

		tokens, err := as.CreateSession(
			ctx,
			user.ID,
			user.Role,
		)
		if err != nil {
			return domain.OAuthResult{}, err
		}

		return domain.OAuthResult{
			Tokens: &domain.TokenPair{
				AccessToken:  tokens.AccessToken,
				RefreshToken: tokens.RefreshToken,
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

func (as *AuthService) CompleteOAuthRegistration(
	ctx context.Context,
	userData domain.OAuthRegistrationData,
) (domain.TokenPair, error) {
	profKey := fmt.Sprintf(
		"oauth:pending:%s",
		userData.Key,
	)

	profileJSON, err := as.sessRepo.GetValue(ctx, profKey)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"when completing the profile: %w",
			err,
		)
	}

	var profile domain.OAuthProfile

	if err = json.Unmarshal(
		[]byte(profileJSON),
		&profile,
	); err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"deserialization error: %w",
			err,
		)
	}

	if userData.FirstName != "" {
		profile.FirstName = userData.FirstName
	}

	if userData.LastName != "" {
		profile.LastName = userData.LastName
	}

	userID, err := as.userRepo.CreateWithOauth(
		ctx,
		&domain.User{
			Email:          profile.Email,
			FirstName:      profile.FirstName,
			LastName:       profile.LastName,
			Phone:          userData.Phone,
			Provider:       profile.Provider,
			ProviderUserID: profile.ProviderUserID,
		},
	)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf(
			"error creating oauth user: %w",
			err,
		)
	}

	if err := as.sessRepo.DeleteEntry(ctx, profKey); err != nil {
		return domain.TokenPair{}, err
	}

	return as.CreateSession(
		ctx,
		userID.String(),
		domain.RoleCustomer,
	)
}

func (as *AuthService) ResendVerCode(
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

	payload, err := json.Marshal(domain.UserRegisteredEvent{
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
		domain.UserRegistredEventKey,
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
