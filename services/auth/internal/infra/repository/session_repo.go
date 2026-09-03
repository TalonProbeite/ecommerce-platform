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

func (s *SessionRepo) SaveEntry(ctx context.Context, key string, value string, ttl time.Duration) error {
	if err := s.rdb.Set(ctx, key,  value, ttl).Err(); err != nil {
		return err
	}
	return nil
}

func (s *SessionRepo) DeleteEntry(ctx context.Context, key string) error {
	if err := s.rdb.Del(ctx, key).Err(); err != nil {
		return err
	}
	return nil
}

func (s *SessionRepo) GetValue(ctx context.Context, key string) (string, error) {
	userID, err := s.rdb.Get(ctx, key).Result()
	if err != nil {
		return "", err
	}

	return userID, nil
}
