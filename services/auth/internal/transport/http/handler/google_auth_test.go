package handler

import (
	"context"
	"encoding/json"
	"github.com/google/uuid"
	"github.com/labstack/echo/v4"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"shop/auth/internal/application"
	"shop/auth/internal/domain"
	"shop/shared/roles"
	"strings"
	"testing"
	"time"
)

type oauthMemoryRepo struct {
	domain.SessionRepository
	values map[string]string
}

func (r *oauthMemoryRepo) SaveEntry(_ context.Context, k, v string, _ time.Duration) error {
	r.values[k] = v
	return nil
}
func (r *oauthMemoryRepo) GetValue(_ context.Context, k string) (string, error) {
	v, ok := r.values[k]
	if !ok {
		return "", domain.ErrEntryNotFound
	}
	return v, nil
}
func (r *oauthMemoryRepo) ConsumeEntry(ctx context.Context, k string) (string, error) {
	v, err := r.GetValue(ctx, k)
	delete(r.values, k)
	return v, err
}
func (r *oauthMemoryRepo) DeleteEntry(_ context.Context, k string) error {
	delete(r.values, k)
	return nil
}
func (r *oauthMemoryRepo) AcquireLock(_ context.Context, k, v string, _ time.Duration) (bool, error) {
	if _, ok := r.values[k]; ok {
		return false, nil
	}
	r.values[k] = v
	return true, nil
}
func (r *oauthMemoryRepo) ReleaseLock(ctx context.Context, k, v string) error {
	return r.DeleteEntry(ctx, k)
}
func (r *oauthMemoryRepo) CreateSession(context.Context, *domain.Session, time.Duration, time.Duration) error {
	return nil
}

type oauthUsers struct {
	domain.AuthUserRepository
	user *domain.User
}

func (r *oauthUsers) GetByOAuth(context.Context, string, string) (*domain.User, error) {
	if r.user == nil {
		return nil, domain.ErrUserNotFound
	}
	return r.user, nil
}
func (r *oauthUsers) CreateWithOauth(_ context.Context, u *domain.User) (uuid.UUID, error) {
	id := uuid.MustParse("11111111-1111-1111-1111-111111111111")
	u.ID = id.String()
	r.user = u
	return id, nil
}

type oauthTokens struct{ domain.TokenManager }

func (oauthTokens) GenerateToken(string, roles.Role, *string) (string, error) { return "access", nil }

type oauthGoogle struct{ domain.GoogleClient }

func (oauthGoogle) AuthURL(state, verifier, nonce string) string {
	return "https://accounts.google.com/auth?state=" + state
}
func (oauthGoogle) GetProfile(context.Context, string, string, string) (*domain.OAuthProfile, error) {
	return &domain.OAuthProfile{Provider: domain.ProviderGoogle, ProviderUserID: "sub", Email: "user@gmail.com", EmailVerified: true, FirstName: "Jane", LastName: "Doe"}, nil
}
func oauthHTTP(t *testing.T, existing bool) *echo.Echo {
	t.Helper()
	repo := &oauthMemoryRepo{values: map[string]string{}}
	users := &oauthUsers{}
	if existing {
		users.user = &domain.User{ID: "user-id", Role: roles.RoleCustomer, IsActive: true}
	}
	svc := application.NewAuthService(users, repo, oauthPublisher{}, oauthTokens{}, oauthGoogle{})
	h := NewAuthHandler(svc, slog.New(slog.NewTextHandler(io.Discard, nil)), "https://rentify.test")
	e := echo.New()
	e.GET("/auth/api/oauth/google", h.GoogleAuth)
	e.GET("/auth/api/oauth/google/callback", h.GoogleCallback)
	e.GET("/auth/api/oauth/profile", h.GetOAuthProfile)
	e.POST("/auth/api/oauth/complete", h.CompleteOAuthRegistration)
	return e
}
func oauthRequest(e *echo.Echo, method, path, body string, cookies ...*http.Cookie) *httptest.ResponseRecorder {
	req := httptest.NewRequest(method, path, strings.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Origin", "https://rentify.test")
	for _, c := range cookies {
		req.AddCookie(c)
	}
	rec := httptest.NewRecorder()
	e.ServeHTTP(rec, req)
	return rec
}
func findCookie(t *testing.T, r *httptest.ResponseRecorder, name string) *http.Cookie {
	t.Helper()
	for _, c := range r.Result().Cookies() {
		if c.Name == name {
			return c
		}
	}
	t.Fatalf("missing cookie %s", name)
	return nil
}
func TestOAuthCallbackRedirectsAndProfileCompletion(t *testing.T) {
	e := oauthHTTP(t, false)
	start := oauthRequest(e, "GET", "/auth/api/oauth/google", "")
	state := findCookie(t, start, "oauth_state")
	if !state.HttpOnly || !state.Secure || state.SameSite != http.SameSiteLaxMode {
		t.Fatal("insecure browser binding cookie")
	}
	callback := oauthRequest(e, "GET", "/auth/api/oauth/google/callback?state="+state.Value+"&code=code", "", state)
	if callback.Code != 303 || callback.Header().Get("Location") != "https://rentify.test/register/complete" {
		t.Fatalf("callback: %d %s", callback.Code, callback.Body)
	}
	pending := findCookie(t, callback, "profile_key")
	if !pending.HttpOnly {
		t.Fatal("insecure profile cookie")
	}
	for range 2 {
		read := oauthRequest(e, "GET", "/auth/api/oauth/profile", "", pending)
		if read.Code != 200 || read.Header().Get("Cache-Control") != "no-store" {
			t.Fatalf("read: %d %s", read.Code, read.Body)
		}
		var fields map[string]any
		if err := json.Unmarshal(read.Body.Bytes(), &fields); err != nil {
			t.Fatal(err)
		}
		if len(fields) != 3 || fields["email"] != "user@gmail.com" {
			t.Fatalf("unsafe DTO: %v", fields)
		}
	}
	complete := oauthRequest(e, "POST", "/auth/api/oauth/complete", `{"first_name":"Jane","phone":"+48123456789"}`, pending)
	if complete.Code != 201 {
		t.Fatalf("completion: %d %s", complete.Code, complete.Body)
	}
	findCookie(t, complete, "access_token")
	findCookie(t, complete, "refresh_token")
	if findCookie(t, complete, "profile_key").MaxAge != -1 {
		t.Fatal("pending cookie not deleted")
	}
	if r := oauthRequest(e, "GET", "/auth/api/oauth/profile", "", pending); r.Code != 401 {
		t.Fatalf("consumed key: %d", r.Code)
	}
}
func TestOAuthExistingUserRedirect(t *testing.T) {
	e := oauthHTTP(t, true)
	state := findCookie(t, oauthRequest(e, "GET", "/auth/api/oauth/google", ""), "oauth_state")
	r := oauthRequest(e, "GET", "/auth/api/oauth/google/callback?state="+state.Value+"&code=code", "", state)
	if r.Code != 303 || r.Header().Get("Location") != "https://rentify.test/oauth/success" {
		t.Fatalf("callback: %d %s", r.Code, r.Body)
	}
}
func TestOAuthErrorsAndOrigin(t *testing.T) {
	e := oauthHTTP(t, false)
	if r := oauthRequest(e, "GET", "/auth/api/oauth/profile", ""); r.Code != 401 || !strings.Contains(r.Body.String(), "oauth_registration_expired") {
		t.Fatalf("missing key: %d %s", r.Code, r.Body)
	}
	for _, origin := range []string{"https://evil.test", ""} {
		req := httptest.NewRequest("POST", "/auth/api/oauth/complete", strings.NewReader(`{}`))
		req.Header.Set("Origin", origin)
		req.Header.Set("Content-Type", "application/json")
		r := httptest.NewRecorder()
		e.ServeHTTP(r, req)
		if r.Code != 403 {
			t.Fatalf("origin %q: %d", origin, r.Code)
		}
	}
	r := oauthRequest(e, "GET", "/auth/api/oauth/google/callback?code=code&state=foreign", "")
	if r.Code != 303 || !strings.Contains(r.Header().Get("Location"), "oauth_invalid_state") {
		t.Fatalf("invalid callback: %d %s", r.Code, r.Header())
	}
}

func TestOAuthCancellationConsumesBoundTransaction(t *testing.T) {
	e := oauthHTTP(t, false)
	state := findCookie(t, oauthRequest(e, "GET", "/auth/api/oauth/google", ""), "oauth_state")
	path := "/auth/api/oauth/google/callback?state=" + state.Value + "&error=access_denied"
	canceled := oauthRequest(e, "GET", path, "", state)
	if canceled.Code != 303 || !strings.Contains(canceled.Header().Get("Location"), "oauth_canceled") {
		t.Fatalf("cancellation: %d %s", canceled.Code, canceled.Header())
	}
	if findCookie(t, canceled, "oauth_state").MaxAge != -1 {
		t.Fatal("state cookie was not cleared")
	}
	replay := oauthRequest(e, "GET", path, "", state)
	if !strings.Contains(replay.Header().Get("Location"), "oauth_invalid_state") {
		t.Fatal("cancelled transaction could be replayed")
	}
}

// OAuth registration publishes welcome through the same application port as
// password registration; the HTTP fixture needs a publisher as well.
type oauthPublisher struct{}

func (oauthPublisher) PublishEvent(string, []byte) error { return nil }
