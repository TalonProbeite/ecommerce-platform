package repository

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"shop/auth/internal/domain"
	"time"

	"github.com/redis/go-redis/v9"
)

var ErrRefreshTokenRejected = errors.New("refresh token rejected")

type SessionRepo struct {
	rdb *redis.Client
}

func NewSessionRepo(rdb *redis.Client) *SessionRepo {
	return &SessionRepo{
		rdb: rdb,
	}
}

func marshalSession(session *domain.Session) ([]byte, error) {
	return json.Marshal(map[string]string{
		"id":            session.ID,
		"user_id":       session.UserID,
		"access_token":  session.AccessToken,
		"refresh_token": session.RefreshToken,
	})
}

func (s *SessionRepo) SaveEntry(
	ctx context.Context,
	key, value string,
	ttl time.Duration,
) error {
	return s.rdb.Set(ctx, key, value, ttl).Err()
}

func (s *SessionRepo) DeleteEntry(
	ctx context.Context,
	key string,
) error {
	return s.rdb.Del(ctx, key).Err()
}

func (s *SessionRepo) GetValue(
	ctx context.Context,
	key string,
) (string, error) {
	return s.rdb.Get(ctx, key).Result()
}

func (s *SessionRepo) CreateSession(
	ctx context.Context,
	session *domain.Session,
	accessTTL,
	refreshTTL time.Duration,
) error {
	sessionKey := fmt.Sprintf("session:%s", session.ID)
	refreshKey := fmt.Sprintf("refresh:%s", session.RefreshToken)
	userSessionsKey := fmt.Sprintf("user:sessions:%s", session.UserID)

	data, err := marshalSession(session)
	if err != nil {
		return fmt.Errorf("failed to marshal session: %w", err)
	}

	pipe := s.rdb.TxPipeline()

	pipe.Set(ctx, refreshKey, session.ID, refreshTTL)
	pipe.Set(ctx, sessionKey, data, refreshTTL)
	pipe.SAdd(ctx, userSessionsKey, session.ID)

	_, err = pipe.Exec(ctx)
	if err != nil {
		return fmt.Errorf("failed to create session: %w", err)
	}

	return nil
}

func (s *SessionRepo) GetSessionByRefreshToken(
	ctx context.Context,
	refreshToken string,
) (*domain.Session, error) {
	refreshKey := fmt.Sprintf("refresh:%s", refreshToken)

	sessionID, err := s.rdb.Get(ctx, refreshKey).Result()
	if err != nil {
		return nil, err
	}

	sessionKey := fmt.Sprintf("session:%s", sessionID)

	data, err := s.rdb.Get(ctx, sessionKey).Bytes()
	if err != nil {
		return nil, err
	}

	var session domain.Session
	err = json.Unmarshal(data, &session)
	if err != nil {
		return nil, fmt.Errorf("failed to unmarshal session: %w", err)
	}

	return &session, nil
}

func (s *SessionRepo) UpdateSessionTokens(
	ctx context.Context,
	session *domain.Session,
	oldAccessToken string,
	oldRefreshToken string,
	accessTTL time.Duration,
	refreshTTL time.Duration,
) error {
	data, err := marshalSession(session)
	if err != nil {
		return err
	}

	refreshKey := fmt.Sprintf("refresh:%s", oldRefreshToken)
	newRefreshKey := fmt.Sprintf("refresh:%s", session.RefreshToken)
	sessionKey := fmt.Sprintf("session:%s", session.ID)

	err = s.rdb.Watch(ctx, func(tx *redis.Tx) error {
		currentSessionID, getErr := tx.Get(ctx, refreshKey).Result()
		if getErr != nil {
			if errors.Is(getErr, redis.Nil) {
				return ErrRefreshTokenRejected
			}

			return getErr
		}

		if currentSessionID != session.ID {
			return ErrRefreshTokenRejected
		}

		_, txErr := tx.TxPipelined(ctx, func(pipe redis.Pipeliner) error {
			pipe.Del(ctx, refreshKey)
			pipe.Set(ctx, newRefreshKey, session.ID, refreshTTL)
			pipe.Set(ctx, sessionKey, data, refreshTTL)
			return nil
		})
		if errors.Is(txErr, redis.TxFailedErr) {
			return ErrRefreshTokenRejected
		}

		return txErr
	}, refreshKey)

	return err
}

func (s *SessionRepo) DeleteSession(
	ctx context.Context,
	session *domain.Session,
) error {
	sessionKey := fmt.Sprintf("session:%s", session.ID)
	refreshKey := fmt.Sprintf("refresh:%s", session.RefreshToken)
	userSessionsKey := fmt.Sprintf("user:sessions:%s", session.UserID)

	pipe := s.rdb.TxPipeline()

	pipe.Del(ctx, sessionKey)
	pipe.Del(ctx, refreshKey)
	pipe.SRem(ctx, userSessionsKey, session.ID)

	if _, err := pipe.Exec(ctx); err != nil {
		return fmt.Errorf("failed to delete session: %w", err)
	}

	return nil
}

func (s *SessionRepo) RevokeAllSessions(
	ctx context.Context,
	userID string,
) error {
	userSessionsKey := fmt.Sprintf("user:sessions:%s", userID)

	sessionIDs, err := s.rdb.SMembers(ctx, userSessionsKey).Result()
	if err != nil {
		return fmt.Errorf("failed to get user sessions: %w", err)
	}

	pipe := s.rdb.TxPipeline()

	for _, sessionID := range sessionIDs {
		sessionKey := fmt.Sprintf("session:%s", sessionID)

		data, getErr := s.rdb.Get(ctx, sessionKey).Bytes()
		if getErr != nil {
			if errors.Is(getErr, redis.Nil) {
				continue
			}

			return fmt.Errorf("failed to get session %s: %w", sessionID, getErr)
		}

		var session domain.Session
		if err = json.Unmarshal(data, &session); err != nil {
			return fmt.Errorf("failed to unmarshal session %s: %w", sessionID, err)
		}

		pipe.Del(ctx, sessionKey)
		pipe.Del(ctx, fmt.Sprintf("refresh:%s", session.RefreshToken))
	}

	pipe.Del(ctx, userSessionsKey)

	_, err = pipe.Exec(ctx)
	if err != nil {
		return fmt.Errorf("failed to revoke user sessions: %w", err)
	}

	return nil
}
