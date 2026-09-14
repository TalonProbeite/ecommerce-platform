package domain

import "errors"

var (
	ErrUserNotFound       = errors.New("user not found error")
	ErrInvalidCredentials = errors.New("incorrect password")
	ErrUserNotActive      = errors.New("the user is not active")
	ErrEmailAlreadyExists = errors.New("email already in use")
)
