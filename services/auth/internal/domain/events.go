package domain

const (
	UserRegistredEventKey     = "user.registered"
	UserEmailVerifiedEventKey = "user.email_verified"
	RoutingKeyUserRoleChanged = "user.role_changed"
	RoutingKeyUserBanned      = "user.banned"
)

type UserRegisteredEvent struct {
	Email string `json:"email"`
	Code  string `json:"code"`
}
type UserEmailVerifiedEvent struct {
	Email string `json:"email"`
	Name  string `json:"name"`
}
type UserRoleChangedEvent struct {
	UserID    string `json:"user_id"`
	AdminID   string `json:"admin_id"`
	NewRole   string `json:"new_role"`
	Timestamp int64  `json:"timestamp"`
}

type UserBannedEvent struct {
	UserID    string `json:"user_id"`
	AdminID   string `json:"admin_id"`
	IsBanned  bool   `json:"is_banned"`
	Timestamp int64  `json:"timestamp"`
}
