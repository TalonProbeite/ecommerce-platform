package events

const (
	UserRegisteredEventKey    = "user.registered"
	UserEmailVerifiedEventKey = "user.email_verified"
	OrderPaidEventKey         = "order.paid"
	OrderConfirmedEventKey    = "order.confirmed"
	OrderCancelledEventKey    = "order.cancelled"
)

const (
	UserRegisteredSubject    = "Welcome aboard"
	UserEmailVerifiedSubject = "Email verified"
	OrderPaidSubject         = "Payment confirmed"
	OrderConfirmedSubject    = "Order confirmed"
	OrderCancelledSubject    = "Order canceled"
)

type UserRegisteredEvent struct {
	Email string `json:"email"`
	Code  string `json:"code"`
}
type UserEmailVerifiedEvent struct {
	Email string `json:"email"`
	Name  string `json:"name"`
}
type OrderPaidEvent struct {
	OrderID string  `json:"order_id"`
	Email   string  `json:"email"`
	Amount  float64 `json:"amount"`
}
type OrderConfirmedEvent struct {
	OrderID string `json:"order_id"`
	Email   string `json:"email"`
}
type OrderCancelledPayload struct {
	OrderID string `json:"order_id"`
	Email   string `json:"email"`
	Reason  string `json:"reason"`
}

const (
	RoutingKeyUserRoleChanged = "user.role_changed"
	RoutingKeyUserBanned      = "user.banned"
)

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
