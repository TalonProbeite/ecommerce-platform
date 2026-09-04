package config

import (
	"crypto/rsa"
	"encoding/base64"
	"log"
	"time"
	"github.com/golang-jwt/jwt/v5"
	"github.com/ilyakaznacheev/cleanenv"
)


type AppConfig struct {
	Port string `env:"APP_PORT" env-default:"8080"`
	Env string `env:"APP_ENV" env-default:"local"`
	ShutdownTimeout  time.Duration `env:"SHUTDOWN_TIMEOUT" env-default:"10s"`
	AutoMigrate bool `env:"AUTO_MIGRATE" env-default:"true"`
}



type PostgresConfig struct {
	DatabaseURL string `env:"DATABASE_URL" env-required:"true"`
}

type RedisConfig struct {
	RedisURL string `env:"REDIS_URL" env-required:"true"`
}


type RabbitConfig struct {
	RabbitURL string `env:"RABBITMQ_URL" env-default:"amqp://guest:guest@auth-rabbitmq:5672/"`
	ExchangeName string `env:"EXCHANGE_NAME" env-default:"events_exchange"`
	ExchangeType string `env:"EXCHANGE_TYPE" env-default:"direct"`
}


type JWTConfig struct {
	JwtPrivateKeyBase64 string `env:"JWT_PRIVATE_KEY_BASE64" env-required:"true"`
 	JwtPublicKeyBase64  string `env:"JWT_PUBLIC_KEY_BASE64" env-required:"true"`
	Algorithm string `env:"ALGORITHM_JWT" env-default:"RS256"`
}


type Config struct {
	App AppConfig 
	Postgres PostgresConfig
	Redis RedisConfig
	Rabbit RabbitConfig
	JWT JWTConfig
}



func MustLoad() *Config {
	var cfg Config

	if err := cleanenv.ReadConfig(".env", &cfg); err != nil {
		if envErr := cleanenv.ReadEnv(&cfg); envErr != nil {
			log.Fatalf("failed to load configuration: %v", envErr)
		}
	}

	return &cfg
}

func (c *Config) RSAPrivateKey() *rsa.PrivateKey {
	pemBytes, err := base64.StdEncoding.DecodeString(c.JWT.JwtPrivateKeyBase64)
	if err != nil {
		log.Fatalf("failed to decode base64 private key: %v", err)
	}

	key, err := jwt.ParseRSAPrivateKeyFromPEM(pemBytes)
	if err != nil {
		log.Fatalf("invalid RSA private key: %v", err)
	}

	return key
}

func (c *Config) RSAPublicKey() *rsa.PublicKey {
	pemBytes, err := base64.StdEncoding.DecodeString(c.JWT.JwtPublicKeyBase64)
	if err != nil {
		log.Fatalf("failed to decode base64 public key: %v", err)
	}

	key, err := jwt.ParseRSAPublicKeyFromPEM(pemBytes)
	if err != nil {
		log.Fatalf("invalid RSA public key: %v", err)
	}

	return key
}