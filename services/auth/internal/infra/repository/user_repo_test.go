package repository_test

import (
	"context"
	"encoding/json"
	"os"
	"testing"

	"shop/auth/internal/application"
	"shop/auth/internal/domain"
	"shop/auth/internal/infra/repository"
	"shop/shared/roles"

	"github.com/jmoiron/sqlx"
)

// Temporary tables keep fixtures isolated from any existing application data.
func TestGetByIDProfile(t *testing.T) {
	dsn := os.Getenv("AUTH_TEST_DATABASE_URL")
	if dsn == "" {
		t.Skip("set AUTH_TEST_DATABASE_URL to run PostgreSQL repository tests")
	}
	db, err := sqlx.Connect("postgres", dsn)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = db.Close() })
	db.SetMaxOpenConns(1)
	db.MustExec(`CREATE TEMP TABLE users (
		id text, email text, password_hash text, role text,
		is_active boolean, is_email_verified boolean
	)`)
	db.MustExec(`CREATE TEMP TABLE profiles (
		user_id text, first_name text, last_name text, phone text
	)`)
	db.MustExec(`CREATE TEMP TABLE oauth_accounts (user_id text, provider text, provider_user_id text)`)
	repo := repository.NewUserRepo(db)
	service := application.NewProfileService(repo, nil, nil)

	tests := []struct {
		name                    string
		role                    roles.Role
		verified                bool
		lastName, phone         any
		wantLastName, wantPhone string
	}{
		{"unverified customer", roles.RoleCustomer, false, "Doe", "+48123456789", "Doe", "+48123456789"},
		{"verified admin", roles.RoleAdmin, true, "Doe", "+48123456789", "Doe", "+48123456789"},
		{"null last name", roles.RoleCustomer, true, nil, "+48123456789", "", "+48123456789"},
		{"null phone", roles.RoleAdmin, false, "Doe", nil, "Doe", ""},
		{"both optional fields null", roles.RoleCustomer, false, nil, nil, "", ""},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			db.MustExec(`TRUNCATE users, profiles`)
			db.MustExec(`INSERT INTO users VALUES ($1, $2, 'hash', $3, true, $4)`, "user-id", "profile@example.com", tt.role, tt.verified)
			db.MustExec(`INSERT INTO profiles VALUES ($1, 'Jane', $2, $3)`, "user-id", tt.lastName, tt.phone)
			profile, err := service.GetUserProfile(context.Background(), "user-id")
			if err != nil {
				t.Fatalf("get profile: %v", err)
			}
			want := domain.UserProfile{
				Email: "profile@example.com", FirstName: "Jane",
				LastName: tt.wantLastName, Phone: tt.wantPhone,
				Role: tt.role, IsVerified: tt.verified,
			}
			if profile != want {
				t.Fatalf("profile = %+v, want %+v", profile, want)
			}
			loginUser, err := repo.GetByEmail(context.Background(), want.Email)
			if err != nil {
				t.Fatal(err)
			}
			if loginUser.Role != profile.Role || loginUser.IsEmailVerified != profile.IsVerified ||
				loginUser.Email != profile.Email || loginUser.FirstName != profile.FirstName ||
				loginUser.LastName != profile.LastName || loginUser.Phone != profile.Phone {
				t.Fatalf("login user and restored profile differ: %+v / %+v", loginUser, profile)
			}
			body, err := json.Marshal(profile)
			if err != nil {
				t.Fatal(err)
			}
			var fields map[string]json.RawMessage
			if err := json.Unmarshal(body, &fields); err != nil {
				t.Fatal(err)
			}
			for _, key := range []string{"email", "first_name", "last_name", "phone", "role", "is_verified", "is_oauth"} {
				if _, ok := fields[key]; !ok {
					t.Errorf("missing JSON field %s: %s", key, body)
				}
			}
			if len(fields) != 7 {
				t.Errorf("unexpected JSON contract: %s", body)
			}
		})
	}
}
