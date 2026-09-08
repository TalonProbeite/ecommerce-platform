package domain

type Role string

const (
	RoleAdmin    Role = "admin"
	RoleCustomer Role = "customer"
	RoleAnalyst Role = "analyst"
)

func (r Role) String() string {
	return string(r)
}