package dto

import (
	"fmt"
	"strings"
)

type RegisterRequest struct {
	Email     string `json:"email" validate:"required,email"`
	Password  string `json:"password" validate:"required,min=8"`
	FirstName string `json:"first_name" validate:"required,max=100"`
	LastName  string `json:"last_name" validate:"max=100"`
	Phone     string `json:"phone" validate:"required,min=10,max=30"`
}

type LoginRequest struct {
	Email    string `json:"email" validate:"required,email"`
	Password string `json:"password" validate:"required,min=8"`
}

type VerifyEmailRequest struct {
	Code string `json:"code"`
}

func (r *VerifyEmailRequest) Validate() error {
	r.Code = strings.TrimSpace(r.Code)
	if len(r.Code) != 10 {
		return fmt.Errorf("verification code must be exactly 10 characters long")
	}
	return nil
}
