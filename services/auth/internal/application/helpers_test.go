package application

import (
	"context"
	"encoding/json/v2"
	"errors"
	"slices"
	"strings"
	"sync"
	"testing"
	"time"

	"shop/auth/internal/domain"
	"shop/auth/internal/infra/crypto"
	"shop/shared/roles"

	"github.com/google/uuid"
)

const (
	testUserID      = "11111111-1111-1111-1111-111111111111"
	testEmail       = "john@example.com"
	testPassword    = "Password123!"
	testAccessToken = "access-token"
)

var (
	testUUID = uuid.MustParse(testUserID)

	// errBoom is the error injected into a mock; tests then check that the
	// service wrapped it with %w instead of swallowing it.
	errBoom        = errors.New("boom")
	errKeyNotFound = errors.New("key not found")
)

// bcrypt is slow, so the hash is computed once for the whole package.
var (
	hashOnce sync.Once
	hashVal  string
	hashErr  error
)

func testPasswordHash(t *testing.T) string {
	t.Helper()
	hashOnce.Do(func() { hashVal, hashErr = crypto.HashPassword(testPassword) })
	if hashErr != nil {
		t.Fatalf("hash test password: %v", hashErr)
	}
	return hashVal
}

// ---------------------------------------------------------------------------
// fixture
// ---------------------------------------------------------------------------

type savedEntry struct {
	value string
	ttl   time.Duration
}

type publishedEvent struct {
	key     string
	payload []byte
}

type tokenRequest struct {
	userID string
	role   []roles.Role
}

// sessionWrite captures CreateSession / UpdateSessionTokens arguments.
type sessionWrite struct {
	session    domain.Session
	oldAccess  string
	oldRefresh string
	accessTTL  time.Duration
	refreshTTL time.Duration
}

// fixture wires an AuthService to mocks that succeed by default.
// A test breaks one step with fail[<call name>] and tweaks returned data
// through the exported-looking fields below.
type fixture struct {
	svc *AuthService

	// Data returned by mocks. Tests may modify these before calling the service.
	user         *domain.User // nil => repo returns (nil, nil)
	userActive   bool         // GetStatus result
	newUserID    uuid.UUID    // Create / CreateWithOauth result
	profile      *domain.OAuthProfile
	session      *domain.Session   // stored session for Refresh / Logout
	store        map[string]string // key-value part of SessionRepository
	fail         map[string]error  // call name => error to return
	oauthMissing bool              // GetByOAuth returns ErrUserNotFound

	// Recorded interactions.
	calls          []string
	saved          map[string]savedEntry
	deleted        []string
	events         []publishedEvent
	tokenReqs      []tokenRequest
	created        *sessionWrite
	updated        *sessionWrite
	deletedSession *domain.Session
	createdUser    *domain.User
	oauthProvider  string
	oauthID        string
	authState      string
}

func (f *fixture) hit(name string) error {
	f.calls = append(f.calls, name)
	return f.fail[name]
}

func (f *fixture) called(name string) bool { return slices.Contains(f.calls, name) }

func newFixture(t *testing.T) *fixture {
	t.Helper()

	f := &fixture{
		user: &domain.User{
			ID:       testUserID,
			Email:    testEmail,
			Password: testPasswordHash(t),
			Role:     roles.RoleCustomer,
			IsActive: true,
		},
		userActive: true,
		newUserID:  testUUID,
		profile: &domain.OAuthProfile{
			Provider:       domain.ProviderGoogle,
			ProviderUserID: "google-123",
			Email:          testEmail,
			FirstName:      "GoogleJohn",
			LastName:       "GoogleDoe",
		},
		session: &domain.Session{
			ID:           "sess-1",
			UserID:       testUserID,
			AccessToken:  "old-access",
			RefreshToken: "old-refresh",
		},
		store: map[string]string{},
		fail:  map[string]error{},
		saved: map[string]savedEntry{},
	}

	users := &MockUserRepo{
		GetByEmailFunc: func(context.Context, string) (*domain.User, error) {
			if err := f.hit("users.GetByEmail"); err != nil {
				return nil, err
			}
			return f.user, nil
		},
		CreateFunc: func(_ context.Context, u *domain.User) (uuid.UUID, error) {
			f.createdUser = u
			if err := f.hit("users.Create"); err != nil {
				return uuid.Nil, err
			}
			return f.newUserID, nil
		},
		SetVerifiedFunc: func(context.Context, string) (string, string, error) {
			if err := f.hit("users.SetVerified"); err != nil {
				return "", "", err
			}
			return testEmail, "John", nil
		},
		GetStatusFunc: func(context.Context, string) (bool, error) {
			if err := f.hit("users.GetStatus"); err != nil {
				return false, err
			}
			return f.userActive, nil
		},
		GetByIDFunc: func(context.Context, string) (*domain.User, error) {
			if err := f.hit("users.GetByID"); err != nil {
				return nil, err
			}
			return f.user, nil
		},
		GetByOAuthFunc: func(_ context.Context, provider, id string) (*domain.User, error) {
			f.oauthProvider, f.oauthID = provider, id
			if err := f.hit("users.GetByOAuth"); err != nil {
				return nil, err
			}
			if f.oauthMissing {
				return nil, domain.ErrUserNotFound
			}
			return f.user, nil
		},
		CreateWithOauthFunc: func(_ context.Context, u *domain.User) (uuid.UUID, error) {
			f.createdUser = u
			if err := f.hit("users.CreateWithOauth"); err != nil {
				return uuid.Nil, err
			}
			return f.newUserID, nil
		},
		GetEmailByUserIDFunc: func(context.Context, string) (string, error) {
			if err := f.hit("users.GetEmailByUserID"); err != nil {
				return "", err
			}
			return testEmail, nil
		},
	}

	sess := &MockSessionRepo{
		SaveEntryFunc: func(_ context.Context, key, value string, ttl time.Duration) error {
			if err := f.hit("sess.SaveEntry"); err != nil {
				return err
			}
			f.saved[key] = savedEntry{value: value, ttl: ttl}
			f.store[key] = value
			return nil
		},
		DeleteEntryFunc: func(_ context.Context, key string) error {
			if err := f.hit("sess.DeleteEntry"); err != nil {
				return err
			}
			delete(f.store, key)
			f.deleted = append(f.deleted, key)
			return nil
		},
		GetValueFunc: func(_ context.Context, key string) (string, error) {
			if err := f.hit("sess.GetValue"); err != nil {
				return "", err
			}
			v, ok := f.store[key]
			if !ok {
				return "", errKeyNotFound
			}
			return v, nil
		},
		CreateSessionFunc: func(_ context.Context, s *domain.Session, accessTTL, refreshTTL time.Duration) error {
			if err := f.hit("sess.CreateSession"); err != nil {
				return err
			}
			f.created = &sessionWrite{session: *s, accessTTL: accessTTL, refreshTTL: refreshTTL}
			return nil
		},
		GetSessionByRefreshTokenFunc: func(_ context.Context, refresh string) (*domain.Session, error) {
			if err := f.hit("sess.GetSessionByRefreshToken"); err != nil {
				return nil, err
			}
			if f.session == nil || f.session.RefreshToken != refresh {
				return nil, errKeyNotFound
			}
			// A copy, so that the service mutating it does not touch our fixture,
			// like a real repository that decodes a fresh value every time.
			cp := *f.session
			return &cp, nil
		},
		UpdateSessionTokensFunc: func(
			_ context.Context,
			s *domain.Session,
			oldAccess, oldRefresh string,
			accessTTL, refreshTTL time.Duration,
		) error {
			if err := f.hit("sess.UpdateSessionTokens"); err != nil {
				return err
			}
			f.updated = &sessionWrite{
				session:    *s,
				oldAccess:  oldAccess,
				oldRefresh: oldRefresh,
				accessTTL:  accessTTL,
				refreshTTL: refreshTTL,
			}
			return nil
		},
		DeleteSessionFunc: func(_ context.Context, s *domain.Session) error {
			if err := f.hit("sess.DeleteSession"); err != nil {
				return err
			}
			cp := *s
			f.deletedSession = &cp
			return nil
		},
		RevokeAllSessionsFunc: func(context.Context, string) error {
			return f.hit("sess.RevokeAllSessions")
		},
	}

	pub := &MockPublisher{
		PublishEventFunc: func(key string, payload []byte) error {
			if err := f.hit("pub.PublishEvent"); err != nil {
				return err
			}
			f.events = append(f.events, publishedEvent{key: key, payload: slices.Clone(payload)})
			return nil
		},
	}

	tokens := &MockTokenManager{
		GenerateTokenFunc: func(userID string, role ...roles.Role) (string, error) {
			f.tokenReqs = append(
				f.tokenReqs,
				tokenRequest{userID: userID, role: role},
			)

			if err := f.hit("tokens.GenerateToken"); err != nil {
				return "", err
			}

			return testAccessToken, nil
		},

		VerifyTokenFunc: func(token string) (string, roles.Role, error) {
			if err := f.hit("tokens.VerifyToken"); err != nil {
				return "", "", err
			}

			if token != testAccessToken {
				return "", "", errors.New("invalid access token")
			}

			return testUserID, roles.RoleCustomer, nil
		},
	}

	google := &MockGoogleClient{
		AuthURLFunc: func(state string) string {
			f.hit("google.AuthURL")
			f.authState = state
			return "https://accounts.example/auth?state=" + state
		},
		GetProfileFunc: func(context.Context, string) (*domain.OAuthProfile, error) {
			if err := f.hit("google.GetProfile"); err != nil {
				return nil, err
			}
			cp := *f.profile
			return &cp, nil
		},
	}

	f.svc = NewAuthService(users, sess, pub, tokens, google)
	return f
}

// ---------------------------------------------------------------------------
// error-case description shared by all table tests
// ---------------------------------------------------------------------------

// errCase describes an expected failure. Embed it into a test-case struct.
//
//   - failAt:  name of the mock call that should fail ("" = nothing injected)
//   - failErr: error returned by that call (default errBoom)
//   - wantMsg: substring expected in the returned error
//   - wantIs:  sentinel expected via errors.Is (default: the injected error)
//
// A case without any of these fields is expected to succeed.
type errCase struct {
	failAt  string
	failErr error
	wantMsg string
	wantIs  error
}

func (c errCase) wantsErr() bool {
	return c.failAt != "" || c.wantMsg != "" || c.wantIs != nil
}

func (c errCase) arm(f *fixture) {
	if c.failAt == "" {
		return
	}
	err := c.failErr
	if err == nil {
		err = errBoom
	}
	f.fail[c.failAt] = err
}

// check verifies err against the case. It returns true when a failure was
// expected (and verified), so the caller can skip the success assertions.
func (c errCase) check(t *testing.T, err error) bool {
	t.Helper()

	if !c.wantsErr() {
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		return false
	}

	if err == nil {
		t.Fatal("expected error, got nil")
	}
	if c.wantMsg != "" && !strings.Contains(err.Error(), c.wantMsg) {
		t.Fatalf("expected error containing %q, got %q", c.wantMsg, err.Error())
	}

	is := c.wantIs
	if is == nil && c.failAt != "" && c.failErr == nil {
		is = errBoom
	}
	if is != nil && !errors.Is(err, is) {
		t.Fatalf("expected error wrapping %v, got %v", is, err)
	}
	return true
}

// ---------------------------------------------------------------------------
// assertions
// ---------------------------------------------------------------------------

func assertNotCalled(t *testing.T, f *fixture, names ...string) {
	t.Helper()
	for _, n := range names {
		if f.called(n) {
			t.Errorf("%s must not be called; calls: %v", n, f.calls)
		}
	}
}

// assertTokenPair checks the returned pair against what was really stored
// through CreateSession and what was requested from the token manager.
func assertTokenPair(t *testing.T, f *fixture, got domain.TokenPair, userID string, role roles.Role) {
	t.Helper()

	if got.AccessToken != testAccessToken {
		t.Errorf("access token = %q, want %q", got.AccessToken, testAccessToken)
	}
	if got.RefreshToken == "" {
		t.Error("refresh token is empty")
	}

	if f.created == nil {
		t.Fatal("session was not created")
	}
	s := f.created.session
	if s.ID == "" || s.ID == s.RefreshToken {
		t.Errorf("session id %q must be non-empty and differ from the refresh token", s.ID)
	}
	if s.UserID != userID {
		t.Errorf("session user id = %q, want %q", s.UserID, userID)
	}
	if s.AccessToken != got.AccessToken || s.RefreshToken != got.RefreshToken {
		t.Errorf("stored session tokens differ from the returned pair: %+v vs %+v", s, got)
	}
	if f.created.accessTTL != accessTokenTTL || f.created.refreshTTL != refreshTokenTTL {
		t.Errorf("session TTLs = %s/%s, want %s/%s",
			f.created.accessTTL, f.created.refreshTTL, accessTokenTTL, refreshTokenTTL)
	}

	assertLastTokenRequest(t, f, userID, role)
}

func assertLastTokenRequest(t *testing.T, f *fixture, userID string, role roles.Role) {
	t.Helper()
	if len(f.tokenReqs) == 0 {
		t.Fatal("token manager was not called")
	}
	req := f.tokenReqs[len(f.tokenReqs)-1]
	if req.userID != userID || len(req.role) != 1 || req.role[0] != role {
		t.Errorf("GenerateToken(%q, %v), want (%q, [%s])", req.userID, req.role, userID, role)
	}
}

// eventPayload returns the payload of the published event with the given key.
func (f *fixture) eventPayload(t *testing.T, key string) []byte {
	t.Helper()
	for _, e := range f.events {
		if e.key == key {
			return e.payload
		}
	}
	t.Fatalf("event %q was not published; got %d events", key, len(f.events))
	return nil
}

func decode[T any](t *testing.T, data []byte) T {
	t.Helper()
	var v T
	if err := json.Unmarshal(data, &v); err != nil {
		t.Fatalf("decode %T: %v", v, err)
	}
	return v
}

func mustJSON(t *testing.T, v any) string {
	t.Helper()
	b, err := json.Marshal(v)
	if err != nil {
		t.Fatalf("marshal %T: %v", v, err)
	}
	return string(b)
}

func testCtx() context.Context { return context.Background() }
