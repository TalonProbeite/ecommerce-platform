package domain

import "errors"

var (
	// ErrUserNotFound indicates that a user was not found in database.
	ErrUserNotFound = errors.New("user not found error")
	// ErrInvalidCredentials indicates that password verification failed.
	ErrInvalidCredentials = errors.New("incorrect password")
	// ErrUserNotActive indicates that a user account is inactive.
	ErrUserNotActive = errors.New("the user is not active")
	// ErrEmailAlreadyExists indicates a registration conflict with an existing email.
	ErrEmailAlreadyExists = errors.New("email already in use")
)
