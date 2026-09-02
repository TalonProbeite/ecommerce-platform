package config

import (
	"crypto/rsa"
	"encoding/base64"
	"fmt"
	"log"

	"github.com/golang-jwt/jwt/v5"
	"github.com/ilyakaznacheev/cleanenv"
)

type Config struct {
	Env                 string `env:"ENV" env-default:"local"`
	Port                string `env:"APP_PORT" env-default:"8080"`
	DBHost              string `env:"DB_HOST" env-default:"localhost"`
	DBPort              string `env:"DB_PORT" env-default:"5432"`
	DBUser              string `env:"DB_USER" env-required:"true"`
	DBPass              string `env:"DB_PASS" env-required:"true"`
	DBName              string `env:"DB_NAME" env-required:"true"`
	AutoMigrate         bool   `env:"AUTO_MIGRATE" env-default:"false"`
	JwtPrivateKeyBase64 string `env:"JWT_PRIVATE_KEY_BASE64" env-required:"true"`
	JwtPublicKeyBase64  string `env:"JWT_PUBLIC_KEY_BASE64" env-required:"true"`
	RedisHost           string `env:"REDIS_HOST" env-default:"localhost"`
	RedisPort           string `env:"REDIS_PORT" env-default:"6379"`
	RedisPass           string `env:"REDIS_PASSWORD" env-default:""`
	RedisDB             int    `env:"REDIS_DB" env-default:"0"`
	RabbitHost          string `env:"RABBITMQ_HOST" env-default:"localhost"`
	RabbitPort          string `env:"RABBITMQ_PORT" env-default:"5672"`
	RabbitUser          string `env:"RABBITMQ_USER" env-default:"guest"`
	RabbitPass          string `env:"RABBITMQ_PASS" env-default:"guest"`
}

func (c *Config) PostgresDSN() string {
	return fmt.Sprintf("postgres://%s:%s@%s:%s/%s?sslmode=disable",
		c.DBUser, c.DBPass, c.DBHost, c.DBPort, c.DBName,
	)
}

func (c *Config) RedisAddr() string {
	return fmt.Sprintf("%s:%s", c.RedisHost, c.RedisPort)
}

func (c *Config) RabbitURL() string {
	return fmt.Sprintf("amqp://%s:%s@%s:%s/",
		c.RabbitUser, c.RabbitPass, c.RabbitHost, c.RabbitPort,
	)
}

func (c *Config) RSAPrivateKey() *rsa.PrivateKey {
	pemBytes, err := base64.StdEncoding.DecodeString(c.JwtPrivateKeyBase64)
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
	pemBytes, err := base64.StdEncoding.DecodeString(c.JwtPublicKeyBase64)
	if err != nil {
		log.Fatalf("failed to decode base64 public key: %v", err)
	}

	key, err := jwt.ParseRSAPublicKeyFromPEM(pemBytes)
	if err != nil {
		log.Fatalf("invalid RSA public key: %v", err)
	}

	return key
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
