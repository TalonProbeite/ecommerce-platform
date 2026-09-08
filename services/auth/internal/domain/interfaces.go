// Package domain contains domain models and core service interfaces.
package domain

import (
	"context"
	"time"

	"github.com/google/uuid"
)

// UserRepository defines database operations for users.
type UserRepository interface {
	GetByEmail(ctx context.Context, email string) (*User, error)
	Create(ctx context.Context, user *User) (uuid.UUID, error)
	SetVerified(ctx context.Context, UserID string) error
	GetStatus(ctx context.Context, UserID string) (bool, error)
	GetByID(ctx context.Context, UserID string) (*User, error)
}

// SessionRepository defines caching/session storage operations.
type SessionRepository interface {
	SaveEntry(ctx context.Context, key, value string, ttl time.Duration) error
	DeleteEntry(ctx context.Context, key string) error
	GetValue(ctx context.Context, key string) (string, error)
}

// EventPublisher defines event publishing capabilities.
type EventPublisher interface {
	PublishEvent(eventKey string, payload []byte) error
}

// TokenManager defines JWT generation operations.
type TokenManager interface {
	GenerateToken(UserID string, role ...Role) (string, error)
}
