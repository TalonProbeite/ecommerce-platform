package application

import (
	"context"
	"testing"
	"time"

	"shop/auth/internal/domain"
	"shop/auth/internal/transport/http/dto"
	"shop/shared/events"
)

type MockProfileUserRepo struct {
	GetByIDProfileFunc  func(context.Context, string) (*domain.User, error)
	UpdateProfileFunc   func(context.Context, string, string, string, string, string) error
	GetPassByUserIDFunc func(context.Context, string) (string, error)
	ResetPasswordFunc   func(context.Context, string, string) error
}

func (m *MockProfileUserRepo) GetByIDProfile(ctx context.Context, userID string) (*domain.User, error) {
	if m.GetByIDProfileFunc == nil {
		unset("GetByIDProfileFunc")
	}
	return m.GetByIDProfileFunc(ctx, userID)
}

func (m *MockProfileUserRepo) UpdateProfile(
	ctx context.Context,
	userID, email, firstName, lastName, phone string,
) error {
	if m.UpdateProfileFunc == nil {
		unset("UpdateProfileFunc")
	}
	return m.UpdateProfileFunc(ctx, userID, email, firstName, lastName, phone)
}

func (m *MockProfileUserRepo) GetPassByUserID(ctx context.Context, userID string) (string, error) {
	if m.GetPassByUserIDFunc == nil {
		unset("GetPassByUserIDFunc")
	}
	return m.GetPassByUserIDFunc(ctx, userID)
}

func (m *MockProfileUserRepo) ResetPassword(ctx context.Context, userID, pass string) error {
	if m.ResetPasswordFunc == nil {
		unset("ResetPasswordFunc")
	}
	return m.ResetPasswordFunc(ctx, userID, pass)
}

var _ domain.ProfileUserRepository = (*MockProfileUserRepo)(nil)

type profileUpdate struct {
	userID    string
	email     string
	firstName string
	lastName  string
	phone     string
}

type profileFixture struct {
	svc *ProfileService

	user    *domain.User
	session *domain.Session
	fail    map[string]error

	calls       []string
	updated     *profileUpdate
	resetPass   string
	revokedUser string
	saved       map[string]savedEntry
	events      []publishedEvent
}

func (f *profileFixture) hit(name string) error {
	f.calls = append(f.calls, name)
	return f.fail[name]
}

func (f *profileFixture) called(name string) bool {
	for _, call := range f.calls {
		if call == name {
			return true
		}
	}
	return false
}

func armProfileErrCase(f *profileFixture, c errCase) {
	if c.failAt == "" {
		return
	}
	err := c.failErr
	if err == nil {
		err = errBoom
	}
	f.fail[c.failAt] = err
}

func newProfileFixture(t *testing.T) *profileFixture {
	t.Helper()

	f := &profileFixture{
		user: &domain.User{
			ID:        testUserID,
			Email:     testEmail,
			FirstName: "John",
			LastName:  "Doe",
			Phone:     "+123456789",
			Password:  testPasswordHash(t),
			IsActive:  true,
		},
		session: &domain.Session{
			ID:           "sess-1",
			UserID:       testUserID,
			AccessToken:  testAccessToken,
			RefreshToken: "refresh-token",
		},
		fail:  map[string]error{},
		saved: map[string]savedEntry{},
	}

	users := &MockProfileUserRepo{
		GetByIDProfileFunc: func(_ context.Context, userID string) (*domain.User, error) {
			if err := f.hit("users.GetByIDProfile"); err != nil {
				return nil, err
			}
			return f.user, nil
		},
		UpdateProfileFunc: func(
			_ context.Context,
			userID, email, firstName, lastName, phone string,
		) error {
			if err := f.hit("users.UpdateProfile"); err != nil {
				return err
			}
			f.updated = &profileUpdate{
				userID:    userID,
				email:     email,
				firstName: firstName,
				lastName:  lastName,
				phone:     phone,
			}
			return nil
		},
		GetPassByUserIDFunc: func(_ context.Context, userID string) (string, error) {
			if err := f.hit("users.GetPassByUserID"); err != nil {
				return "", err
			}
			return f.user.Password, nil
		},
		ResetPasswordFunc: func(_ context.Context, userID, pass string) error {
			if err := f.hit("users.ResetPassword"); err != nil {
				return err
			}
			f.resetPass = pass
			return nil
		},
	}

	sess := &MockSessionRepo{
		SaveEntryFunc: func(_ context.Context, key, value string, ttl time.Duration) error {
			if err := f.hit("sess.SaveEntry"); err != nil {
				return err
			}
			f.saved[key] = savedEntry{
				value: value,
				ttl:   ttl,
			}
			return nil
		},
		GetSessionByRefreshTokenFunc: func(_ context.Context, refreshToken string) (*domain.Session, error) {
			if err := f.hit("sess.GetSessionByRefreshToken"); err != nil {
				return nil, err
			}
			if f.session == nil || f.session.RefreshToken != refreshToken {
				return nil, errKeyNotFound
			}
			cp := *f.session
			return &cp, nil
		},
		RevokeAllSessionsFunc: func(_ context.Context, userID string) error {
			if err := f.hit("sess.RevokeAllSessions"); err != nil {
				return err
			}
			f.revokedUser = userID
			return nil
		},
	}

	pub := &MockPublisher{
		PublishEventFunc: func(key string, payload []byte) error {
			if err := f.hit("pub.PublishEvent"); err != nil {
				return err
			}
			f.events = append(f.events, publishedEvent{
				key:     key,
				payload: append([]byte(nil), payload...),
			})
			return nil
		},
	}

	f.svc = NewProfileService(users, sess, pub)
	return f
}

func assertProfileNotCalled(t *testing.T, f *profileFixture, names ...string) {
	t.Helper()

	for _, name := range names {
		if f.called(name) {
			t.Errorf("%s must not be called; calls: %v", name, f.calls)
		}
	}
}

func profileEventPayload(t *testing.T, f *profileFixture, key string) []byte {
	t.Helper()

	for _, event := range f.events {
		if event.key == key {
			return event.payload
		}
	}

	t.Fatalf("event %q was not published; events: %v", key, f.events)
	return nil
}

func TestProfileService_GetUserProfile(t *testing.T) {
	tests := []struct {
		name      string
		setup     func(*profileFixture)
		notCalled []string
		errCase
	}{
		{
			name: "success",
		},
		{
			name:    "repository error",
			errCase: errCase{failAt: "users.GetByIDProfile", wantIs: errBoom}, // repository error is now propagated as is
		},
		{
			name: "user not found",
			setup: func(f *profileFixture) {
				f.user = nil
			},
			errCase: errCase{wantIs: domain.ErrUserNotFound},
		},
		{
			name: "inactive user",
			setup: func(f *profileFixture) {
				f.user.IsActive = false
			},
			errCase: errCase{wantIs: domain.ErrUserNotActive},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newProfileFixture(t)
			armProfileErrCase(f, tt.errCase)
			if tt.setup != nil {
				tt.setup(f)
			}

			profile, err := f.svc.GetUserProfile(testCtx(), testUserID)
			if tt.check(t, err) {
				assertProfileNotCalled(t, f, tt.notCalled...)
				return
			}

			if profile.Email != f.user.Email ||
				profile.FirstName != f.user.FirstName ||
				profile.LastName != f.user.LastName ||
				profile.Phone != f.user.Phone {
				t.Errorf("unexpected profile: %+v", profile)
			}
			if profile.Email == "" || profile.FirstName == "" ||
				profile.LastName == "" || profile.Phone == "" {
				t.Errorf("profile contains empty fields: %+v", profile)
			}
		})
	}
}

func TestProfileService_PatchUserProfile(t *testing.T) {
	email := "new@example.com"
	firstName := "Jane"
	lastName := "Smith"
	phone := "+987654321"

	tests := []struct {
		name      string
		userData  *dto.PatchUser
		want      profileUpdate
		notCalled []string
		errCase
	}{
		{
			name: "success with all fields",
			userData: &dto.PatchUser{
				Email:     &email,
				FirstName: &firstName,
				LastName:  &lastName,
				Phone:     &phone,
			},
			want: profileUpdate{
				userID: testUserID, email: email, firstName: firstName, lastName: lastName, phone: phone,
			},
		},
		{
			name: "success without email",
			userData: &dto.PatchUser{
				FirstName: &firstName,
				LastName:  &lastName,
				Phone:     &phone,
			},
			want: profileUpdate{
				userID: testUserID, firstName: firstName, lastName: lastName, phone: phone,
			},
			notCalled: []string{"sess.SaveEntry", "pub.PublishEvent"},
		},
		{
			name: "success with email only",
			userData: &dto.PatchUser{
				Email: &email,
			},
			want: profileUpdate{
				userID: testUserID, email: email,
			},
		},
		{
			name: "success with first name only",
			userData: &dto.PatchUser{
				FirstName: &firstName,
			},
			want: profileUpdate{
				userID: testUserID, firstName: firstName,
			},
		},
		{
			name: "update error",
			userData: &dto.PatchUser{
				FirstName: &firstName,
			},
			errCase:   errCase{failAt: "users.UpdateProfile", wantMsg: "error updating profile"},
			notCalled: []string{"sess.SaveEntry", "pub.PublishEvent"},
		},
		{
			name: "verification code save error",
			userData: &dto.PatchUser{
				Email: &email,
			},
			errCase:   errCase{failAt: "sess.SaveEntry", wantMsg: "error saving verification code"},
			notCalled: []string{"pub.PublishEvent"},
		},
		{
			name: "publisher error",
			userData: &dto.PatchUser{
				Email: &email,
			},
			errCase: errCase{failAt: "pub.PublishEvent", wantMsg: "error while publishing event"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newProfileFixture(t)
			armProfileErrCase(f, tt.errCase)

			err := f.svc.PatchUserProfile(testCtx(), testUserID, tt.userData)
			if tt.check(t, err) {
				assertProfileNotCalled(t, f, tt.notCalled...)
				return
			}

			if f.updated == nil {
				t.Fatal("profile was not updated")
			}
			if *f.updated != tt.want {
				t.Errorf("updated profile = %+v, want %+v", *f.updated, tt.want)
			}

			if tt.want.email != "" {
				entry, ok := f.saved["ver:"+testUserID]
				if !ok || entry.value == "" || entry.ttl != 15*time.Minute {
					t.Fatalf("verification entry = %+v (found=%t), want non-empty code with ttl 15m", entry, ok)
				}

				event := decode[events.UserRegisteredEvent](
					t,
					profileEventPayload(t, f, events.UserRegisteredEventKey),
				)
				if event.Email != tt.want.email || event.Code != entry.value {
					t.Errorf("event %+v does not match email/saved code %q", event, entry.value)
				}
			}
		})
	}
}

func TestProfileService_ResetPassword(t *testing.T) {
	const refresh = "refresh-token"

	tests := []struct {
		name      string
		oldPass   string
		access    string
		setup     func(*profileFixture)
		notCalled []string
		errCase
	}{
		{
			name:    "success",
			oldPass: testPassword,
			access:  testAccessToken,
		},
		{
			name:    "password repository error",
			oldPass: testPassword,
			access:  testAccessToken,
			errCase: errCase{
				failAt:  "users.GetPassByUserID",
				wantMsg: "error while searching for the password",
			},
			notCalled: []string{
				"users.ResetPassword",
				"sess.GetSessionByRefreshToken",
				"sess.RevokeAllSessions",
			},
		},
		{
			name:    "invalid old password",
			oldPass: "WrongPassword1!",
			access:  testAccessToken,
			errCase: errCase{
				wantIs: domain.ErrInvalidCredentials,
			},
			notCalled: []string{
				"users.ResetPassword",
				"sess.GetSessionByRefreshToken",
				"sess.RevokeAllSessions",
			},
		},
		{
			name:    "password reset error",
			oldPass: testPassword,
			access:  testAccessToken,
			errCase: errCase{
				failAt:  "users.ResetPassword",
				wantMsg: "failed to change password",
			},
			notCalled: []string{
				"sess.RevokeAllSessions",
			},
		},
		{
			name:    "session repository error",
			oldPass: testPassword,
			access:  testAccessToken,
			errCase: errCase{
				failAt:  "sess.GetSessionByRefreshToken",
				wantMsg: "error searching for session",
			},
			notCalled: []string{"users.ResetPassword", "sess.RevokeAllSessions"},
		},
		{
			name:    "access token does not belong to session",
			oldPass: testPassword,
			access:  "someone-elses-access",
			errCase: errCase{
				wantMsg: "access token does not belong to session",
			},
			notCalled: []string{"users.ResetPassword", "sess.RevokeAllSessions"},
		},
		{
			name:    "revoke sessions error",
			oldPass: testPassword,
			access:  testAccessToken,
			errCase: errCase{
				failAt:  "sess.RevokeAllSessions",
				wantMsg: "error occurred while revoking all auth sessions",
			},
		},
		{
			name:    "refresh token does not resolve session",
			oldPass: testPassword,
			access:  testAccessToken,
			setup: func(f *profileFixture) {
				f.session.RefreshToken = "other-refresh"
			},
			errCase:   errCase{wantMsg: "error searching for session"},
			notCalled: []string{"users.ResetPassword", "sess.RevokeAllSessions"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			f := newProfileFixture(t)
			armProfileErrCase(f, tt.errCase)
			if tt.setup != nil {
				tt.setup(f)
			}

			err := f.svc.ResetPassword(testCtx(), testUserID, tt.oldPass, "NewPassword123!", refresh, tt.access)
			if tt.check(t, err) {
				assertProfileNotCalled(t, f, tt.notCalled...)
				return
			}

			if f.resetPass == "" {
				t.Fatal("new password was not saved")
			}
			if f.resetPass == "NewPassword123!" {
				t.Error("new password must be stored hashed")
			}
			if f.revokedUser != testUserID {
				t.Errorf("revoked user id = %q, want %q", f.revokedUser, testUserID)
			}
		})
	}
}
