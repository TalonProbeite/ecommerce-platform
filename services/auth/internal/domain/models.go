package domain

import "shop/shared/roles"

type User struct {
	ID              string     `db:"id"`
	Email           string     `db:"email"`
	Password        string     `db:"password_hash"`
	Role            roles.Role `db:"role"`
	FirstName       string     `db:"first_name"`
	LastName        string     `db:"last_name"`
	Phone           string     `db:"phone"`
	Provider        Provider   `db:"provider"`
	ProviderUserID  string     `db:"provider_user_id"`
	IsActive        bool       `db:"is_active"`
	IsEmailVerified bool       `db:"is_email_verified"`
	IsOAuth         bool       `db:"is_oauth"`
	StoreID         *string    `db:"store_id"`
}
type TokenPair struct {
	AccessToken  string
	RefreshToken string
}
type OAuthProfile struct {
	Provider       Provider `json:"provider"`
	ProviderUserID string   `json:"sub"`
	Email          string   `json:"email"`
	HostedDomain   string   `json:"hd,omitempty"`
	FirstName      string   `json:"given_name"`
	LastName       string   `json:"family_name"`
	EmailVerified  bool     `json:"email_verified"`
}
type OAuthResult struct {
	Tokens          *TokenPair
	RegistrationKey string
}
type OAuthRegistrationData struct {
	Key               string
	FirstName         string
	LastName          string
	Phone             string
	FirstNameProvided bool
	LastNameProvided  bool
}

type UserProfile struct {
	Email      string     `json:"email"`
	FirstName  string     `json:"first_name"`
	LastName   string     `json:"last_name"`
	Phone      string     `json:"phone"`
	Role       roles.Role `json:"role"`
	StoreID    *string    `json:"store_id,omitempty"`
	IsVerified bool       `json:"is_verified"`
	IsOAuth    bool       `json:"is_oauth"`
}
type Session struct {
	ID           string `json:"id"`
	UserID       string `json:"user_id"`
	AccessToken  string `json:"access_token"`
	RefreshToken string `json:"refresh_token"`
}

// OAuthStart is transport-neutral; the handler stores State in a browser cookie.
type OAuthStart struct {
	URL   string
	State string
}
type OAuthTransaction struct {
	Verifier string `json:"verifier"`
	Nonce    string `json:"nonce"`
}

// PendingOAuthProfile deliberately excludes provider identifiers and secrets.
type PendingOAuthProfile struct {
	Email     string `json:"email"`
	FirstName string `json:"first_name"`
	LastName  string `json:"last_name"`
}
