package repository

import (
	"context"
	"time"

	"github.com/redis/go-redis/v9"
)

// SessionRepo handles session management using Redis.
type SessionRepo struct {
	rdb *redis.Client
}

// NewSessionRepo constructs a new SessionRepo instance.
func NewSessionRepo(rdb *redis.Client) *SessionRepo {
	repo := SessionRepo{rdb: rdb}
	return &repo
}

// SaveEntry sets a key-value pair in Redis with a TTL.
func (s *SessionRepo) SaveEntry(ctx context.Context, key, value string, ttl time.Duration) error {
	if err := s.rdb.Set(ctx, key, value, ttl).Err(); err != nil {
		return err
	}
	return nil
}

// DeleteEntry removes a key from Redis.
func (s *SessionRepo) DeleteEntry(ctx context.Context, key string) error {
	if err := s.rdb.Del(ctx, key).Err(); err != nil {
		return err
	}
	return nil
}

// GetValue retrieves a string value by key from Redis.
func (s *SessionRepo) GetValue(ctx context.Context, key string) (string, error) {
	userID, err := s.rdb.Get(ctx, key).Result()
	if err != nil {
		return "", err
	}

	return userID, nil
}
