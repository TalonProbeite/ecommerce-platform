package crypto

import (
	"crypto/rsa"
	"fmt"
	"shop/notification/internal/domain"

	"github.com/golang-jwt/jwt/v5"
)

type JWTManager struct {
	publicKey *rsa.PublicKey
}

func NewJWTManager(key *rsa.PublicKey) *JWTManager {
	return &JWTManager{
		publicKey: key,
	}
}

func (m *JWTManager) VerifyToken(tokenString string) (string, domain.Role, error) {
	token, err := jwt.Parse(tokenString, func(token *jwt.Token) (interface{}, error) {
		if _, ok := token.Method.(*jwt.SigningMethodRSA); !ok {
			return nil, fmt.Errorf("unexpected signing method: %v", token.Header["alg"])
		}
		return m.publicKey, nil
	})

	if err != nil || !token.Valid {
		return "", "", fmt.Errorf("invalid token: %w", err)
	}

	claims, ok := token.Claims.(jwt.MapClaims)
	if !ok {
		return "", "", fmt.Errorf("invalid token claims")
	}

	UserID, ok := claims["user_id"].(string)
	if !ok {
		return "", "", fmt.Errorf("UserID not found in token")
	}

	role, ok := claims["role"].(string)
	if !ok {
		return "", "", fmt.Errorf("role not found in token")
	}

	return UserID, domain.Role(role), nil
}
