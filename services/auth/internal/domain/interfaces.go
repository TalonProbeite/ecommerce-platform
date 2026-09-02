package domain

import "context"

type UserRepository interface {
	GetByEmail(ctx context.Context, email string) (*User, error)
	Create(ctx context.Context, user *User) error
}

type SessionRepository interface {
	SaveToken(ctx context.Context, userID string, token string) error
	DeleteToken(ctx context.Context, token string) error
	GetUserID(ctx context.Context , token string) error
}

type EventPublisher interface {
	PublishUserRegistered(ctx context.Context, email string) error
}

type TokenManager interface {
	GenerateToken(userId string, role ...string) (string, error)
}
