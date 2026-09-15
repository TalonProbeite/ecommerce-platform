package domain

import (
	"context"
	"time"

	"github.com/google/uuid"
)

type UserRepository interface {
	GetByEmail(ctx context.Context, email string) (*User, error)
	Create(ctx context.Context, user *User) (uuid.UUID, error)
	SetVerified(ctx context.Context, UserID string) (string, string, error)
	GetStatus(ctx context.Context, UserID string) (bool, error)
	GetByID(ctx context.Context, UserID string) (*User, error)
	GetByOAuth(ctx context.Context, provider, providerUserID string) (*User, error)
	CreateWithOauth(ctx context.Context, u *User) (userID uuid.UUID, err error)
}
type SessionRepository interface {
	SaveEntry(ctx context.Context, key, value string, ttl time.Duration) error
	DeleteEntry(ctx context.Context, key string) error
	GetValue(ctx context.Context, key string) (string, error)
}
type EventPublisher interface {
	PublishEvent(eventKey string, payload []byte) error
}
type TokenManager interface {
	GenerateToken(UserID string, role ...Role) (string, error)
}
type GoogleClient interface {
	AuthURL(state string) string
	GetProfile(ctx context.Context, code string) (*OAuthProfile, error)
}
