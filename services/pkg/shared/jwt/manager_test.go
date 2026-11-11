package jwt

import (
	"crypto/rand"
	"crypto/rsa"
	"testing"

	"shop/shared/roles"

	jwtlib "github.com/golang-jwt/jwt/v5"
)

func newTestManager(t *testing.T) *JWTManager {
	t.Helper()

	key, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		t.Fatalf("generate key: %v", err)
	}

	return NewJWTManager(&key.PublicKey, key)
}

func parseClaims(t *testing.T, manager *JWTManager, token string) *CustomClaim {
	t.Helper()

	claims := &CustomClaim{}
	parsed, err := jwtlib.ParseWithClaims(token, claims, func(*jwtlib.Token) (any, error) {
		return manager.publicKey, nil
	})
	if err != nil {
		t.Fatalf("parse token: %v", err)
	}
	if !parsed.Valid {
		t.Fatal("token is invalid")
	}

	return claims
}

func TestGenerateTokenIncludesStoreIDForStoreManager(t *testing.T) {
	manager := newTestManager(t)
	storeID := "33333333-3333-3333-3333-333333333333"

	token, err := manager.GenerateToken("user-id", roles.RoleStoreManager, &storeID)
	if err != nil {
		t.Fatalf("generate token: %v", err)
	}

	claims := parseClaims(t, manager, token)
	if claims.StoreID == nil || *claims.StoreID != storeID {
		t.Errorf("store id claim = %v, want %q", claims.StoreID, storeID)
	}
}

func TestGenerateTokenOmitsStoreIDForOtherRoles(t *testing.T) {
	manager := newTestManager(t)
	storeID := "33333333-3333-3333-3333-333333333333"

	token, err := manager.GenerateToken("user-id", roles.RoleAdmin, &storeID)
	if err != nil {
		t.Fatalf("generate token: %v", err)
	}

	claims := parseClaims(t, manager, token)
	if claims.StoreID != nil {
		t.Errorf("store id claim = %v, want nil", claims.StoreID)
	}
}
