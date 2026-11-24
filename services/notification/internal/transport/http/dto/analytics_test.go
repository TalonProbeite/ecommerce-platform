package dto_test

import (
	"testing"

	"shop/notification/internal/transport/http/dto"

	"shop/notification/internal/infra/validator"
)

func TestGetNotificationByIDRequestValidation(t *testing.T) {
	t.Parallel()

	customValidator := validator.New()

	tests := []struct {
		name    string
		request dto.GetNotificationByIDRequest
		wantErr bool
	}{
		{name: "accepts an id", request: dto.GetNotificationByIDRequest{ID: "abc123"}},
		{name: "rejects an empty id", request: dto.GetNotificationByIDRequest{}, wantErr: true},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			err := customValidator.Validate(tc.request)
			if tc.wantErr && err == nil {
				t.Fatal("Validate() = nil, want an error")
			}

			if !tc.wantErr && err != nil {
				t.Fatalf("Validate() returned an error: %v", err)
			}
		})
	}
}

func TestGetNotificationsHistoryRequestValidation(t *testing.T) {
	t.Parallel()

	customValidator := validator.New()

	tests := []struct {
		name    string
		request dto.GetNotificationsHistoryRequest
		wantErr bool
	}{
		{name: "zero values are valid", request: dto.GetNotificationsHistoryRequest{}},
		{name: "accepts the maximum limit", request: dto.GetNotificationsHistoryRequest{Limit: 100}},
		{name: "rejects a negative limit", request: dto.GetNotificationsHistoryRequest{Limit: -1}, wantErr: true},
		{name: "rejects a limit above the maximum", request: dto.GetNotificationsHistoryRequest{Limit: 101}, wantErr: true},
		{name: "accepts a positive offset", request: dto.GetNotificationsHistoryRequest{Offset: 5}},
		{name: "rejects a negative offset", request: dto.GetNotificationsHistoryRequest{Offset: -1}, wantErr: true},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			err := customValidator.Validate(tc.request)
			if tc.wantErr && err == nil {
				t.Fatal("Validate() = nil, want an error")
			}

			if !tc.wantErr && err != nil {
				t.Fatalf("Validate() returned an error: %v", err)
			}
		})
	}
}
