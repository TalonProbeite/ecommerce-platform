package domain

type User struct {
	ID              string `db:"id"`
	Email           string `db:"email"`
	Password        string `db:"password_hash"`
	Role            Role   `db:"role"`
	FirstName       string `db:"first_name"`
	LastName        string `db:"last_name"`
	Phone           string `db:"phone"`
	IsActive        bool   `db:"is_active"`
	IsEmailVerified bool   `db:"is_email_verified"`
}
type TokenPair struct {
	AccessToken  string
	RefreshToken string
}
