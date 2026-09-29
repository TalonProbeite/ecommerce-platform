package application

import (
	"context"
	"time"

	"shop/auth/internal/domain"

	"github.com/google/uuid"
)

// Compile-time checks: if an interface changes, the tests stop compiling
// right here instead of somewhere deep inside a test.
var (
	_ domain.AuthUserRepository = (*MockUserRepo)(nil)
	_ domain.SessionRepository  = (*MockSessionRepo)(nil)
	_ domain.EventPublisher     = (*MockPublisher)(nil)
	_ domain.TokenManager       = (*MockTokenManager)(nil)
	_ domain.GoogleClient       = (*MockGoogleClient)(nil)
)

// unset is called by a mock method whose Func was not provided.
func unset(name string) {
	panic(name + " is not set")
}

// ---------------------------------------------------------------------------
// User repository
// ---------------------------------------------------------------------------

type MockUserRepo struct {
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
	if m.GetByEmailFunc == nil {
		unset("GetByEmailFunc")
	}
	return m.GetByEmailFunc(ctx, email)
}

func (m *MockUserRepo) Create(ctx context.Context, u *domain.User) (uuid.UUID, error) {
	if m.CreateFunc == nil {
		unset("CreateFunc")
	}
	return m.CreateFunc(ctx, u)
}

func (m *MockUserRepo) SetVerified(ctx context.Context, userID string) (string, string, error) {
	if m.SetVerifiedFunc == nil {
		unset("SetVerifiedFunc")
	}
	return m.SetVerifiedFunc(ctx, userID)
}

func (m *MockUserRepo) GetStatus(ctx context.Context, userID string) (bool, error) {
	if m.GetStatusFunc == nil {
		unset("GetStatusFunc")
	}
	return m.GetStatusFunc(ctx, userID)
}

func (m *MockUserRepo) GetByID(ctx context.Context, userID string) (*domain.User, error) {
	if m.GetByIDFunc == nil {
		unset("GetByIDFunc")
	}
	return m.GetByIDFunc(ctx, userID)
}

func (m *MockUserRepo) GetByOAuth(ctx context.Context, provider, providerUserID string) (*domain.User, error) {
	if m.GetByOAuthFunc == nil {
		unset("GetByOAuthFunc")
	}
	return m.GetByOAuthFunc(ctx, provider, providerUserID)
}

func (m *MockUserRepo) CreateWithOauth(ctx context.Context, u *domain.User) (uuid.UUID, error) {
	if m.CreateWithOauthFunc == nil {
		unset("CreateWithOauthFunc")
	}
	return m.CreateWithOauthFunc(ctx, u)
}

func (m *MockUserRepo) GetEmailByUserID(ctx context.Context, userID string) (string, error) {
	if m.GetEmailByUserIDFunc == nil {
		unset("GetEmailByUserIDFunc")
	}
	return m.GetEmailByUserIDFunc(ctx, userID)
}

// ---------------------------------------------------------------------------
// Session repository
// ---------------------------------------------------------------------------

type MockSessionRepo struct {
	SaveEntryFunc                func(context.Context, string, string, time.Duration) error
	DeleteEntryFunc              func(context.Context, string) error
	GetValueFunc                 func(context.Context, string) (string, error)
	CreateSessionFunc            func(context.Context, *domain.Session, time.Duration, time.Duration) error
	GetSessionByRefreshTokenFunc func(context.Context, string) (*domain.Session, error)
	UpdateSessionTokensFunc      func(context.Context, *domain.Session, string, string, time.Duration, time.Duration) error
	DeleteSessionFunc            func(context.Context, *domain.Session) error
	RevokeAllSessionsFunc        func(context.Context, string) error
}

func (m *MockSessionRepo) SaveEntry(ctx context.Context, key, value string, ttl time.Duration) error {
	if m.SaveEntryFunc == nil {
		unset("SaveEntryFunc")
	}
	return m.SaveEntryFunc(ctx, key, value, ttl)
}

func (m *MockSessionRepo) DeleteEntry(ctx context.Context, key string) error {
	if m.DeleteEntryFunc == nil {
		unset("DeleteEntryFunc")
	}
	return m.DeleteEntryFunc(ctx, key)
}

func (m *MockSessionRepo) GetValue(ctx context.Context, key string) (string, error) {
	if m.GetValueFunc == nil {
		unset("GetValueFunc")
	}
	return m.GetValueFunc(ctx, key)
}

func (m *MockSessionRepo) CreateSession(
	ctx context.Context,
	s *domain.Session,
	accessTTL, refreshTTL time.Duration,
) error {
	if m.CreateSessionFunc == nil {
		unset("CreateSessionFunc")
	}
	return m.CreateSessionFunc(ctx, s, accessTTL, refreshTTL)
}

func (m *MockSessionRepo) GetSessionByRefreshToken(ctx context.Context, refreshToken string) (*domain.Session, error) {
	if m.GetSessionByRefreshTokenFunc == nil {
		unset("GetSessionByRefreshTokenFunc")
	}
	return m.GetSessionByRefreshTokenFunc(ctx, refreshToken)
}

func (m *MockSessionRepo) UpdateSessionTokens(
	ctx context.Context,
	s *domain.Session,
	oldAccess, oldRefresh string,
	accessTTL, refreshTTL time.Duration,
) error {
	if m.UpdateSessionTokensFunc == nil {
		unset("UpdateSessionTokensFunc")
	}
	return m.UpdateSessionTokensFunc(ctx, s, oldAccess, oldRefresh, accessTTL, refreshTTL)
}

func (m *MockSessionRepo) DeleteSession(ctx context.Context, s *domain.Session) error {
	if m.DeleteSessionFunc == nil {
		unset("DeleteSessionFunc")
	}
	return m.DeleteSessionFunc(ctx, s)
}

func (m *MockSessionRepo) RevokeAllSessions(ctx context.Context, userID string) error {
	if m.RevokeAllSessionsFunc == nil {
		unset("RevokeAllSessionsFunc")
	}
	return m.RevokeAllSessionsFunc(ctx, userID)
}

// ---------------------------------------------------------------------------
// Publisher, token manager, Google client
// ---------------------------------------------------------------------------

type MockPublisher struct {
	PublishEventFunc func(string, []byte) error
}

func (m *MockPublisher) PublishEvent(eventKey string, payload []byte) error {
	if m.PublishEventFunc == nil {
		unset("PublishEventFunc")
	}
	return m.PublishEventFunc(eventKey, payload)
}

type MockTokenManager struct {
	GenerateTokenFunc func(string, ...domain.Role) (string, error)
}

func (m *MockTokenManager) GenerateToken(userID string, role ...domain.Role) (string, error) {
	if m.GenerateTokenFunc == nil {
		unset("GenerateTokenFunc")
	}
	return m.GenerateTokenFunc(userID, role...)
}

type MockGoogleClient struct {
	AuthURLFunc    func(string) string
	GetProfileFunc func(context.Context, string) (*domain.OAuthProfile, error)
}

func (m *MockGoogleClient) AuthURL(state string) string {
	if m.AuthURLFunc == nil {
		unset("AuthURLFunc")
	}
	return m.AuthURLFunc(state)
}

func (m *MockGoogleClient) GetProfile(ctx context.Context, code string) (*domain.OAuthProfile, error) {
	if m.GetProfileFunc == nil {
		unset("GetProfileFunc")
	}
	return m.GetProfileFunc(ctx, code)
}
