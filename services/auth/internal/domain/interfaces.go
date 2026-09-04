package domain

import (
	"context"
	"github.com/google/uuid"
	"time"
)

type UserRepository interface {
	GetByEmail(ctx context.Context, email string) (*User, error)
	Create(ctx context.Context, user *User) (uuid.UUID,error)
}

type SessionRepository interface {
	SaveEntry(ctx context.Context, key string, value string, ttl time.Duration) error
	DeleteEntry(ctx context.Context, key string) error
	GetValue(ctx context.Context, key string) (string, error)
}

type EventPublisher interface {
	PublishEvent(eventKey string, payload []byte) error
}

type TokenManager interface {
	GenerateToken(userId string, role ...string) (string, error)
}
