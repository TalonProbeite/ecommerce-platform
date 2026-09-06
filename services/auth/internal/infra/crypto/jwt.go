package crypto

import (
	"crypto/rsa"
	"fmt"
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
	UserId string `json:"user_id"`
	Role   string `json:"role"`
	jwt.RegisteredClaims
}

func (m *JWTManager) GenerateToken(userId string, role ...string) (string, error) {
	currentRole := "customer"
	if len(role) > 0 {
		currentRole = role[0]
	}

	claims := CustomClaim{
		UserId: userId,
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

func (tm *JWTManager) VerifyToken(tokenString string) (string, string, error) {
	token, err := jwt.Parse(tokenString, func(token *jwt.Token) (interface{}, error) {
		if _, ok := token.Method.(*jwt.SigningMethodRSA); !ok {
			return nil, fmt.Errorf("unexpected signing method: %v", token.Header["alg"])
		}
		return &tm.privateKey.PublicKey, nil
	})

	if err != nil || !token.Valid {
		return "", "", fmt.Errorf("invalid token: %w", err)
	}

	claims, ok := token.Claims.(jwt.MapClaims)
	if !ok {
		return "", "", fmt.Errorf("invalid token claims")
	}

	userID, ok := claims["user_id"].(string)
	if !ok {
		return "", "", fmt.Errorf("userID not found in token")
	}

	role, ok := claims["role"].(string)
	if !ok {
		return "", "", fmt.Errorf("role not found in token")
	}

	return userID, role, nil
}
