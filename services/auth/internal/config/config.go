// Package config provides application configuration loading and key parsing.
package config

import (
	"crypto/rsa"
	"encoding/base64"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"github.com/ilyakaznacheev/cleanenv"
)

// AppConfig holds application server settings.
type AppConfig struct {
	Port            string        `env:"APP_PORT" env-default:"8080"`
	Env             string        `env:"APP_ENV" env-default:"local"`
	ShutdownTimeout time.Duration `env:"SHUTDOWN_TIMEOUT" env-default:"10s"`
	AutoMigrate     bool          `env:"AUTO_MIGRATE" env-default:"true"`
}

// PostgresConfig holds PostgreSQL database connection parameters.
type PostgresConfig struct {
	DatabaseURL string `env:"DATABASE_URL" env-required:"true"`
}

// RedisConfig holds Redis database connection parameters.
type RedisConfig struct {
	RedisURL string `env:"REDIS_URL" env-required:"true"`
}

// RabbitConfig holds RabbitMQ connection parameters and exchange configurations.
type RabbitConfig struct {
	RabbitURL    string `env:"RABBITMQ_URL" env-default:"amqp://guest:guest@auth-rabbitmq:5672/"`
	ExchangeName string `env:"EXCHANGE_NAME" env-default:"events_exchange"`
	ExchangeType string `env:"EXCHANGE_TYPE" env-default:"direct"`
}

// JWTConfig holds JWT RSA keys in base64 format and algorithm settings.
type JWTConfig struct {
	JwtPrivateKeyBase64 string `env:"JWT_PRIVATE_KEY_BASE64" env-required:"true"`
	JwtPublicKeyBase64  string `env:"JWT_PUBLIC_KEY_BASE64" env-required:"true"`
}

// Config aggregates all application configuration sections.
type Config struct {
	Rabbit   RabbitConfig
	JWT      JWTConfig
	Postgres PostgresConfig
	Redis    RedisConfig
	App      AppConfig
}

// MustLoad reads configuration from environment variables or .env file, terminating on failure.
func MustLoad() *Config {
	var cfg Config

	if err := cleanenv.ReadConfig(".env", &cfg); err != nil {
		if envErr := cleanenv.ReadEnv(&cfg); envErr != nil {
			panic(envErr)
		}
	}

	return &cfg
}

// RSAPrivateKey decodes and parses the RSA private key from Base64 PEM format.
func (c *Config) RSAPrivateKey() *rsa.PrivateKey {
	pemBytes, err := base64.StdEncoding.DecodeString(c.JWT.JwtPrivateKeyBase64)
	if err != nil {
		panic(err)
	}

	key, err := jwt.ParseRSAPrivateKeyFromPEM(pemBytes)
	if err != nil {
		panic(err)
	}

	return key
}

// RSAPublicKey decodes and parses the RSA public key from Base64 PEM format.
func (c *Config) RSAPublicKey() *rsa.PublicKey {
	pemBytes, err := base64.StdEncoding.DecodeString(c.JWT.JwtPublicKeyBase64)
	if err != nil {
		panic(err)
	}

	key, err := jwt.ParseRSAPublicKeyFromPEM(pemBytes)
	if err != nil {
		panic(err)
	}

	return key
}
