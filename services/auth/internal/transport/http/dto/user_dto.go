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

type CompleteReq struct {
	FirstName string `json:"first_name"`
	LastName  string `json:"last_name"`
	Phone     string `json:"phone" validate:"required"`
}
type PatchUser struct {
	Email     *string `json:"email" validate:"omitempty,email"`
	FirstName *string `json:"first_name" validate:"omitempty,max=100"`
	LastName  *string `json:"last_name" validate:"omitempty,max=100"`
	Phone     *string `json:"phone" validate:"omitempty,min=10,max=30"`
}

func (p *PatchUser) Validate() error {
	if p.Email == nil && p.FirstName == nil && p.LastName == nil && p.Phone == nil {
		return fmt.Errorf("invalid data, all fields are empty")
	}

	if p.Email != nil && *p.Email == "" {
		return fmt.Errorf("email cannot be empty")
	}

	if p.FirstName != nil && *p.FirstName == "" {
		return fmt.Errorf("first name cannot be empty")
	}

	if p.LastName != nil && *p.LastName == "" {
		return fmt.Errorf("last name cannot be empty")
	}

	if p.Phone != nil && *p.Phone == "" {
		return fmt.Errorf("phone cannot be empty")
	}

	return nil
}
