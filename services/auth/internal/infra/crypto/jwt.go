// Package crypto provides cryptographic utilities including password hashing and JWT management.
package crypto

import (
	"crypto/rsa"
	"fmt"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

// JWTManager handles JWT generation and validation using RSA keys.
type JWTManager struct {
	privateKey *rsa.PrivateKey
}

// NewJWTManager constructs a new JWTManager with the given RSA private key.
func NewJWTManager(key *rsa.PrivateKey) *JWTManager {
	return &JWTManager{
		privateKey: key,
	}
}

// CustomClaim represents custom claims embedded inside JWT access tokens.
type CustomClaim struct {
	UserID string `json:"user_id"`
	Role   string `json:"role"`
	jwt.RegisteredClaims
}

// GenerateToken creates a signed RSA256 JWT access token for a given user ID and role.
func (m *JWTManager) GenerateToken(userID string, role ...string) (string, error) {
	currentRole := "customer"
	if len(role) > 0 {
		currentRole = role[0]
	}

	claims := CustomClaim{
		UserID: userID,
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

// VerifyToken parses and validates an RSA-signed JWT token string.
func (m *JWTManager) VerifyToken(tokenString string) (string, string, error) {
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