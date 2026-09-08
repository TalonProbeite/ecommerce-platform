// Package dto defines data transfer objects for HTTP request/response payloads.
package dto

import (
	"fmt"
	"strings"
)

// RegisterRequest holds registration payload fields and validation rules.
type RegisterRequest struct {
	Email     string `json:"email" validate:"required,email"`
	Password  string `json:"password" validate:"required,min=8"`
	FirstName string `json:"first_name" validate:"required,max=100"`
	LastName  string `json:"last_name" validate:"max=100"`
	Phone     string `json:"phone" validate:"required,min=10,max=30"`
}

// LoginRequest holds login payload fields and validation rules.
type LoginRequest struct {
	Email    string `json:"email" validate:"required,email"`
	Password string `json:"password" validate:"required,min=8"`
}

// VerifyEmailRequest holds email verification code payload.
type VerifyEmailRequest struct {
	Code string `json:"code"`
}

// Validate sanitizes and verifies the VerifyEmailRequest payload.
func (r *VerifyEmailRequest) Validate() error {
	r.Code = strings.TrimSpace(r.Code)
	if len(r.Code) != 10 {
		return fmt.Errorf("verification code must be exactly 10 characters long")
	}
	return nil
}
