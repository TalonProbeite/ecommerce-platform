package dto_test

import (
	"testing"

	"shop/auth/internal/transport/http/dto"
	"shop/shared/validator"
)

func boolPtr(value bool) *bool { return &value }

func stringPtr(value string) *string { return &value }

func TestVerifyEmailRequestValidate(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		input   string
		want    string
		wantErr bool
	}{
		{name: "trims surrounding whitespace", input: "  ABCDEFGHIJ ", want: "ABCDEFGHIJ"},
		{name: "rejects a short code", input: "ABCDEFGHI", wantErr: true},
		{name: "rejects a long code", input: "ABCDEFGHIJK", wantErr: true},
		{name: "rejects an empty code", input: "", wantErr: true},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			request := dto.VerifyEmailRequest{Code: tc.input}

			err := request.Validate()
			if tc.wantErr {
				if err == nil {
					t.Fatal("Validate() = nil, want an error")
				}

				return
			}

			if err != nil {
				t.Fatalf("Validate() returned an error: %v", err)
			}

			if request.Code != tc.want {
				t.Fatalf("Code = %q, want %q", request.Code, tc.want)
			}
		})
	}
}

func TestPatchUserValidate(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		request dto.PatchUser
		wantErr bool
	}{
		{name: "rejects an empty patch", request: dto.PatchUser{}, wantErr: true},
		{name: "rejects an empty email", request: dto.PatchUser{Email: stringPtr("")}, wantErr: true},
		{name: "rejects an empty first name", request: dto.PatchUser{FirstName: stringPtr("")}, wantErr: true},
		{name: "rejects an empty last name", request: dto.PatchUser{LastName: stringPtr("")}, wantErr: true},
		{name: "rejects an empty phone", request: dto.PatchUser{Phone: stringPtr("")}, wantErr: true},
		{name: "accepts a single non-empty field", request: dto.PatchUser{FirstName: stringPtr("Jane")}},
		{name: "accepts all fields", request: dto.PatchUser{
			Email:     stringPtr("jane@example.com"),
			FirstName: stringPtr("Jane"),
			LastName:  stringPtr("Doe"),
			Phone:     stringPtr("+491234567890"),
		}},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			err := tc.request.Validate()
			if tc.wantErr && err == nil {
				t.Fatal("Validate() = nil, want an error")
			}

			if !tc.wantErr && err != nil {
				t.Fatalf("Validate() returned an error: %v", err)
			}
		})
	}
}

func TestStructValidation(t *testing.T) {
	t.Parallel()

	customValidator := validator.New()

	tests := []struct {
		name    string
		value   any
		wantErr bool
	}{
		{name: "valid registration", value: dto.RegisterRequest{
			Email:     "jane@example.com",
			Password:  "Password123!",
			FirstName: "Jane",
			LastName:  "Doe",
			Phone:     "+491234567890",
		}},
		{name: "invalid registration email", value: dto.RegisterRequest{
			Email:     "not-an-email",
			Password:  "Password123!",
			FirstName: "Jane",
			Phone:     "+491234567890",
		}, wantErr: true},
		{name: "short registration password", value: dto.RegisterRequest{
			Email:     "jane@example.com",
			Password:  "short",
			FirstName: "Jane",
			Phone:     "+491234567890",
		}, wantErr: true},
		{name: "valid login", value: dto.LoginRequest{
			Email:    "jane@example.com",
			Password: "Password123!",
		}},
		{name: "invalid login email", value: dto.LoginRequest{
			Email:    "jane",
			Password: "Password123!",
		}, wantErr: true},
		{name: "valid role", value: dto.UpdateRoleReq{Role: "store_manager"}},
		{name: "invalid role", value: dto.UpdateRoleReq{Role: "owner"}, wantErr: true},
		{name: "ban with explicit false", value: dto.BanUserReq{IsBanned: boolPtr(false)}},
		{name: "ban without a value", value: dto.BanUserReq{}, wantErr: true},
		{name: "valid store id", value: dto.UpdateStoreReq{
			StoreID: stringPtr("11111111-1111-4111-8111-111111111111"),
		}},
		{name: "invalid store id", value: dto.UpdateStoreReq{StoreID: stringPtr("store-1")}, wantErr: true},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			err := customValidator.Validate(tc.value)
			if tc.wantErr && err == nil {
				t.Fatal("Validate() = nil, want an error")
			}

			if !tc.wantErr && err != nil {
				t.Fatalf("Validate() returned an error: %v", err)
			}
		})
	}
}
