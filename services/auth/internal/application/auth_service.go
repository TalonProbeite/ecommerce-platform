package application

import (
	"context"
	"encoding/json"
	"fmt"
	"shop/auth/internal/domain"
	"shop/auth/internal/infra/crypto"
	"shop/auth/internal/transport/http/dto"
	"time"

	"github.com/google/uuid"
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

func (as AuthService) Registration(ctx context.Context, userData *dto.RegisterRequest) error{
    hashPassword , err:= crypto.HashPassword(userData.Password)
    if err != nil {
        return  fmt.Errorf("error while hashing password: %w", err)
    }
    userId , err := as.userRepo.Create(ctx ,domain.User{
                                Email: userData.Email,
                                Password: hashPassword,
                                Role: "customer",
                                IsActive: true,
                                IsEmailVerified: false,
                                FirstName: userData.FirstName,
                                LastName: userData.LastName,
                                Phone: userData.Phone})

    if userId == uuid.Nil {
        return fmt.Errorf("user repository returned empty user id")
    }
    
    if err != nil {
        return fmt.Errorf("error while trying to save user: %w", err)
    }
    code , err := crypto.GenerateCode(10)
    if err != nil {
        return  fmt.Errorf("error generating confirmation code: %w", err)
    }
    payload , err := json.Marshal(domain.UserRegisteredEvent{
                                        Email: userData.Email,
                                        Code: code})
    if err != nil {
        return  fmt.Errorf("error while creating json struct for event: %w", err)
    }

    err = as.publisher.PublishEvent("user.registered", payload)
    if err != nil {
        return  fmt.Errorf("error while publishing event: %w", err)
    }

    refresh , err := crypto.GenerateRefreshToken()
    if err != nil {
        return fmt.Errorf("error creating refresh token: %w", err)
    }
    userIdString := userId.String()
    access, err :=  as.tokenMng.GenerateToken(userIdString, "customer")

    if err != nil {
        return fmt.Errorf("access key generation error: %w")
    }
    refKey := fmt.Sprintln("refresh:{%s}",refresh)
    acKey := fmt.Sprintln("access:{%s}", access)
    verKey := fmt.Sprintln("ver:{%s}", code)
   
    err = as.sessRepo.SaveEntry(ctx, refKey, refresh, 7*24*time.Hour)
    if err != nil {
        return fmt.Errorf("error saving refresh token: %w", err)
    }

    err = as.sessRepo.SaveEntry(ctx, acKey, access, 15*time.Minute)
    if err != nil {
        return fmt.Errorf("error saving access token: %w", err)
    }

    err = as.sessRepo.SaveEntry(ctx, verKey, code, 15*time.Minute)
    if err != nil {
        return fmt.Errorf("error saving refresh token: %w", err)
    }



}