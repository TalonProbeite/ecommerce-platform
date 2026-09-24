package crypto

import (
	"crypto/rand"
	"encoding/base64"
	"fmt"
)

func GenerateRandomToken(size int) (string, error) {
	buffer := make([]byte, size)

	if _, err := rand.Read(buffer); err != nil {
		return "", fmt.Errorf("failed to read random bytes: %w", err)
	}

	return base64.RawURLEncoding.EncodeToString(buffer), nil
}
