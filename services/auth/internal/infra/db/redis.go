package db

import (
	"context"
	"fmt"
	"time"

	"github.com/redis/go-redis/v9"
)

// NewRedisClient parses connection URL and connects to Redis.
func NewRedisClient(addr string) (*redis.Client, error) {
	options, err := redis.ParseURL(addr)
	if err != nil {
		return nil, fmt.Errorf("failed to parse url for redis: %w", err)
	}
	rdb := redis.NewClient(options)

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := rdb.Ping(ctx).Err(); err != nil {
		if closeErr := rdb.Close(); closeErr != nil {
			return nil, fmt.Errorf("failed to ping redis (%w) and failed to close client (%w)", err, closeErr)
		}
		return nil, fmt.Errorf("failed to ping redis: %w", err)
	}

	return rdb, nil
}
