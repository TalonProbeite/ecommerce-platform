package crypto

import (
	"crypto/rsa"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

type CustomClaim struct {
	UserId string `json:"user_id"`
	Role   string `json:"role"`
	jwt.RegisteredClaims
}

func GenerateToken(key *rsa.PrivateKey, userId string, role ...string) (string, error) {
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

	signedToken, err := token.SignedString(key)
	if err != nil {
		return "", err
	}

	return signedToken, nil
}
