package domain

import "errors"

var (
	ErrEntryNotFound            = errors.New("session entry not found")
	ErrOAuthStateInvalid        = errors.New("invalid oauth transaction")
	ErrOAuthRegistrationExpired = errors.New("oauth registration expired")
	ErrOAuthRegistrationBusy    = errors.New("oauth registration in progress")
	ErrOAuthIdentityInvalid     = errors.New("invalid oauth identity")
	ErrUserNotFound             = errors.New("user not found error")
	ErrInvalidCredentials       = errors.New("incorrect password")
	ErrUserNotActive            = errors.New("the user is not active")
	ErrEmailAlreadyExists       = errors.New("email already in use")
	ErrValidationFailed         = errors.New("validation failed")
	ErrEmailLockedByOAuth       = errors.New("user is registered using oauth")
)
