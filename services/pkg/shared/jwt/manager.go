package jwt

import (
	"crypto/rsa"
	"errors"
	"fmt"
	"time"

	"github.com/golang-jwt/jwt/v5"

	"shop/shared/roles"
)

const defaultTokenTTL = 15 * time.Minute

var (
	ErrPrivateKeyRequired = errors.New("jwt: private key is required")
	ErrPublicKeyRequired  = errors.New("jwt: public key is required")
)

type JWTManager struct {
	privateKey *rsa.PrivateKey
	publicKey  *rsa.PublicKey
	tokenTTL   time.Duration
}

func NewJWTManager(
	publicKey *rsa.PublicKey,
	privateKeys ...*rsa.PrivateKey,
) *JWTManager {
	var privateKey *rsa.PrivateKey

	if len(privateKeys) > 0 {
		privateKey = privateKeys[0]
	}

	if publicKey == nil && privateKey != nil {
		publicKey = &privateKey.PublicKey
	}

	return &JWTManager{
		privateKey: privateKey,
		publicKey:  publicKey,
		tokenTTL:   defaultTokenTTL,
	}
}

func (m *JWTManager) WithTokenTTL(ttl time.Duration) *JWTManager {
	manager := *m
	manager.tokenTTL = ttl

	return &manager
}

type CustomClaim struct {
	UserID string     `json:"user_id"`
	Role   roles.Role `json:"role"`

	jwt.RegisteredClaims
}

func (m *JWTManager) GenerateToken(
	userID string,
	role ...roles.Role,
) (string, error) {
	if m.privateKey == nil {
		return "", ErrPrivateKeyRequired
	}

	currentRole := roles.RoleCustomer
	if len(role) > 0 {
		currentRole = role[0]
	}

	now := time.Now()

	claims := CustomClaim{
		UserID: userID,
		Role:   currentRole,
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(now.Add(m.tokenTTL)),
			IssuedAt:  jwt.NewNumericDate(now),
		},
	}

	token := jwt.NewWithClaims(jwt.SigningMethodRS256, claims)

	signedToken, err := token.SignedString(m.privateKey)
	if err != nil {
		return "", fmt.Errorf("jwt: sign token: %w", err)
	}

	return signedToken, nil
}

func (m *JWTManager) VerifyToken(
	tokenString string,
) (string, roles.Role, error) {
	if m.publicKey == nil {
		return "", "", ErrPublicKeyRequired
	}

	claims := &CustomClaim{}

	token, err := jwt.ParseWithClaims(
		tokenString,
		claims,
		func(token *jwt.Token) (any, error) {
			if token.Method != jwt.SigningMethodRS256 {
				return nil, fmt.Errorf(
					"unexpected signing method: %v",
					token.Header["alg"],
				)
			}

			return m.publicKey, nil
		},
		jwt.WithValidMethods([]string{
			jwt.SigningMethodRS256.Alg(),
		}),
	)
	if err != nil {
		return "", "", fmt.Errorf("jwt: parse token: %w", err)
	}

	if !token.Valid {
		return "", "", errors.New("jwt: token is invalid")
	}

	if claims.UserID == "" {
		return "", "", errors.New("jwt: user_id claim is missing")
	}

	if claims.Role == "" {
		return "", "", errors.New("jwt: role claim is missing")
	}

	return claims.UserID, claims.Role, nil
}
