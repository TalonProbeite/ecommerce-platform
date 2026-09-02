package application 

import (
    "shop/auth/internal/domain"
)

type AuthService struct {
    userRepo   domain.UserRepository
    sessRepo   domain.SessionRepository
    publisher  domain.EventPublisher
    tokenMng   domain.TokenManager
}


func NewAuthService(
    ur domain.UserRepository,
    sr domain.SessionRepository,
    ep domain.EventPublisher,
    tm domain.TokenManager,
) *AuthService {
    return &AuthService{
        userRepo: ur, sessRepo: sr, publisher: ep, tokenMng: tm,
    }
}