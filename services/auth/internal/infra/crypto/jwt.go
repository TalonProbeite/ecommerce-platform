package crypto

import (
	"crypto/rsa"
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
