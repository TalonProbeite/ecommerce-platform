package validator_test

import (
	"testing"

	"shop/notification/internal/infra/validator"
)

type payload struct {
	Name  string `validate:"required"`
	Email string `validate:"required,email"`
	Age   int    `validate:"gte=18,lte=120"`
}

func TestCustomValidator(t *testing.T) {
	t.Parallel()

	customValidator := validator.New()

	tests := []struct {
		name    string
		value   payload
		wantErr bool
	}{
		{
			name:  "accepts a valid payload",
			value: payload{Name: "Jane", Email: "jane@example.com", Age: 30},
		},
		{
			name:    "rejects a missing name",
			value:   payload{Email: "jane@example.com", Age: 30},
			wantErr: true,
		},
		{
			name:    "rejects an invalid email",
			value:   payload{Name: "Jane", Email: "jane", Age: 30},
			wantErr: true,
		},
		{
			name:    "rejects an age below the minimum",
			value:   payload{Name: "Jane", Email: "jane@example.com", Age: 17},
			wantErr: true,
		},
		{
			name:    "rejects an age above the maximum",
			value:   payload{Name: "Jane", Email: "jane@example.com", Age: 121},
			wantErr: true,
		},
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
