package crypto

import (
	"crypto/rand"
	"encoding/base64"
)

// GenerateRefreshToken creates a secure random 32-byte URL-safe base64 refresh token string.
func GenerateRefreshToken() (string, error) {
	buffer := make([]byte, 32)

	if _, err := rand.Read(buffer); err != nil {
		return "", err
	}

	return base64.RawURLEncoding.EncodeToString(buffer), nil
}
