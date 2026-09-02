package domain

type User struct {
	ID        string `db:"id"`
	Email     string `db:"email"`
	Password  string `db:"password_hash"`
	Role      string `db:"role"`
	IsActive  bool   `db:"is_active"`
	FirstName string `db:"first_name"`
	LasyName  string `db:"last_name"`
	Phone     string `db:"phone"`
}
