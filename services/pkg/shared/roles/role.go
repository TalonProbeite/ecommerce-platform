package roles

type Role string

const (
	RoleAdmin        Role = "admin"
	RoleCustomer     Role = "customer"
	RoleAnalyst      Role = "analyst"
	RoleStoreManager Role = "store_manager"
)

func (r Role) String() string {
	return string(r)
}
