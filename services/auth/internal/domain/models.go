package domain

type User struct {
	ID              string   `db:"id"`
	Email           string   `db:"email"`
	Password        string   `db:"password_hash"`
	Role            Role     `db:"role"`
	FirstName       string   `db:"first_name"`
	LastName        string   `db:"last_name"`
	Phone           string   `db:"phone"`
	Provider        Provider `db:"provider"`
	ProviderUserID  string   `db:"provider_user_id"`
	IsActive        bool     `db:"is_active"`
	IsEmailVerified bool     `db:"is_email_verified"`
}
type TokenPair struct {
	AccessToken  string
	RefreshToken string
}
type OAuthProfile struct {
	Provider       Provider `json:"provider"`
	ProviderUserID string   `json:"sub"`
	Email          string   `json:"email"`
	FirstName      string   `json:"given_name"`
	LastName       string   `json:"family_name"`
}
type OAuthResult struct {
	Tokens          *TokenPair
	RegistrationKey string
}
type OAuthRegistrationData struct {
	Key       string
	FirstName string
	LastName  string
	Phone     string
}

type UserProfile struct {
	Email     string `json:"email"`
	FirstName string `json:"first_name"`
	LastName  string `json:"last_name"`
	Phone     string `json:"phone"`
}
