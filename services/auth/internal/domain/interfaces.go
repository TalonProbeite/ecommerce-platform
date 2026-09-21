package domain

import (
	"context"
	"time"

	"github.com/google/uuid"
)

type AuthUserRepository interface {
	GetByEmail(ctx context.Context, email string) (*User, error)
	Create(ctx context.Context, user *User) (uuid.UUID, error)
	SetVerified(ctx context.Context, userID string) (string, string, error)
	GetStatus(ctx context.Context, userID string) (bool, error)
	GetByID(ctx context.Context, userID string) (*User, error)
	GetByOAuth(ctx context.Context, provider, providerUserID string) (*User, error)
	CreateWithOauth(ctx context.Context, u *User) (uuid.UUID, error)
	GetEmailByUserID(ctx context.Context, userID string) (string, error)
}

type ProfileUserRepository interface {
	GetByIDProfile(ctx context.Context, userID string) (*User, error)
	// Update(ctx context.Context, user *User) error
	// UpdatePassword(ctx context.Context, userID, newHash string) error
	// GetEmailByUserID(ctx context.Context, userID string) (string, error)
}

type AdminUserRepository interface {
	GetByID(ctx context.Context, userID string) (*User, error)
	UpdateRole(ctx context.Context, userID string, role Role) error
	UpdateStatus(ctx context.Context, userID string, isActive bool) error
	ListUsers(ctx context.Context, limit, offset int) ([]User, error)
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
	GenerateToken(userID string, role ...Role) (string, error)
}

type GoogleClient interface {
	AuthURL(state string) string
	GetProfile(ctx context.Context, code string) (*OAuthProfile, error)
}
