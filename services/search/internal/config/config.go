package config

import (
	"crypto/rsa"
	"encoding/base64"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"github.com/ilyakaznacheev/cleanenv"
)

type RedisConfig struct {
	RedisURL string `env:"REDIS_URL" env-required:"true"`
}

type RabbitConfig struct {
	RabbitURL string `env:"RABBITMQ_URL" env-default:"amqp://guest:guest@auth-rabbitmq:5672/"`
}

type AppConfig struct {
	Port            string        `env:"APP_PORT" env-default:"8080"`
	Env             string        `env:"APP_ENV" env-default:"local"`
	ShutdownTimeout time.Duration `env:"SHUTDOWN_TIMEOUT" env-default:"10s"`
}

type MeilisearchConfig struct {
	MeiliUrl string `env:"MEILI_URL" env-required:"true"`
	APIKey   string `env:"MEILI_API_KEY" env-required:"true"`
}
type JWTConfig struct {
	JwtPublicKeyBase64 string `env:"JWT_PUBLIC_KEY_BASE64" env-required:"true"`
}

type Config struct {
	Rabbit RabbitConfig
	Redis  RedisConfig
	App    AppConfig
	Meli   MeilisearchConfig
	JWT    JWTConfig
}

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

func MustLoad() *Config {
	var cfg Config

	if err := cleanenv.ReadConfig(".env", &cfg); err != nil {
		if envErr := cleanenv.ReadEnv(&cfg); envErr != nil {
			panic(envErr)
		}
	}

	return &cfg
}
