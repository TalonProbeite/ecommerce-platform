package domain

type User struct {
	ID        string `db:"id"`
	Email     string `db:"email"`
	Password  string `db:"password_hash"`
	Role      string `db:"role"`
	IsActive  bool   `db:"is_active"`
	IsEmailVerified bool `db:"is_email_verified"`
	FirstName string `db:"first_name"`
	LastName  string `db:"last_name"`
	Phone     string `db:"phone"`
}
