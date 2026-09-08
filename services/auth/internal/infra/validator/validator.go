// Package validator provides custom struct validation for HTTP requests.
package validator

import "github.com/go-playground/validator/v10"

// CustomValidator wraps go-playground validator for Echo integration.
type CustomValidator struct {
	validator *validator.Validate
}

// Validate validates struct fields using go-playground tags.
func (cv *CustomValidator) Validate(i interface{}) error {
	return cv.validator.Struct(i)
}

// New constructs a new CustomValidator instance.
func New() *CustomValidator {
	return &CustomValidator{
		validator: validator.New(),
	}
}
