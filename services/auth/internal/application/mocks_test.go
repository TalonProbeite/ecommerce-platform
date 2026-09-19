package application

import (
	"context"
	"log/slog"
	"time"

	"shop/auth/internal/domain"

	"github.com/google/uuid"
)

type MockUserRepo struct {
	Log *slog.Logger

	GetByEmailFunc       func(context.Context, string) (*domain.User, error)
	CreateFunc           func(context.Context, *domain.User) (uuid.UUID, error)
	SetVerifiedFunc      func(context.Context, string) (string, string, error)
	GetStatusFunc        func(context.Context, string) (bool, error)
	GetByIDFunc          func(context.Context, string) (*domain.User, error)
	GetByOAuthFunc       func(context.Context, string, string) (*domain.User, error)
	CreateWithOauthFunc  func(context.Context, *domain.User) (uuid.UUID, error)
	GetEmailByUserIDFunc func(context.Context, string) (string, error)
}

func (m *MockUserRepo) GetByEmail(ctx context.Context, email string) (*domain.User, error) {
	m.Log.Debug("MockUserRepo.GetByEmail", "email", email)
	if m.GetByEmailFunc == nil {
		panic("GetByEmailFunc is nil")
	}
	return m.GetByEmailFunc(ctx, email)
}

func (m *MockUserRepo) Create(ctx context.Context, user *domain.User) (uuid.UUID, error) {
	m.Log.Debug("MockUserRepo.Create", "email", user.Email, "role", user.Role)
	if m.CreateFunc == nil {
		panic("CreateFunc is nil")
	}
	return m.CreateFunc(ctx, user)
}

func (m *MockUserRepo) SetVerified(ctx context.Context, userID string) (string, string, error) {
	m.Log.Debug("MockUserRepo.SetVerified", "user_id", userID)
	if m.SetVerifiedFunc == nil {
		panic("SetVerifiedFunc is nil")
	}
	return m.SetVerifiedFunc(ctx, userID)
}

func (m *MockUserRepo) GetStatus(ctx context.Context, userID string) (bool, error) {
	m.Log.Debug("MockUserRepo.GetStatus", "user_id", userID)
	if m.GetStatusFunc == nil {
		panic("GetStatusFunc is nil")
	}
	return m.GetStatusFunc(ctx, userID)
}

func (m *MockUserRepo) GetByID(ctx context.Context, userID string) (*domain.User, error) {
	m.Log.Debug("MockUserRepo.GetByID", "user_id", userID)
	if m.GetByIDFunc == nil {
		panic("GetByIDFunc is nil")
	}
	return m.GetByIDFunc(ctx, userID)
}

func (m *MockUserRepo) GetByOAuth(ctx context.Context, provider, providerUserID string) (*domain.User, error) {
	m.Log.Debug("MockUserRepo.GetByOAuth", "provider", provider, "provider_user_id", providerUserID)
	if m.GetByOAuthFunc == nil {
		panic("GetByOAuthFunc is nil")
	}
	return m.GetByOAuthFunc(ctx, provider, providerUserID)
}

func (m *MockUserRepo) CreateWithOauth(ctx context.Context, user *domain.User) (uuid.UUID, error) {
	m.Log.Debug("MockUserRepo.CreateWithOauth", "email", user.Email, "provider", user.Provider, "provider_user_id", user.ProviderUserID)
	if m.CreateWithOauthFunc == nil {
		panic("CreateWithOauthFunc is nil")
	}
	return m.CreateWithOauthFunc(ctx, user)
}

func (m *MockUserRepo) GetEmailByUserID(ctx context.Context, userID string) (string, error) {
	m.Log.Debug("MockUserRepo.GetEmailByUserID", "user_id", userID)
	if m.GetEmailByUserIDFunc == nil {
		panic("GetEmailByUserIDFunc is nil")
	}
	return m.GetEmailByUserIDFunc(ctx, userID)
}

type MockSessionRepo struct {
	Log *slog.Logger

	SaveEntryFunc   func(context.Context, string, string, time.Duration) error
	DeleteEntryFunc func(context.Context, string) error
	GetValueFunc    func(context.Context, string) (string, error)
}

func (m *MockSessionRepo) SaveEntry(ctx context.Context, key, value string, ttl time.Duration) error {
	m.Log.Debug("MockSessionRepo.SaveEntry", "key", key, "ttl", ttl)
	if m.SaveEntryFunc == nil {
		panic("SaveEntryFunc is nil")
	}
	return m.SaveEntryFunc(ctx, key, value, ttl)
}

func (m *MockSessionRepo) DeleteEntry(ctx context.Context, key string) error {
	m.Log.Debug("MockSessionRepo.DeleteEntry", "key", key)
	if m.DeleteEntryFunc == nil {
		panic("DeleteEntryFunc is nil")
	}
	return m.DeleteEntryFunc(ctx, key)
}

func (m *MockSessionRepo) GetValue(ctx context.Context, key string) (string, error) {
	m.Log.Debug("MockSessionRepo.GetValue", "key", key)
	if m.GetValueFunc == nil {
		panic("GetValueFunc is nil")
	}
	return m.GetValueFunc(ctx, key)
}

type MockPublisher struct {
	Log              *slog.Logger
	PublishEventFunc func(string, []byte) error
}

func (m *MockPublisher) PublishEvent(eventKey string, payload []byte) error {
	m.Log.Debug("MockPublisher.PublishEvent", "event_key", eventKey)
	if m.PublishEventFunc == nil {
		panic("PublishEventFunc is nil")
	}
	return m.PublishEventFunc(eventKey, payload)
}

type MockTokenManager struct {
	Log               *slog.Logger
	GenerateTokenFunc func(string, ...domain.Role) (string, error)
}

func (m *MockTokenManager) GenerateToken(userID string, role ...domain.Role) (string, error) {
	m.Log.Debug("MockTokenManager.GenerateToken", "user_id", userID, "role", role)
	if m.GenerateTokenFunc == nil {
		panic("GenerateTokenFunc is nil")
	}
	return m.GenerateTokenFunc(userID, role...)
}

type MockGoogleClient struct {
	Log            *slog.Logger
	AuthURLFunc    func(string) string
	GetProfileFunc func(context.Context, string) (*domain.OAuthProfile, error)
}

func (m *MockGoogleClient) AuthURL(state string) string {
	m.Log.Debug("MockGoogleClient.AuthURL", "state", state)
	if m.AuthURLFunc == nil {
		panic("AuthURLFunc is nil")
	}
	return m.AuthURLFunc(state)
}

func (m *MockGoogleClient) GetProfile(ctx context.Context, code string) (*domain.OAuthProfile, error) {
	m.Log.Debug("MockGoogleClient.GetProfile", "code", code)
	if m.GetProfileFunc == nil {
		panic("GetProfileFunc is nil")
	}
	return m.GetProfileFunc(ctx, code)
}
