package domain

const (
	// UserRegistredEventKey is the routing key for user registration events.
	UserRegistredEventKey = "user.registered"
	// UserEmailVerifiedEventKey is the routing key for email verification events.
	UserEmailVerifiedEventKey = "user.email_verified"
)

// UserRegisteredEvent contains event payload for newly registered users.
type UserRegisteredEvent struct {
	Email string `json:"email"`
	Code  string `json:"code"`
}

// UserEmailVerifiedEvent contains event payload for verified email events.
type UserEmailVerifiedEvent struct {
	Email string `json:"email"`
	Name  string `json:"name"`
}
