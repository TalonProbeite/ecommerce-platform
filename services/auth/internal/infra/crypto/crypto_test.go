package crypto_test

import (
	"encoding/base64"
	"strings"
	"testing"

	"shop/auth/internal/infra/crypto"
)

func TestGenerateCode(t *testing.T) {
	t.Parallel()

	const codeLength = 32

	code, err := crypto.GenerateCode(codeLength)
	if err != nil {
		t.Fatalf("GenerateCode returned an error: %v", err)
	}

	if len(code) != codeLength {
		t.Fatalf("GenerateCode length = %d, want %d", len(code), codeLength)
	}

	for _, char := range code {
		if !strings.ContainsRune("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789", char) {
			t.Fatalf("GenerateCode produced an unexpected character %q", char)
		}
	}

	empty, err := crypto.GenerateCode(0)
	if err != nil {
		t.Fatalf("GenerateCode(0) returned an error: %v", err)
	}

	if empty != "" {
		t.Fatalf("GenerateCode(0) = %q, want an empty string", empty)
	}
}

func TestHashPasswordRoundTrip(t *testing.T) {
	t.Parallel()

	const password = "Password123!"

	hash, err := crypto.HashPassword(password)
	if err != nil {
		t.Fatalf("HashPassword returned an error: %v", err)
	}

	if hash == "" {
		t.Fatal("HashPassword returned an empty hash")
	}

	if hash == password {
		t.Fatal("HashPassword returned the plain password")
	}

	if !crypto.CheckPasswordHash(password, hash) {
		t.Fatal("CheckPasswordHash rejected the correct password")
	}

	if crypto.CheckPasswordHash("wrong-password", hash) {
		t.Fatal("CheckPasswordHash accepted a wrong password")
	}

	if crypto.CheckPasswordHash(password, "not-a-bcrypt-hash") {
		t.Fatal("CheckPasswordHash accepted an invalid hash")
	}
}

func TestGenerateRandomToken(t *testing.T) {
	t.Parallel()

	const tokenSize = 32

	token, err := crypto.GenerateRandomToken(tokenSize)
	if err != nil {
		t.Fatalf("GenerateRandomToken returned an error: %v", err)
	}

	decoded, err := base64.RawURLEncoding.DecodeString(token)
	if err != nil {
		t.Fatalf("GenerateRandomToken is not URL-safe base64: %v", err)
	}

	if len(decoded) != tokenSize {
		t.Fatalf("decoded token length = %d, want %d", len(decoded), tokenSize)
	}

	other, err := crypto.GenerateRandomToken(tokenSize)
	if err != nil {
		t.Fatalf("GenerateRandomToken returned an error: %v", err)
	}

	if token == other {
		t.Fatal("GenerateRandomToken returned the same token twice")
	}

	empty, err := crypto.GenerateRandomToken(0)
	if err != nil {
		t.Fatalf("GenerateRandomToken(0) returned an error: %v", err)
	}

	if empty != "" {
		t.Fatalf("GenerateRandomToken(0) = %q, want an empty string", empty)
	}
}
