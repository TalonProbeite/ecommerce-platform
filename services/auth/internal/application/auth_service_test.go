package application

import (
	"slices"
	"testing"

	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"

	"github.com/google/uuid"
	"github.com/jackc/pgx/v5/pgconn"
)

func TestAuthService_Registration(t *testing.T) {
	req := &dto.RegisterRequest{
		Email:     testEmail,
		Password:  testPassword,
		FirstName: "John",
		LastName:  "Doe",
		Phone:     "+123456789",
	}
	verKey := "ver:" + testUserID

	tests := []struct {
		name      string
		setup     func(*fixture)
		notCalled []string
		errCase
	}{
		{name: "success"},
		{
			name: "duplicate email",
			errCase: errCase{
				failAt:  "users.Create",
				failErr: &pgconn.PgError{Code: "23505"},
				wantIs:  domain.ErrEmailAlreadyExists,
			},
			notCalled: []string{"pub.PublishEvent", "sess.CreateSession", "sess.SaveEntry"},
		},
		{
			name:      "repository error",
			errCase:   errCase{failAt: "users.Create", wantMsg: "error while trying to save user"},
			notCalled: []string{"pub.PublishEvent", "sess.CreateSession", "sess.SaveEntry"},
		},
		{
			name:      "repository returned nil uuid",
			setup:     func(f *fixture) { f.newUserID = uuid.Nil },
			errCase:   errCase{wantMsg: "empty user id"},
			notCalled: []string{"pub.PublishEvent", "sess.CreateSession", "sess.SaveEntry"},
		},
		{
			name:    "publisher error",
			errCase: errCase{failAt: "pub.PublishEvent", wantMsg: "error while publishing event"},
		},
		{
			name:    "session creation error",
			errCase: errCase{failAt: "sess.CreateSession", wantMsg: "error saving auth session"},
		},
		{
			name:    "verification code save error",
			errCase: errCase{failAt: "sess.SaveEntry", wantMsg: "error saving verification code"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newFixture(t)
			tt.arm(f)
			if tt.setup != nil {
				tt.setup(f)
			}

			tokens, err := f.svc.Registration(testCtx(), req)
			if tt.check(t, err) {
				assertNotCalled(t, f, tt.notCalled...)
				return
			}

			assertTokenPair(t, f, tokens, testUserID, domain.RoleCustomer)

			u := f.createdUser
			if u.Email != req.Email || u.FirstName != req.FirstName ||
				u.LastName != req.LastName || u.Phone != req.Phone {
				t.Errorf("unexpected created user: %+v", u)
			}
			if u.Role != domain.RoleCustomer || !u.IsActive || u.IsEmailVerified {
				t.Errorf("wrong defaults: role=%s active=%t verified=%t", u.Role, u.IsActive, u.IsEmailVerified)
			}
			if u.Password == "" || u.Password == req.Password {
				t.Error("password must be stored hashed")
			}

			ver, ok := f.saved[verKey]
			if !ok || ver.value == "" || ver.ttl != verificationCodeTTL {
				t.Errorf("verification entry = %+v (found=%t), want non-empty code with ttl %s", ver, ok, verificationCodeTTL)
			}

			ev := decode[domain.UserRegisteredEvent](t, f.eventPayload(t, domain.UserRegisteredEventKey))
			if ev.Email != req.Email || ev.Code != ver.value {
				t.Errorf("event %+v does not match email/saved code %q", ev, ver.value)
			}
		})
	}
}

func TestAuthService_Login(t *testing.T) {
	tests := []struct {
		name      string
		password  string
		setup     func(*fixture)
		notCalled []string
		errCase
	}{
		{
			name:     "success",
			password: testPassword,
			setup:    func(f *fixture) { f.user.Role = domain.RoleAdmin }, // role must reach the token
		},
		{
			name:     "repository error",
			password: testPassword,
			errCase:  errCase{failAt: "users.GetByEmail", wantMsg: "error while searching for user"},
		},
		{
			name:      "user not found",
			password:  testPassword,
			setup:     func(f *fixture) { f.user = nil },
			errCase:   errCase{wantMsg: "user not found"},
			notCalled: []string{"users.GetStatus", "sess.CreateSession"},
		},
		{
			name:      "oauth user has no password",
			password:  testPassword,
			setup:     func(f *fixture) { f.user.Password = "" },
			errCase:   errCase{wantMsg: "oauth"},
			notCalled: []string{"users.GetStatus", "sess.CreateSession"},
		},
		{
			name:      "incorrect password",
			password:  "WrongPassword1!",
			errCase:   errCase{wantIs: domain.ErrInvalidCredentials},
			notCalled: []string{"users.GetStatus", "sess.CreateSession"},
		},
		{
			name:     "status repository error",
			password: testPassword,
			errCase:  errCase{failAt: "users.GetStatus", wantMsg: "error checking user status"},
		},
		{
			name:      "inactive user",
			password:  testPassword,
			setup:     func(f *fixture) { f.userActive = false },
			errCase:   errCase{wantMsg: "not active"},
			notCalled: []string{"sess.CreateSession", "tokens.GenerateToken"},
		},
		{
			name:     "session creation error",
			password: testPassword,
			errCase:  errCase{failAt: "sess.CreateSession", wantMsg: "error saving auth session"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newFixture(t)
			tt.arm(f)
			if tt.setup != nil {
				tt.setup(f)
			}

			tokens, err := f.svc.Login(testCtx(), &dto.LoginRequest{Email: testEmail, Password: tt.password})
			if tt.check(t, err) {
				assertNotCalled(t, f, tt.notCalled...)
				return
			}

			assertTokenPair(t, f, tokens, testUserID, domain.RoleAdmin)
		})
	}
}

func TestAuthService_VerifyEmail(t *testing.T) {
	verKey := "ver:" + testUserID

	tests := []struct {
		name      string
		code      string
		notCalled []string
		errCase
	}{
		{name: "success", code: "123456"},
		{
			name:      "code lookup error",
			code:      "123456",
			errCase:   errCase{failAt: "sess.GetValue", wantMsg: "error when trying to get email confirmation code"},
			notCalled: []string{"users.SetVerified", "pub.PublishEvent"},
		},
		{
			name:      "invalid code",
			code:      "000000",
			errCase:   errCase{wantMsg: "invalid verification code"},
			notCalled: []string{"users.SetVerified", "sess.DeleteEntry", "pub.PublishEvent"},
		},
		{
			name:      "set verified error",
			code:      "123456",
			errCase:   errCase{failAt: "users.SetVerified", wantMsg: "error updating email confirmation field"},
			notCalled: []string{"sess.DeleteEntry", "pub.PublishEvent"},
		},
		{
			name:      "code delete error",
			code:      "123456",
			errCase:   errCase{failAt: "sess.DeleteEntry", wantMsg: "error deleting verification code"},
			notCalled: []string{"pub.PublishEvent"},
		},
		{
			name:    "publisher error",
			code:    "123456",
			errCase: errCase{failAt: "pub.PublishEvent", wantMsg: "error while publishing verified event"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newFixture(t)
			f.store[verKey] = "123456"
			tt.arm(f)

			err := f.svc.VerifyEmail(testCtx(), tt.code, testUserID)
			if tt.check(t, err) {
				assertNotCalled(t, f, tt.notCalled...)
				return
			}

			if !slices.Contains(f.deleted, verKey) {
				t.Errorf("verification code %q must be deleted; deleted: %v", verKey, f.deleted)
			}
			ev := decode[domain.UserEmailVerifiedEvent](t, f.eventPayload(t, domain.UserEmailVerifiedEventKey))
			if ev.Email != testEmail || ev.Name != "John" {
				t.Errorf("unexpected event: %+v", ev)
			}
		})
	}
}

func TestAuthService_Refresh(t *testing.T) {
	tests := []struct {
		name      string
		setup     func(*fixture)
		notCalled []string
		errCase
	}{
		{
			name:  "success",
			setup: func(f *fixture) { f.user.Role = domain.RoleAdmin }, // role comes from the user, not the session
		},
		{
			name:      "session not found",
			errCase:   errCase{failAt: "sess.GetSessionByRefreshToken", wantMsg: "error searching for refresh session"},
			notCalled: []string{"users.GetByID", "sess.UpdateSessionTokens"},
		},
		{
			name:      "user repository error",
			errCase:   errCase{failAt: "users.GetByID", wantMsg: "error searching for user"},
			notCalled: []string{"sess.UpdateSessionTokens"},
		},
		{
			name:      "user not found",
			setup:     func(f *fixture) { f.user = nil },
			errCase:   errCase{wantMsg: "user not found"},
			notCalled: []string{"sess.UpdateSessionTokens"},
		},
		{
			name:      "inactive user",
			setup:     func(f *fixture) { f.user.IsActive = false },
			errCase:   errCase{wantMsg: "not active"},
			notCalled: []string{"tokens.GenerateToken", "sess.UpdateSessionTokens"},
		},
		{
			name:      "token generation error",
			errCase:   errCase{failAt: "tokens.GenerateToken", wantMsg: "access key generation error"},
			notCalled: []string{"sess.UpdateSessionTokens"},
		},
		{
			name:    "session update error",
			errCase: errCase{failAt: "sess.UpdateSessionTokens", wantMsg: "error updating auth session"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newFixture(t)
			tt.arm(f)
			if tt.setup != nil {
				tt.setup(f)
			}

			tokens, err := f.svc.Refresh(testCtx(), "old-refresh")
			if tt.check(t, err) {
				assertNotCalled(t, f, tt.notCalled...)
				return
			}

			if tokens.AccessToken != testAccessToken {
				t.Errorf("access token = %q, want %q", tokens.AccessToken, testAccessToken)
			}
			if tokens.RefreshToken == "" || tokens.RefreshToken == "old-refresh" {
				t.Errorf("refresh token must be new and non-empty, got %q", tokens.RefreshToken)
			}

			u := f.updated
			if u == nil {
				t.Fatal("session tokens were not updated")
			}
			if u.oldAccess != "old-access" || u.oldRefresh != "old-refresh" {
				t.Errorf("old tokens passed to repo = %q/%q", u.oldAccess, u.oldRefresh)
			}
			if u.session.ID != "sess-1" || u.session.UserID != testUserID {
				t.Errorf("session identity changed: %+v", u.session)
			}
			if u.session.AccessToken != tokens.AccessToken || u.session.RefreshToken != tokens.RefreshToken {
				t.Errorf("stored tokens differ from the returned pair: %+v vs %+v", u.session, tokens)
			}
			if u.accessTTL != accessTokenTTL || u.refreshTTL != refreshTokenTTL {
				t.Errorf("TTLs = %s/%s, want %s/%s", u.accessTTL, u.refreshTTL, accessTokenTTL, refreshTokenTTL)
			}
			assertLastTokenRequest(t, f, testUserID, domain.RoleAdmin)
		})
	}
}

func TestAuthService_Logout(t *testing.T) {
	tests := []struct {
		name      string
		access    string
		notCalled []string
		errCase
	}{
		{name: "success", access: "old-access"},
		{name: "empty access token (expired) still logs out by refresh token", access: ""},
		{
			name:      "session not found",
			access:    "old-access",
			errCase:   errCase{failAt: "sess.GetSessionByRefreshToken", wantMsg: "error searching for session"},
			notCalled: []string{"sess.DeleteSession"},
		},
		{
			name:      "access token belongs to another session",
			access:    "someone-elses-access",
			errCase:   errCase{wantMsg: "does not belong to session"},
			notCalled: []string{"sess.DeleteSession"},
		},
		{
			name:    "session delete error",
			access:  "old-access",
			errCase: errCase{failAt: "sess.DeleteSession", wantMsg: "error occurred while deleting auth session"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newFixture(t)
			tt.arm(f)

			err := f.svc.Logout(testCtx(), "old-refresh", tt.access)
			if tt.check(t, err) {
				assertNotCalled(t, f, tt.notCalled...)
				return
			}

			if f.deletedSession == nil || f.deletedSession.ID != "sess-1" {
				t.Errorf("expected session sess-1 to be deleted, got %+v", f.deletedSession)
			}
		})
	}
}

func TestAuthService_Google(t *testing.T) {
	tests := []struct {
		name      string
		notCalled []string
		errCase
	}{
		{name: "success"},
		{
			name:      "state save error",
			errCase:   errCase{failAt: "sess.SaveEntry", wantMsg: "error when saving state"},
			notCalled: []string{"google.AuthURL"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newFixture(t)
			tt.arm(f)

			url, err := f.svc.Google(testCtx())
			if tt.check(t, err) {
				assertNotCalled(t, f, tt.notCalled...)
				return
			}

			if f.authState == "" {
				t.Fatal("state passed to AuthURL is empty")
			}
			if want := "https://accounts.example/auth?state=" + f.authState; url != want {
				t.Errorf("url = %q, want %q", url, want)
			}
			if e, ok := f.saved["state:"+f.authState]; !ok || e.ttl != oauthStateTTL {
				t.Errorf("state entry = %+v (found=%t), want ttl %s", e, ok, oauthStateTTL)
			}
		})
	}
}

func TestAuthService_GoogleCallback(t *testing.T) {
	const state = "st"
	stateKey := "state:" + state

	tests := []struct {
		name        string
		setup       func(*fixture)
		wantNewUser bool
		notCalled   []string
		errCase
	}{
		{name: "existing user gets tokens"},
		{
			name:        "unknown user gets pending registration",
			setup:       func(f *fixture) { f.oauthMissing = true },
			wantNewUser: true,
		},
		{
			name:      "state not found",
			setup:     func(f *fixture) { delete(f.store, stateKey) },
			errCase:   errCase{wantMsg: "error when searching for state"},
			notCalled: []string{"google.GetProfile"},
		},
		{
			name:      "state delete error",
			errCase:   errCase{failAt: "sess.DeleteEntry", wantMsg: "error deleting used state"},
			notCalled: []string{"google.GetProfile"},
		},
		{
			name:      "google profile error",
			errCase:   errCase{failAt: "google.GetProfile", wantMsg: "error when retrieving profile"},
			notCalled: []string{"users.GetByOAuth"},
		},
		{
			name:      "oauth lookup error",
			errCase:   errCase{failAt: "users.GetByOAuth", wantMsg: "error verifying user"},
			notCalled: []string{"sess.CreateSession", "sess.SaveEntry"},
		},
		{
			name:    "existing user session error",
			errCase: errCase{failAt: "sess.CreateSession", wantMsg: "error saving auth session"},
		},
		{
			name:    "pending profile save error",
			setup:   func(f *fixture) { f.oauthMissing = true },
			errCase: errCase{failAt: "sess.SaveEntry", wantMsg: "profile save error"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newFixture(t)
			f.store[stateKey] = ""
			f.user.Role = domain.RoleAnalyst
			tt.arm(f)
			if tt.setup != nil {
				tt.setup(f)
			}

			res, err := f.svc.GoogleCallback(testCtx(), state, "auth-code")
			if tt.check(t, err) {
				assertNotCalled(t, f, tt.notCalled...)
				return
			}

			if !slices.Contains(f.deleted, stateKey) {
				t.Errorf("used state must be deleted; deleted: %v", f.deleted)
			}

			if !tt.wantNewUser {
				if f.oauthProvider != domain.ProviderGoogle.String() || f.oauthID != f.profile.ProviderUserID {
					t.Errorf("GetByOAuth(%q, %q)", f.oauthProvider, f.oauthID)
				}
				if res.Tokens == nil || res.RegistrationKey != "" {
					t.Fatalf("expected tokens only, got %+v", res)
				}
				assertTokenPair(t, f, *res.Tokens, testUserID, domain.RoleAnalyst)
				return
			}

			if res.Tokens != nil || res.RegistrationKey == "" {
				t.Fatalf("expected registration key only, got %+v", res)
			}
			if f.created != nil {
				t.Error("no session must be created for an unregistered user")
			}
			pending, ok := f.saved["oauth:pending:"+res.RegistrationKey]
			if !ok || pending.ttl != oauthPendingTTL {
				t.Fatalf("pending entry = %+v (found=%t), want ttl %s", pending, ok, oauthPendingTTL)
			}
			if got := decode[domain.OAuthProfile](t, []byte(pending.value)); got != *f.profile {
				t.Errorf("stored profile = %+v, want %+v", got, *f.profile)
			}
		})
	}
}

func TestAuthService_CompleteOAuthRegistration(t *testing.T) {
	const key = "pending-key"
	pendingKey := "oauth:pending:" + key

	tests := []struct {
		name          string
		first, last   string // overrides from the registration form
		pendingValue  string // "" = the valid profile from the fixture
		removePending bool
		notCalled     []string
		errCase
	}{
		{name: "success keeps profile names"},
		{name: "success overrides names from the form", first: "John", last: "Doe"},
		{
			name:          "pending profile not found",
			removePending: true,
			errCase:       errCase{wantMsg: "when completing the profile"},
			notCalled:     []string{"users.CreateWithOauth"},
		},
		{
			name:         "invalid pending profile",
			pendingValue: "not-json",
			errCase:      errCase{wantMsg: "deserialization error"},
			notCalled:    []string{"users.CreateWithOauth"},
		},
		{
			name:      "create user error",
			errCase:   errCase{failAt: "users.CreateWithOauth", wantMsg: "error creating oauth user"},
			notCalled: []string{"sess.DeleteEntry", "sess.CreateSession"},
		},
		{
			name:      "pending delete error",
			errCase:   errCase{failAt: "sess.DeleteEntry"},
			notCalled: []string{"sess.CreateSession"},
		},
		{
			name:    "session creation error",
			errCase: errCase{failAt: "sess.CreateSession", wantMsg: "error saving auth session"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newFixture(t)
			f.store[pendingKey] = mustJSON(t, f.profile)
			if tt.pendingValue != "" {
				f.store[pendingKey] = tt.pendingValue
			}
			if tt.removePending {
				delete(f.store, pendingKey)
			}
			tt.arm(f)

			tokens, err := f.svc.CompleteOAuthRegistration(testCtx(), domain.OAuthRegistrationData{
				Key:       key,
				FirstName: tt.first,
				LastName:  tt.last,
				Phone:     "+123456789",
			})
			if tt.check(t, err) {
				assertNotCalled(t, f, tt.notCalled...)
				return
			}

			wantFirst, wantLast := f.profile.FirstName, f.profile.LastName
			if tt.first != "" {
				wantFirst = tt.first
			}
			if tt.last != "" {
				wantLast = tt.last
			}

			u := f.createdUser
			if u.Email != f.profile.Email || u.Phone != "+123456789" ||
				u.Provider != f.profile.Provider || u.ProviderUserID != f.profile.ProviderUserID {
				t.Errorf("unexpected created user: %+v", u)
			}
			if u.FirstName != wantFirst || u.LastName != wantLast {
				t.Errorf("names = %q %q, want %q %q", u.FirstName, u.LastName, wantFirst, wantLast)
			}
			if !slices.Contains(f.deleted, pendingKey) {
				t.Errorf("pending profile must be deleted; deleted: %v", f.deleted)
			}
			assertTokenPair(t, f, tokens, testUserID, domain.RoleCustomer)
		})
	}
}

func TestAuthService_ResendVerCode(t *testing.T) {
	verKey := "ver:" + testUserID

	tests := []struct {
		name      string
		notCalled []string
		errCase
	}{
		{name: "success"},
		{
			name:      "save code error",
			errCase:   errCase{failAt: "sess.SaveEntry", wantMsg: "failed to save code"},
			notCalled: []string{"users.GetEmailByUserID", "pub.PublishEvent"},
		},
		{
			name:      "email lookup error",
			errCase:   errCase{failAt: "users.GetEmailByUserID", wantMsg: "failed to receive email"},
			notCalled: []string{"pub.PublishEvent"},
		},
		{
			name:    "publisher error",
			errCase: errCase{failAt: "pub.PublishEvent", wantMsg: "error while publishing event"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newFixture(t)
			tt.arm(f)

			err := f.svc.ResendVerCode(testCtx(), testUserID)
			if tt.check(t, err) {
				assertNotCalled(t, f, tt.notCalled...)
				return
			}

			ver, ok := f.saved[verKey]
			if !ok || ver.value == "" || ver.ttl != verificationCodeTTL {
				t.Fatalf("verification entry = %+v (found=%t), want non-empty code with ttl %s", ver, ok, verificationCodeTTL)
			}
			ev := decode[domain.UserRegisteredEvent](t, f.eventPayload(t, domain.UserRegisteredEventKey))
			if ev.Email != testEmail || ev.Code != ver.value {
				t.Errorf("event %+v does not match email/saved code %q", ev, ver.value)
			}
		})
	}
}
