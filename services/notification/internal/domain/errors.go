package domain

import "errors"

var (
	ErrNotificationNotFound = errors.New("notification not found")
	ErrInvalidFilters       = errors.New("invalid filters provided")
)
