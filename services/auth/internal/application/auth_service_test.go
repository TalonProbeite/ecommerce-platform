package application

import (
	"context"
	"encoding/json"
	"errors"
	"log/slog"
	"os"
	"strings"
	"testing"
	"time"

	"shop/auth/internal/domain"
	"shop/auth/internal/infra/crypto"
	"shop/auth/internal/transport/http/dto"

	"github.com/google/uuid"
	"github.com/jackc/pgx/v5/pgconn"
)

func newTestLogger() *slog.Logger {
	return slog.New(
		slog.NewTextHandler(
			os.Stdout,
			&slog.HandlerOptions{
				Level: slog.LevelDebug,
			},
		),
	)
}

func newAuthServiceForTest(
	userRepo *MockUserRepo,
	sessRepo *MockSessionRepo,
	publisher *MockPublisher,
	tokenMng *MockTokenManager,
	oauth *MockGoogleClient,
) *AuthService {
	return NewAuthService(
		userRepo,
		sessRepo,
		publisher,
		tokenMng,
		oauth,
	)
}

func testContext() context.Context {
	return context.Background()
}

func testUser() *domain.User {
	return &domain.User{
		ID:       "user-123",
		Email:    "john@example.com",
		Password: "password-hash",
		Role:     domain.RoleCustomer,
		IsActive: true,
	}
}

func testUserWithHash(hash string) *domain.User {
	user := testUser()
	user.Password = hash
	return user
}

func mustUUID() uuid.UUID {
	id, err := uuid.Parse("11111111-1111-1111-1111-111111111111")
	if err != nil {
		panic(err)
	}
	return id
}

func duplicateEmailError() error {
	return &pgconn.PgError{
		Code:    "23505",
		Message: "duplicate key value violates unique constraint",
	}
}

func assertErrorContains(t *testing.T, err error, want string) {
	t.Helper()

	if err == nil {
		t.Fatalf("expected error containing %q, got nil", want)
	}

	if !strings.Contains(err.Error(), want) {
		t.Fatalf("expected error containing %q, got %q", want, err.Error())
	}
}

func TestAuthService_Registration(t *testing.T) {
	logger := newTestLogger()
	userID := mustUUID()

	tests := []struct {
		name          string
		createErr     error
		returnNilID   bool
		publishErr    error
		tokenErr      error
		saveErrOn     int
		expectError   bool
		expectWrapped error
		expectMessage string
	}{
		{
			name: "success",
		},
		{
			name:          "duplicate email",
			createErr:     duplicateEmailError(),
			expectError:   true,
			expectWrapped: domain.ErrEmailAlreadyExists,
		},
		{
			name:          "repository error",
			createErr:     errors.New("database is unavailable"),
			expectError:   true,
			expectMessage: "error while trying to save user",
		},
		{
			name:        "repository returned nil uuid",
			returnNilID: true,
			expectError: true,
		},
		{
			name:          "publisher error",
			publishErr:    errors.New("rabbitmq unavailable"),
			expectError:   true,
			expectMessage: "error while publishing event",
		},
		{
			name:          "token generation error",
			tokenErr:      errors.New("jwt failure"),
			expectError:   true,
			expectMessage: "access key generation error",
		},
		{
			name:          "save refresh session error",
			saveErrOn:     1,
			expectError:   true,
			expectMessage: "error saving refresh token",
		},
		{
			name:          "save access session error",
			saveErrOn:     2,
			expectError:   true,
			expectMessage: "error saving access token",
		},
		{
			name:          "save verification code error",
			saveErrOn:     3,
			expectError:   true,
			expectMessage: "error saving verification code",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Logf("starting registration scenario=%s", tt.name)

			saveCalls := 0
			var savedKeys []string
			var savedValues []string
			var publishedEvent string
			var publishedPayload []byte

			userRepo := &MockUserRepo{
				Log: logger,
				CreateFunc: func(_ context.Context, user *domain.User) (uuid.UUID, error) {
					t.Logf(
						"Create email=%s role=%s active=%t verified=%t",
						user.Email,
						user.Role,
						user.IsActive,
						user.IsEmailVerified,
					)

					if tt.returnNilID {
						return uuid.Nil, nil
					}
					return userID, tt.createErr
				},
			}

			sessRepo := &MockSessionRepo{
				Log: logger,
				SaveEntryFunc: func(
					_ context.Context,
					key, value string,
					ttl time.Duration,
				) error {
					saveCalls++
					savedKeys = append(savedKeys, key)
					savedValues = append(savedValues, value)

					t.Logf(
						"SaveEntry #%d key=%s value=%s ttl=%s",
						saveCalls,
						key,
						value,
						ttl,
					)

					if saveCalls == tt.saveErrOn {
						return errors.New("redis save failed")
					}
					return nil
				},
			}

			publisher := &MockPublisher{
				Log: logger,
				PublishEventFunc: func(eventKey string, payload []byte) error {
					publishedEvent = eventKey
					publishedPayload = append([]byte(nil), payload...)
					t.Logf("PublishEvent key=%s payload=%s", eventKey, string(payload))
					return tt.publishErr
				},
			}

			tokenMng := &MockTokenManager{
				Log: logger,
				GenerateTokenFunc: func(
					userID string,
					role ...domain.Role,
				) (string, error) {
					t.Logf("GenerateToken user_id=%s role=%v", userID, role)
					if tt.tokenErr != nil {
						return "", tt.tokenErr
					}
					return "access-token", nil
				},
			}

			service := newAuthServiceForTest(
				userRepo,
				sessRepo,
				publisher,
				tokenMng,
				nil,
			)

			result, err := service.Registration(
				testContext(),
				&dto.RegisterRequest{
					Email:     "john@example.com",
					Password:  "Password123!",
					FirstName: "John",
					LastName:  "Doe",
					Phone:     "+123456789",
				},
			)

			if !tt.expectError {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
				if result.AccessToken != "access-token" {
					t.Fatalf("unexpected access token: %q", result.AccessToken)
				}
				if result.RefreshToken == "" {
					t.Fatal("expected non-empty refresh token")
				}
				if saveCalls != 3 {
					t.Fatalf("expected 3 redis saves, got %d", saveCalls)
				}
				if publishedEvent != domain.UserRegistredEventKey {
					t.Fatalf("unexpected event key: %q", publishedEvent)
				}
				if len(publishedPayload) == 0 {
					t.Fatal("expected non-empty event payload")
				}
				if !strings.HasPrefix(savedKeys[0], "refresh:") {
					t.Fatalf("unexpected refresh key: %q", savedKeys[0])
				}
				if savedKeys[1] != "access:access-token" {
					t.Fatalf("unexpected access key: %q", savedKeys[1])
				}
				if savedKeys[2] != "ver:"+userID.String() {
					t.Fatalf("unexpected verification key: %q", savedKeys[2])
				}
				if savedValues[0] != userID.String() || savedValues[1] != userID.String() {
					t.Fatalf("unexpected session values: %#v", savedValues[:2])
				}
				if savedValues[2] == "" {
					t.Fatal("expected verification code")
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if tt.expectWrapped != nil && !errors.Is(err, tt.expectWrapped) {
					t.Fatalf("expected wrapped error %v, got %v", tt.expectWrapped, err)
				}
				if tt.expectMessage != "" && !strings.Contains(err.Error(), tt.expectMessage) {
					t.Fatalf("expected error containing %q, got %q", tt.expectMessage, err.Error())
				}
			}

			t.Logf("finished registration scenario=%s err=%v", tt.name, err)
		})
	}
}

func TestAuthService_Login(t *testing.T) {
	logger := newTestLogger()

	hash, err := crypto.HashPassword("Password123!")
	if err != nil {
		t.Fatal(err)
	}

	tests := []struct {
		name        string
		user        *domain.User
		getErr      error
		status      bool
		statusErr   error
		tokenErr    error
		saveErrOn   int
		password    string
		expectError bool
		expectMsg   string
	}{
		{
			name:     "success",
			user:     testUserWithHash(hash),
			status:   true,
			password: "Password123!",
		},
		{
			name:        "repository error",
			getErr:      errors.New("postgres unavailable"),
			password:    "Password123!",
			expectError: true,
			expectMsg:   "error while searching for user",
		},
		{
			name:        "user not found",
			user:        nil,
			password:    "Password123!",
			expectError: true,
			expectMsg:   "user not found error",
		},
		{
			name: "oauth user",
			user: &domain.User{
				ID:       "oauth-user",
				Email:    "oauth@example.com",
				Password: "",
				Role:     domain.RoleCustomer,
				IsActive: true,
			},
			password:    "Password123!",
			expectError: true,
			expectMsg:   "user registered via oauth",
		},
		{
			name:        "incorrect password",
			user:        testUserWithHash(hash),
			status:      true,
			password:    "WrongPassword!",
			expectError: true,
			expectMsg:   "incorrect password",
		},
		{
			name:        "status repository error",
			user:        testUserWithHash(hash),
			status:      true,
			statusErr:   errors.New("status query failed"),
			password:    "Password123!",
			expectError: true,
			expectMsg:   "error checking user status",
		},
		{
			name:        "inactive user",
			user:        testUserWithHash(hash),
			status:      false,
			password:    "Password123!",
			expectError: true,
			expectMsg:   "the user is not active",
		},
		{
			name:        "token generation error",
			user:        testUserWithHash(hash),
			status:      true,
			tokenErr:    errors.New("jwt failure"),
			password:    "Password123!",
			expectError: true,
			expectMsg:   "access key generation error",
		},
		{
			name:        "refresh save error",
			user:        testUserWithHash(hash),
			status:      true,
			saveErrOn:   1,
			password:    "Password123!",
			expectError: true,
			expectMsg:   "error saving refresh token",
		},
		{
			name:        "access save error",
			user:        testUserWithHash(hash),
			status:      true,
			saveErrOn:   2,
			password:    "Password123!",
			expectError: true,
			expectMsg:   "error saving access token",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Logf("starting login scenario=%s", tt.name)

			saveCalls := 0
			userStatusCalls := 0

			userRepo := &MockUserRepo{
				Log: logger,
				GetByEmailFunc: func(_ context.Context, _ string) (*domain.User, error) {
					return tt.user, tt.getErr
				},
				GetStatusFunc: func(_ context.Context, _ string) (bool, error) {
					userStatusCalls++
					return tt.status, tt.statusErr
				},
			}

			sessRepo := &MockSessionRepo{
				Log: logger,
				SaveEntryFunc: func(
					_ context.Context,
					key, value string,
					ttl time.Duration,
				) error {
					saveCalls++
					t.Logf("SaveEntry #%d key=%s value=%s ttl=%s", saveCalls, key, value, ttl)
					if saveCalls == tt.saveErrOn {
						return errors.New("redis save failed")
					}
					return nil
				},
			}

			tokenMng := &MockTokenManager{
				Log: logger,
				GenerateTokenFunc: func(_ string, _ ...domain.Role) (string, error) {
					if tt.tokenErr != nil {
						return "", tt.tokenErr
					}
					return "access-token", nil
				},
			}

			service := newAuthServiceForTest(
				userRepo,
				sessRepo,
				&MockPublisher{
					Log: logger,
					PublishEventFunc: func(string, []byte) error {
						return nil
					},
				},
				tokenMng,
				nil,
			)

			result, err := service.Login(
				testContext(),
				&dto.LoginRequest{
					Email:    "john@example.com",
					Password: tt.password,
				},
			)

			if !tt.expectError {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
				if result.AccessToken != "access-token" {
					t.Fatalf("unexpected access token: %q", result.AccessToken)
				}
				if result.RefreshToken == "" {
					t.Fatal("expected non-empty refresh token")
				}
				if userStatusCalls != 1 {
					t.Fatalf("expected 1 status call, got %d", userStatusCalls)
				}
				if saveCalls != 2 {
					t.Fatalf("expected 2 redis saves, got %d", saveCalls)
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if tt.expectMsg != "" && !strings.Contains(err.Error(), tt.expectMsg) {
					t.Fatalf("expected error containing %q, got %q", tt.expectMsg, err.Error())
				}
			}

			t.Logf("finished login scenario=%s err=%v", tt.name, err)
		})
	}
}

func TestAuthService_VerifyEmail(t *testing.T) {
	logger := newTestLogger()

	tests := []struct {
		name        string
		code        string
		redisCode   string
		getErr      error
		setErr      error
		deleteErr   error
		publishErr  error
		expectError bool
		expectMsg   string
	}{
		{
			name:      "success",
			code:      "1234567890",
			redisCode: "1234567890",
		},
		{
			name:        "redis get error",
			code:        "1234567890",
			getErr:      errors.New("redis unavailable"),
			expectError: true,
			expectMsg:   "email confirmation code",
		},
		{
			name:        "invalid code",
			code:        "1111111111",
			redisCode:   "1234567890",
			expectError: true,
			expectMsg:   "invalid verification code",
		},
		{
			name:        "set verified error",
			code:        "1234567890",
			redisCode:   "1234567890",
			setErr:      errors.New("postgres update failed"),
			expectError: true,
			expectMsg:   "error updating email confirmation field",
		},
		{
			name:        "delete code error",
			code:        "1234567890",
			redisCode:   "1234567890",
			deleteErr:   errors.New("redis delete failed"),
			expectError: true,
			expectMsg:   "error deleting verification code",
		},
		{
			name:        "publish error",
			code:        "1234567890",
			redisCode:   "1234567890",
			publishErr:  errors.New("rabbit unavailable"),
			expectError: true,
			expectMsg:   "error while publishing verified event",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			getCalls := 0
			setCalls := 0
			deleteCalls := 0
			publishCalls := 0

			userRepo := &MockUserRepo{
				Log: logger,
				SetVerifiedFunc: func(_ context.Context, _ string) (string, string, error) {
					setCalls++
					return "john@example.com", "John", tt.setErr
				},
			}

			sessRepo := &MockSessionRepo{
				Log: logger,
				GetValueFunc: func(_ context.Context, key string) (string, error) {
					getCalls++
					t.Logf("GetValue key=%s", key)
					return tt.redisCode, tt.getErr
				},
				DeleteEntryFunc: func(_ context.Context, key string) error {
					deleteCalls++
					t.Logf("DeleteEntry key=%s", key)
					return tt.deleteErr
				},
			}

			publisher := &MockPublisher{
				Log: logger,
				PublishEventFunc: func(eventKey string, payload []byte) error {
					publishCalls++
					t.Logf("PublishEvent key=%s payload=%s", eventKey, string(payload))
					return tt.publishErr
				},
			}

			service := newAuthServiceForTest(
				userRepo,
				sessRepo,
				publisher,
				nil,
				nil,
			)

			err := service.VerifyEmail(
				testContext(),
				tt.code,
				"user-123",
			)

			if !tt.expectError {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
				if getCalls != 1 || setCalls != 1 || deleteCalls != 1 || publishCalls != 1 {
					t.Fatalf(
						"unexpected call counts get=%d set=%d delete=%d publish=%d",
						getCalls,
						setCalls,
						deleteCalls,
						publishCalls,
					)
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if tt.expectMsg != "" && !strings.Contains(err.Error(), tt.expectMsg) {
					t.Fatalf("expected error containing %q, got %q", tt.expectMsg, err.Error())
				}
			}

			if tt.name == "invalid code" && setCalls != 0 {
				t.Fatalf("SetVerified must not be called, got %d calls", setCalls)
			}

			t.Logf("finished verify scenario=%s err=%v", tt.name, err)
		})
	}
}

func TestAuthService_Refresh(t *testing.T) {
	logger := newTestLogger()

	tests := []struct {
		name        string
		redisErr    error
		user        *domain.User
		userErr     error
		deleteErr   error
		tokenErr    error
		saveErrOn   int
		expectError bool
		expectMsg   string
	}{
		{
			name: "success",
			user: testUser(),
		},
		{
			name:        "redis refresh key error",
			redisErr:    errors.New("redis unavailable"),
			expectError: true,
			expectMsg:   "error searching for key",
		},
		{
			name:        "repository error",
			userErr:     errors.New("postgres unavailable"),
			expectError: true,
			expectMsg:   "error searching for user",
		},
		{
			name:        "user not found",
			user:        nil,
			expectError: true,
			expectMsg:   "user not found error",
		},
		{
			name:        "inactive user",
			user:        &domain.User{ID: "user-123", Role: domain.RoleCustomer, IsActive: false},
			expectError: true,
			expectMsg:   "the user is not active",
		},
		{
			name:        "delete old refresh error",
			user:        testUser(),
			deleteErr:   errors.New("redis delete failed"),
			expectError: true,
			expectMsg:   "error deleting stale session",
		},
		{
			name:        "token generation error",
			user:        testUser(),
			tokenErr:    errors.New("jwt failure"),
			expectError: true,
			expectMsg:   "access key generation error",
		},
		{
			name:        "save refresh error",
			user:        testUser(),
			saveErrOn:   1,
			expectError: true,
			expectMsg:   "error saving refresh token",
		},
		{
			name:        "save access error",
			user:        testUser(),
			saveErrOn:   2,
			expectError: true,
			expectMsg:   "error saving access token",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			getCalls := 0
			deleteCalls := 0
			saveCalls := 0

			userRepo := &MockUserRepo{
				Log: logger,
				GetByIDFunc: func(_ context.Context, userID string) (*domain.User, error) {
					t.Logf("GetByID user_id=%s", userID)
					return tt.user, tt.userErr
				},
			}

			sessRepo := &MockSessionRepo{
				Log: logger,
				GetValueFunc: func(_ context.Context, key string) (string, error) {
					getCalls++
					t.Logf("GetValue key=%s", key)
					return "user-123", tt.redisErr
				},
				DeleteEntryFunc: func(_ context.Context, key string) error {
					deleteCalls++
					t.Logf("DeleteEntry key=%s", key)
					return tt.deleteErr
				},
				SaveEntryFunc: func(_ context.Context, key, value string, ttl time.Duration) error {
					saveCalls++
					t.Logf("SaveEntry #%d key=%s value=%s ttl=%s", saveCalls, key, value, ttl)
					if saveCalls == tt.saveErrOn {
						return errors.New("redis save failed")
					}
					return nil
				},
			}

			tokenMng := &MockTokenManager{
				Log: logger,
				GenerateTokenFunc: func(userID string, role ...domain.Role) (string, error) {
					t.Logf("GenerateToken user_id=%s role=%v", userID, role)
					if tt.tokenErr != nil {
						return "", tt.tokenErr
					}
					return "access-token", nil
				},
			}

			service := newAuthServiceForTest(
				userRepo,
				sessRepo,
				nil,
				tokenMng,
				nil,
			)

			result, err := service.Refresh(testContext(), "old-refresh-token")

			if !tt.expectError {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
				if result.AccessToken != "access-token" || result.RefreshToken == "" {
					t.Fatalf("unexpected token pair: %#v", result)
				}
				if getCalls != 1 || deleteCalls != 1 || saveCalls != 2 {
					t.Fatalf("unexpected calls get=%d delete=%d save=%d", getCalls, deleteCalls, saveCalls)
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if tt.expectMsg != "" && !strings.Contains(err.Error(), tt.expectMsg) {
					t.Fatalf("expected error containing %q, got %q", tt.expectMsg, err.Error())
				}
			}

			t.Logf("finished refresh scenario=%s err=%v", tt.name, err)
		})
	}
}

func TestAuthService_Logout(t *testing.T) {
	logger := newTestLogger()

	tests := []struct {
		name        string
		deleteOn    int
		expectError bool
		expectMsg   string
	}{
		{name: "success"},
		{
			name:        "refresh delete error",
			deleteOn:    1,
			expectError: true,
			expectMsg:   "refresh token",
		},
		{
			name:        "access delete error",
			deleteOn:    2,
			expectError: true,
			expectMsg:   "access token",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			deleteCalls := 0

			sessRepo := &MockSessionRepo{
				Log: logger,
				DeleteEntryFunc: func(_ context.Context, key string) error {
					deleteCalls++
					t.Logf("DeleteEntry #%d key=%s", deleteCalls, key)
					if deleteCalls == tt.deleteOn {
						return errors.New("redis delete failed")
					}
					return nil
				},
			}

			service := newAuthServiceForTest(nil, sessRepo, nil, nil, nil)

			err := service.Logout(
				testContext(),
				"refresh-token",
				"access-token",
			)

			if !tt.expectError {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
				if deleteCalls != 2 {
					t.Fatalf("expected 2 delete calls, got %d", deleteCalls)
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if !strings.Contains(err.Error(), tt.expectMsg) {
					t.Fatalf("expected error containing %q, got %q", tt.expectMsg, err.Error())
				}
			}
		})
	}
}

func TestAuthService_Google(t *testing.T) {
	logger := newTestLogger()

	tests := []struct {
		name        string
		saveErr     error
		expectError bool
		expectMsg   string
	}{
		{name: "success"},
		{
			name:        "state save error",
			saveErr:     errors.New("redis unavailable"),
			expectError: true,
			expectMsg:   "error when saving state",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			sessRepo := &MockSessionRepo{
				Log: newTestLogger(),
				SaveEntryFunc: func(_ context.Context, key, value string, ttl time.Duration) error {
					t.Logf("SaveEntry key=%s value=%s ttl=%s", key, value, ttl)
					return tt.saveErr
				},
			}

			oauth := &MockGoogleClient{
				Log: logger,
				AuthURLFunc: func(state string) string {
					t.Logf("AuthURL state=%s", state)
					return "https://google.example/auth?state=" + state
				},
			}

			service := newAuthServiceForTest(nil, sessRepo, nil, nil, oauth)

			url, err := service.Google(testContext())

			if !tt.expectError {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
				if !strings.HasPrefix(url, "https://google.example/auth?state=") {
					t.Fatalf("unexpected oauth url: %q", url)
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if tt.expectMsg != "" && !strings.Contains(err.Error(), tt.expectMsg) {
					t.Fatalf("expected error containing %q, got %q", tt.expectMsg, err.Error())
				}
			}
		})
	}
}

func TestAuthService_GoogleCallback(t *testing.T) {
	logger := newTestLogger()
	profile := &domain.OAuthProfile{
		Provider:       domain.ProviderGoogle,
		ProviderUserID: "google-123",
		Email:          "john@example.com",
		FirstName:      "John",
		LastName:       "Doe",
	}

	existingUser := &domain.User{
		ID:       "user-123",
		Email:    "john@example.com",
		Role:     domain.RoleCustomer,
		IsActive: true,
	}

	tests := []struct {
		name               string
		stateGetErr        error
		stateDeleteErr     error
		profileErr         error
		getOAuthErr        error
		existingUser       *domain.User
		tokenErr           error
		saveErrOn          int
		expectError        bool
		expectMsg          string
		expectRegistration bool
	}{
		{
			name:        "state not found",
			stateGetErr: errors.New("state expired"),
			expectError: true,
			expectMsg:   "error when searching for state",
		},
		{
			name:           "state delete error",
			stateDeleteErr: errors.New("redis delete failed"),
			expectError:    true,
			expectMsg:      "error deleting used state",
		},
		{
			name:        "google profile error",
			profileErr:  errors.New("google exchange failed"),
			expectError: true,
			expectMsg:   "error when retrieving profile",
		},
		{
			name:        "oauth lookup error",
			getOAuthErr: errors.New("postgres unavailable"),
			expectError: true,
			expectMsg:   "error verifying user",
		},
		{
			name:         "existing oauth user success",
			existingUser: existingUser,
		},
		{
			name:         "existing oauth token error",
			existingUser: existingUser,
			tokenErr:     errors.New("jwt failure"),
			expectError:  true,
			expectMsg:    "access key generation error",
		},
		{
			name:         "existing oauth refresh save error",
			existingUser: existingUser,
			saveErrOn:    1,
			expectError:  true,
			expectMsg:    "error saving refresh token",
		},
		{
			name:         "existing oauth access save error",
			existingUser: existingUser,
			saveErrOn:    2,
			expectError:  true,
			expectMsg:    "error saving access token",
		},
		{
			name:               "new oauth profile save error",
			getOAuthErr:        domain.ErrUserNotFound,
			saveErrOn:          1,
			expectError:        true,
			expectMsg:          "profile save error",
			expectRegistration: false,
		},
		{
			name:               "new oauth registration pending",
			getOAuthErr:        domain.ErrUserNotFound,
			expectRegistration: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			stateDeleteCalls := 0
			saveCalls := 0
			var savedKey string
			var savedValue string

			sessRepo := &MockSessionRepo{
				Log: logger,
				GetValueFunc: func(_ context.Context, key string) (string, error) {
					t.Logf("GetValue key=%s", key)
					return "", tt.stateGetErr
				},
				DeleteEntryFunc: func(_ context.Context, key string) error {
					stateDeleteCalls++
					t.Logf("DeleteEntry #%d key=%s", stateDeleteCalls, key)
					return tt.stateDeleteErr
				},
				SaveEntryFunc: func(_ context.Context, key, value string, ttl time.Duration) error {
					saveCalls++
					savedKey = key
					savedValue = value
					t.Logf("SaveEntry #%d key=%s value=%s ttl=%s", saveCalls, key, value, ttl)
					if saveCalls == tt.saveErrOn {
						return errors.New("redis save failed")
					}
					return nil
				},
			}

			oauth := &MockGoogleClient{
				Log: logger,
				GetProfileFunc: func(_ context.Context, code string) (*domain.OAuthProfile, error) {
					t.Logf("GetProfile code=%s", code)
					return profile, tt.profileErr
				},
			}

			userRepo := &MockUserRepo{
				Log: logger,
				GetByOAuthFunc: func(
					_ context.Context,
					provider string,
					providerUserID string,
				) (*domain.User, error) {
					t.Logf(
						"GetByOAuth provider=%s provider_user_id=%s",
						provider,
						providerUserID,
					)
					return tt.existingUser, tt.getOAuthErr
				},
			}

			tokenMng := &MockTokenManager{
				Log: logger,
				GenerateTokenFunc: func(userID string, role ...domain.Role) (string, error) {
					t.Logf("GenerateToken user_id=%s role=%v", userID, role)
					if tt.tokenErr != nil {
						return "", tt.tokenErr
					}
					return "access-token", nil
				},
			}

			service := newAuthServiceForTest(
				userRepo,
				sessRepo,
				nil,
				tokenMng,
				oauth,
			)

			result, err := service.GoogleCallback(
				testContext(),
				"state-123",
				"google-code",
			)

			if !tt.expectError {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}

				if tt.expectRegistration {
					if result.RegistrationKey == "" {
						t.Fatal("expected registration key")
					}
					if result.Tokens != nil {
						t.Fatal("did not expect tokens for new oauth user")
					}
					if !strings.HasPrefix(savedKey, "oauth:pending:") {
						t.Fatalf("unexpected pending key: %q", savedKey)
					}

					var savedProfile domain.OAuthProfile
					if err := json.Unmarshal([]byte(savedValue), &savedProfile); err != nil {
						t.Fatalf("decode saved profile: %v", err)
					}
					if savedProfile.ProviderUserID != profile.ProviderUserID {
						t.Fatalf("unexpected saved profile: %#v", savedProfile)
					}
				} else {
					if result.Tokens == nil {
						t.Fatal("expected tokens for existing oauth user")
					}
					if result.Tokens.AccessToken != "access-token" {
						t.Fatalf("unexpected access token: %q", result.Tokens.AccessToken)
					}
					if result.Tokens.RefreshToken == "" {
						t.Fatal("expected non-empty refresh token")
					}
					if saveCalls != 2 {
						t.Fatalf("expected 2 token saves, got %d", saveCalls)
					}
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if tt.expectMsg != "" && !strings.Contains(err.Error(), tt.expectMsg) {
					t.Fatalf("expected error containing %q, got %q", tt.expectMsg, err.Error())
				}
			}

			if tt.stateGetErr == nil && stateDeleteCalls != 1 {
				t.Fatalf("expected one state delete call, got %d", stateDeleteCalls)
			}

			t.Logf("finished oauth callback scenario=%s err=%v", tt.name, err)
		})
	}
}

func TestAuthService_CompleteOAuthRegistration(t *testing.T) {
	logger := newTestLogger()
	createdUserID := mustUUID()

	baseProfile := domain.OAuthProfile{
		Provider:       domain.ProviderGoogle,
		ProviderUserID: "google-123",
		Email:          "john@example.com",
		FirstName:      "GoogleJohn",
		LastName:       "GoogleDoe",
	}

	profileJSONBytes, err := json.Marshal(baseProfile)
	if err != nil {
		t.Fatal(err)
	}
	profileJSON := string(profileJSONBytes)

	tests := []struct {
		name           string
		profileJSON    string
		getErr         error
		createErr      error
		tokenErr       error
		saveErrOn      int
		firstName      string
		lastName       string
		expectError    bool
		expectMsg      string
		expectOverride bool
	}{
		{
			name:        "success preserve profile names",
			profileJSON: profileJSON,
		},
		{
			name:           "success override profile names",
			profileJSON:    profileJSON,
			firstName:      "John",
			lastName:       "Doe",
			expectOverride: true,
		},
		{
			name:        "pending profile not found",
			getErr:      errors.New("redis key not found"),
			expectError: true,
			expectMsg:   "when completing the profile",
		},
		{
			name:        "invalid profile json",
			profileJSON: "not-json",
			expectError: true,
			expectMsg:   "deserialization error",
		},
		{
			name:        "create oauth user error",
			profileJSON: profileJSON,
			createErr:   errors.New("database insert failed"),
			expectError: true,
			expectMsg:   "error creating oauth user",
		},
		{
			name:        "token generation error",
			profileJSON: profileJSON,
			tokenErr:    errors.New("jwt failure"),
			expectError: true,
			expectMsg:   "access key generation error",
		},
		{
			name:        "refresh save error",
			profileJSON: profileJSON,
			saveErrOn:   1,
			expectError: true,
			expectMsg:   "error saving refresh token",
		},
		{
			name:        "access save error",
			profileJSON: profileJSON,
			saveErrOn:   2,
			expectError: true,
			expectMsg:   "error saving access token",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			getCalls := 0
			createCalls := 0
			deleteCalls := 0
			saveCalls := 0
			var createdUser *domain.User

			userRepo := &MockUserRepo{
				Log: logger,
				CreateWithOauthFunc: func(_ context.Context, user *domain.User) (uuid.UUID, error) {
					createCalls++
					createdUser = user
					t.Logf("CreateWithOauth email=%s first=%s last=%s phone=%s provider=%s provider_user_id=%s", user.Email, user.FirstName, user.LastName, user.Phone, user.Provider, user.ProviderUserID)
					return createdUserID, tt.createErr
				},
			}

			sessRepo := &MockSessionRepo{
				Log: logger,
				GetValueFunc: func(_ context.Context, key string) (string, error) {
					getCalls++
					t.Logf("GetValue key=%s", key)
					return tt.profileJSON, tt.getErr
				},
				DeleteEntryFunc: func(_ context.Context, key string) error {
					deleteCalls++
					t.Logf("DeleteEntry key=%s", key)
					return nil
				},
				SaveEntryFunc: func(_ context.Context, key, value string, ttl time.Duration) error {
					saveCalls++
					t.Logf("SaveEntry #%d key=%s value=%s ttl=%s", saveCalls, key, value, ttl)
					if saveCalls == tt.saveErrOn {
						return errors.New("redis save failed")
					}
					return nil
				},
			}

			tokenMng := &MockTokenManager{
				Log: logger,
				GenerateTokenFunc: func(userID string, role ...domain.Role) (string, error) {
					t.Logf("GenerateToken user_id=%s role=%v", userID, role)
					if tt.tokenErr != nil {
						return "", tt.tokenErr
					}
					return "access-token", nil
				},
			}

			service := newAuthServiceForTest(
				userRepo,
				sessRepo,
				nil,
				tokenMng,
				nil,
			)

			result, err := service.CompleteOAuthRegistration(
				testContext(),
				domain.OAuthRegistrationData{
					Key:       "pending-key",
					FirstName: tt.firstName,
					LastName:  tt.lastName,
					Phone:     "+123456789",
				},
			)

			if !tt.expectError {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
				if result.AccessToken != "access-token" || result.RefreshToken == "" {
					t.Fatalf("unexpected token pair: %#v", result)
				}
				if getCalls != 1 || createCalls != 1 || deleteCalls != 1 || saveCalls != 2 {
					t.Fatalf("unexpected calls get=%d create=%d delete=%d save=%d", getCalls, createCalls, deleteCalls, saveCalls)
				}
				if createdUser == nil {
					t.Fatal("expected created user")
				}
				if createdUser.Email != baseProfile.Email || createdUser.Phone != "+123456789" {
					t.Fatalf("unexpected created user: %#v", createdUser)
				}
				if tt.expectOverride {
					if createdUser.FirstName != "John" || createdUser.LastName != "Doe" {
						t.Fatalf("expected overridden names, got %q %q", createdUser.FirstName, createdUser.LastName)
					}
				} else {
					if createdUser.FirstName != baseProfile.FirstName || createdUser.LastName != baseProfile.LastName {
						t.Fatalf("expected profile names, got %q %q", createdUser.FirstName, createdUser.LastName)
					}
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if tt.expectMsg != "" && !strings.Contains(err.Error(), tt.expectMsg) {
					t.Fatalf("expected error containing %q, got %q", tt.expectMsg, err.Error())
				}
			}

			t.Logf("finished complete oauth scenario=%s err=%v", tt.name, err)
		})
	}
}

func TestAuthService_ResendVerCode(t *testing.T) {
	logger := newTestLogger()

	tests := []struct {
		name        string
		saveErr     error
		emailErr    error
		publishErr  error
		expectError bool
		expectMsg   string
	}{
		{name: "success"},
		{
			name:        "save code error",
			saveErr:     errors.New("redis unavailable"),
			expectError: true,
			expectMsg:   "failed to save code",
		},
		{
			name:        "email lookup error",
			emailErr:    errors.New("postgres unavailable"),
			expectError: true,
			expectMsg:   "failed to receive email",
		},
		{
			name:        "publish error",
			publishErr:  errors.New("rabbit unavailable"),
			expectError: true,
			expectMsg:   "error while publishing event",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			saveCalls := 0
			var verificationCode string
			publishCalls := 0
			var publishedPayload []byte

			sessRepo := &MockSessionRepo{
				Log: logger,
				SaveEntryFunc: func(_ context.Context, key, value string, ttl time.Duration) error {
					saveCalls++
					verificationCode = value
					t.Logf("SaveEntry key=%s code=%s ttl=%s", key, value, ttl)
					return tt.saveErr
				},
			}

			userRepo := &MockUserRepo{
				Log: logger,
				GetEmailByUserIDFunc: func(_ context.Context, userID string) (string, error) {
					t.Logf("GetEmailByUserID user_id=%s", userID)
					return "john@example.com", tt.emailErr
				},
			}

			publisher := &MockPublisher{
				Log: logger,
				PublishEventFunc: func(eventKey string, payload []byte) error {
					publishCalls++
					publishedPayload = append([]byte(nil), payload...)
					t.Logf("PublishEvent key=%s payload=%s", eventKey, string(payload))
					return tt.publishErr
				},
			}

			service := newAuthServiceForTest(
				userRepo,
				sessRepo,
				publisher,
				nil,
				nil,
			)

			err := service.ResendVerCode(testContext(), "user-123")

			if !tt.expectError {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
				if saveCalls != 1 || publishCalls != 1 {
					t.Fatalf("expected one save and one publish, got save=%d publish=%d", saveCalls, publishCalls)
				}
				if verificationCode == "" {
					t.Fatal("expected non-empty verification code")
				}

				var event domain.UserRegisteredEvent
				if err := json.Unmarshal(publishedPayload, &event); err != nil {
					t.Fatalf("decode published event: %v", err)
				}
				if event.Email != "john@example.com" {
					t.Fatalf("unexpected event email: %q", event.Email)
				}
				if event.Code != verificationCode {
					t.Fatalf("event code %q does not match saved code %q", event.Code, verificationCode)
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if tt.expectMsg != "" && !strings.Contains(err.Error(), tt.expectMsg) {
					t.Fatalf("expected error containing %q, got %q", tt.expectMsg, err.Error())
				}
			}

			t.Logf("finished resend scenario=%s err=%v", tt.name, err)
		})
	}
}
