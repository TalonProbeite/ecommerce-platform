package repository

import (
	"context"
	"fmt"
	"time"

	"github.com/redis/go-redis/v9"
)

type SessionRepo struct {
	rdb *redis.Client
}

func (s SessionRepo) SaveToken(ctx context.Context,
	userID string,
	token string) error {

	key := fmt.Sprintf("rt:%s", token)
	if err := s.rdb.Set(ctx, key, userID, 7*24*time.Hour).Err(); err != nil {
		return err
	}
	return nil
}

func (s SessionRepo) DeleteToken(ctx context.Context,
	token string) error {
	key := fmt.Sprintf("rt:%s", token)
	if err := s.rdb.Del(ctx, key).Err(); err != nil {
		return err
	}
	return nil
}

func (s *SessionRepo) GetUserID(ctx context.Context, token string) (string, error) {
	key := fmt.Sprintf("rt:%s", token)

	userID, err := s.rdb.Get(ctx, key).Result()
	if err != nil {
		return "", err
	}

	return userID, nil
}
