package crypto

import (
	"crypto/rand"
	"math/big"
)

const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789"

// GenerateCode creates a random alphanumeric verification code of the specified length.
func GenerateCode(length int) (string, error) {
	code := make([]byte, length)

	for index := range code {
		number, err := rand.Int(rand.Reader, big.NewInt(int64(len(alphabet))))
		if err != nil {
			return "", err
		}

		code[index] = alphabet[number.Int64()]
	}

	return string(code), nil
}
