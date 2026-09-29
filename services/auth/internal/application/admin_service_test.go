package application

import (
	"context"
	"encoding/json/v2"
	"errors"
	"strings"
	"testing"
	"time"

	"shop/auth/internal/domain"
)

type adminUserRepoMock struct {
	GetByIDFunc         func(context.Context, string) (*domain.User, error)
	UpdateRoleFunc      func(context.Context, string, domain.Role) error
	UpdateBanStatusFunc func(context.Context, string, bool) error
}

func (m *adminUserRepoMock) GetByID(ctx context.Context, userID string) (*domain.User, error) {
	if m.GetByIDFunc == nil {
		return nil, errors.New("GetByIDFunc is not set")
	}
	return m.GetByIDFunc(ctx, userID)
}

func (m *adminUserRepoMock) UpdateRole(ctx context.Context, userID string, role domain.Role) error {
	if m.UpdateRoleFunc == nil {
		return errors.New("UpdateRoleFunc is not set")
	}
	return m.UpdateRoleFunc(ctx, userID, role)
}

func (m *adminUserRepoMock) UpdateBanStatus(ctx context.Context, userID string, isActive bool) error {
	if m.UpdateBanStatusFunc == nil {
		return errors.New("UpdateBanStatusFunc is not set")
	}
	return m.UpdateBanStatusFunc(ctx, userID, isActive)
}

type adminPublisherMock struct {
	err     error
	calls   int
	key     string
	payload []byte
}

func (m *adminPublisherMock) PublishEvent(key string, payload []byte) error {
	m.calls++
	m.key = key
	m.payload = append([]byte(nil), payload...)
	return m.err
}

func newAdminServiceTestService(
	userRepo *adminUserRepoMock,
	sessRepo *MockSessionRepo,
	publisher *adminPublisherMock,
) *AdminService {
	return NewAdminService(userRepo, sessRepo, publisher)
}

func TestAdminService_UpdateRole(t *testing.T) {
	const (
		userID  = "11111111-1111-1111-1111-111111111111"
		adminID = "22222222-2222-2222-2222-222222222222"
	)

	tests := []struct {
		name          string
		newRole       domain.Role
		repoErr       error
		publisherErr  error
		wantErr       string
		wantRepoCalls int
		wantRevoke    int
		wantPubCalls  int
	}{
		{
			name:          "success",
			newRole:       domain.RoleAdmin,
			wantRepoCalls: 1,
			wantRevoke:    1,
			wantPubCalls:  1,
		},
		{
			name:          "repository error",
			newRole:       domain.RoleAnalyst,
			repoErr:       errors.New("boom"),
			wantErr:       "error updating role",
			wantRepoCalls: 1,
		},
		{
			name:          "publisher error",
			newRole:       domain.RoleCustomer,
			publisherErr:  errors.New("boom"),
			wantErr:       "error while publishing event",
			wantRepoCalls: 1,
			wantRevoke:    1,
			wantPubCalls:  1,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			var gotUserID string
			var gotRole domain.Role
			var revokeCalls int
			var revokedUserID string

			userRepo := &adminUserRepoMock{
				UpdateRoleFunc: func(_ context.Context, id string, role domain.Role) error {
					gotUserID = id
					gotRole = role
					return tt.repoErr
				},
			}
			publisher := &adminPublisherMock{err: tt.publisherErr}
			session := &MockSessionRepo{
				RevokeAllSessionsFunc: func(_ context.Context, id string) error {
					revokeCalls++
					revokedUserID = id
					return nil
				},
			}
			svc := newAdminServiceTestService(userRepo, session, publisher)

			started := time.Now().Unix()
			err := svc.UpdateRole(context.Background(), userID, adminID, tt.newRole)
			finished := time.Now().Unix()

			if tt.wantErr == "" {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if !strings.Contains(err.Error(), tt.wantErr) {
					t.Fatalf("error = %q, want substring %q", err.Error(), tt.wantErr)
				}
			}

			if gotUserID != userID && tt.wantRepoCalls > 0 {
				t.Errorf("userID = %q, want %q", gotUserID, userID)
			}
			if gotRole != tt.newRole && tt.wantRepoCalls > 0 {
				t.Errorf("role = %q, want %q", gotRole, tt.newRole)
			}

			if revokeCalls != tt.wantRevoke {
				t.Errorf("revoke calls = %d, want %d", revokeCalls, tt.wantRevoke)
			}
			if revokeCalls > 0 && revokedUserID != userID {
				t.Errorf("revoked userID = %q, want %q", revokedUserID, userID)
			}

			if publisher.calls != tt.wantPubCalls {
				t.Fatalf("publisher calls = %d, want %d", publisher.calls, tt.wantPubCalls)
			}
			if publisher.calls == 0 {
				return
			}

			if publisher.key != domain.RoutingKeyUserRoleChanged {
				t.Fatalf("event key = %q, want %q", publisher.key, domain.RoutingKeyUserRoleChanged)
			}

			var event domain.UserRoleChangedEvent
			if err := json.Unmarshal(publisher.payload, &event); err != nil {
				t.Fatalf("unmarshal event: %v", err)
			}
			if event.UserID != userID || event.AdminID != adminID || event.NewRole != string(tt.newRole) {
				t.Errorf("event = %+v", event)
			}
			if event.Timestamp < started || event.Timestamp > finished {
				t.Errorf("timestamp = %d, want between %d and %d", event.Timestamp, started, finished)
			}
		})
	}
}

func TestAdminService_BanUser(t *testing.T) {
	const (
		userID  = "11111111-1111-1111-1111-111111111111"
		adminID = "22222222-2222-2222-2222-222222222222"
	)

	tests := []struct {
		name          string
		isBanned      bool
		repoErr       error
		sessionErr    error
		publisherErr  error
		wantErr       string
		wantSession   int
		wantPublisher int
	}{
		{
			name:          "ban success",
			isBanned:      true,
			wantSession:   1,
			wantPublisher: 1,
		},
		{
			name:          "unban success",
			isBanned:      false,
			wantPublisher: 1,
		},
		{
			name:     "repository error",
			isBanned: true,
			repoErr:  errors.New("boom"),
			wantErr:  "error updating ban status",
		},
		{
			name:        "session revoke error",
			isBanned:    true,
			sessionErr:  errors.New("boom"),
			wantErr:     "error occurred while revoking all auth sessions",
			wantSession: 1,
		},
		{
			name:          "publisher error",
			isBanned:      true,
			publisherErr:  errors.New("boom"),
			wantErr:       "error while publishing event",
			wantSession:   1,
			wantPublisher: 1,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			var gotUserID string
			var gotStatus bool

			userRepo := &adminUserRepoMock{
				UpdateBanStatusFunc: func(_ context.Context, id string, status bool) error {
					gotUserID = id
					gotStatus = status
					return tt.repoErr
				},
			}
			var revokeCalls int
			var revokedUserID string
			session := &MockSessionRepo{
				RevokeAllSessionsFunc: func(_ context.Context, id string) error {
					revokeCalls++
					revokedUserID = id
					return tt.sessionErr
				},
			}
			publisher := &adminPublisherMock{err: tt.publisherErr}
			svc := newAdminServiceTestService(userRepo, session, publisher)

			err := svc.BanUser(context.Background(), userID, adminID, tt.isBanned)
			if tt.wantErr == "" {
				if err != nil {
					t.Fatalf("unexpected error: %v", err)
				}
			} else {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				if !strings.Contains(err.Error(), tt.wantErr) {
					t.Fatalf("error = %q, want substring %q", err.Error(), tt.wantErr)
				}
			}

			if gotUserID != userID {
				t.Errorf("userID = %q, want %q", gotUserID, userID)
			}
			if gotStatus != tt.isBanned {
				t.Errorf("status = %t, want %t", gotStatus, tt.isBanned)
			}
			if revokeCalls != tt.wantSession {
				t.Errorf("revoke calls = %d, want %d", revokeCalls, tt.wantSession)
			}
			if revokeCalls > 0 && revokedUserID != userID {
				t.Errorf("revoked userID = %q, want %q", revokedUserID, userID)
			}
			if publisher.calls != tt.wantPublisher {
				t.Errorf("publisher calls = %d, want %d", publisher.calls, tt.wantPublisher)
			}
			if publisher.calls == 0 {
				return
			}

			if publisher.key != domain.RoutingKeyUserBanned {
				t.Fatalf("event key = %q, want %q", publisher.key, domain.RoutingKeyUserBanned)
			}
			var event domain.UserBannedEvent
			if err := json.Unmarshal(publisher.payload, &event); err != nil {
				t.Fatalf("unmarshal event: %v", err)
			}
			if event.UserID != userID || event.AdminID != adminID || event.IsBanned != tt.isBanned {
				t.Errorf("event = %+v", event)
			}
		})
	}
}
