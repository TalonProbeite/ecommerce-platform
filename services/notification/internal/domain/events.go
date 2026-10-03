package domain

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
