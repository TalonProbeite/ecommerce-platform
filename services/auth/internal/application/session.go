// internal/application/session_service.go

package application

import (
	"context"
	"fmt"
	"shop/auth/internal/domain"
	"shop/auth/internal/infra/crypto"
	"time"
)

const (
	accessTokenTTL   = 15 * time.Minute
	refreshTokenTTL  = 7 * 24 * time.Hour
	sessionIDSize    = 32
	refreshTokenSize = 32
)

func (as *AuthService) CreateSession(
	ctx context.Context,
	userID string,
	role domain.Role,
) (domain.TokenPair, error) {
	if userID == "" {
		return domain.TokenPair{}, fmt.Errorf("user id is empty")
	}

	sessionID, err := crypto.GenerateRandomToken(sessionIDSize)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error creating session id: %w", err)
	}

	refreshToken, err := crypto.GenerateRandomToken(refreshTokenSize)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error creating refresh token: %w", err)
	}

	accessToken, err := as.tokenMng.GenerateToken(userID, role)
	if err != nil {
		return domain.TokenPair{}, fmt.Errorf("error creating access token: %w", err)
	}

	session := &domain.Session{
		ID:           sessionID,
		UserID:       userID,
		AccessToken:  accessToken,
		RefreshToken: refreshToken,
	}

	if err := as.sessRepo.CreateSession(
		ctx,
		session,
		accessTokenTTL,
		refreshTokenTTL,
	); err != nil {
		return domain.TokenPair{}, fmt.Errorf("error saving auth session: %w", err)
	}

	return domain.TokenPair{
		AccessToken:  accessToken,
		RefreshToken: refreshToken,
	}, nil
}
