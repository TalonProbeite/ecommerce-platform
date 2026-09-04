package domain 


type  UserRegisteredEvent struct {
	Email string `json:"email"`
	Code  string `json:"code"`
}

type UserEmailVerifiedEvent struct {
	Email string `json:"email"`
	Name  string `json:"name"`
}