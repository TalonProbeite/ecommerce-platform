package domain

const (
	UserRegisteredEventKey    = "user.registered"
	UserEmailVerifiedEventKey = "user.email_verified"
	OrderPaidEventKey         = "order.paid"
	OrderConfirmedEventKey    = "order.confirmed"
	OrderCancelledEventKey    = "order.cancelled"
)

type UserRegisteredEvent struct {
	Email string `json:"email"`
	Code  string `json:"code"`
}
type UserEmailVerifiedEvent struct {
	Email string `json:"email"`
	Name  string `json:"name"`
}
