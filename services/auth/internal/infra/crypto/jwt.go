package crypto

import (
	"crypto/rsa"
	"fmt"
	"shop/auth/internal/domain"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

type JWTManager struct {
	privateKey *rsa.PrivateKey
}

func NewJWTManager(key *rsa.PrivateKey) *JWTManager {
	return &JWTManager{
		privateKey: key,
	}
}

type CustomClaim struct {
	UserID string      `json:"user_id"`
	Role   domain.Role `json:"role"`
	jwt.RegisteredClaims
}

func (m *JWTManager) GenerateToken(UserID string, role ...domain.Role) (string, error) {
	currentRole := domain.RoleCustomer
	if len(role) > 0 {
		currentRole = role[0]
	}

	claims := CustomClaim{
		UserID: UserID,
		Role:   currentRole,
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(15 * time.Minute)),
			IssuedAt:  jwt.NewNumericDate(time.Now()),
		},
	}

	token := jwt.NewWithClaims(jwt.SigningMethodRS256, claims)

	signedToken, err := token.SignedString(m.privateKey)
	if err != nil {
		return "", err
	}

	return signedToken, nil
}

func (m *JWTManager) VerifyToken(tokenString string) (string, domain.Role, error) {
	token, err := jwt.Parse(tokenString, func(token *jwt.Token) (interface{}, error) {
		if token.Method != jwt.SigningMethodRS256 {
			return nil, fmt.Errorf("unexpected signing method: %v", token.Header["alg"])
		}
		return &m.privateKey.PublicKey, nil
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
