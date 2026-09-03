package repository

import (
	"context"
	"time"

	"github.com/redis/go-redis/v9"
)

type SessionRepo struct {
	rdb *redis.Client
}

func NewSessionRepo(rdb *redis.Client) *SessionRepo{
	repo := SessionRepo{rdb: rdb}
	return &repo
}

func (s SessionRepo) SaveToken(ctx context.Context,
	userID string,
	token string) error {
	if err := s.rdb.Set(ctx, token, userID, 7*24*time.Hour).Err(); err != nil {
		return err
	}
	return nil
}

func (s SessionRepo) DeleteToken(ctx context.Context, token string) error {
	if err := s.rdb.Del(ctx, token).Err(); err != nil {
		return err
	}
	return nil
}

func (s *SessionRepo) GetUserID(ctx context.Context, token string) (string, error) {
	userID, err := s.rdb.Get(ctx, token).Result()
	if err != nil {
		return "", err
	}

	return userID, nil
}
