package config

import (
	"crypto/rsa"
	"encoding/base64"
	"fmt"
	"net/url"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"github.com/ilyakaznacheev/cleanenv"
	"golang.org/x/oauth2"
	"golang.org/x/oauth2/google"
)

type AppConfig struct {
	Port            string        `env:"APP_PORT" env-default:"8080"`
	Env             string        `env:"APP_ENV" env-default:"local"`
	ShutdownTimeout time.Duration `env:"SHUTDOWN_TIMEOUT" env-default:"10s"`
	AutoMigrate     bool          `env:"AUTO_MIGRATE" env-default:"true"`
}
type PostgresConfig struct {
	DatabaseURL string `env:"DATABASE_URL" env-required:"true"`
}
type RedisConfig struct {
	RedisURL string `env:"REDIS_URL" env-required:"true"`
}
type RabbitConfig struct {
	RabbitURL string `env:"RABBITMQ_URL" env-default:"amqp://guest:guest@auth-rabbitmq:5672/"`
}
type JWTConfig struct {
	JwtPrivateKeyBase64 string `env:"JWT_PRIVATE_KEY_BASE64" env-required:"true"`
	JwtPublicKeyBase64  string `env:"JWT_PUBLIC_KEY_BASE64" env-required:"true"`
}
type OAuthConfig struct {
	FrontendURL  string `env:"FRONTEND_URL" env-default:"https://localhost"`
	ClientID     string `env:"CLIENT_ID" env-required:"true"`
	ClientSecret string `env:"CLIENT_SECRET" env-required:"true"`
	RedirectURL  string `env:"REDIRECT_URL" env-required:"true"`
}

type Config struct {
	Rabbit   RabbitConfig
	OAuth    OAuthConfig
	JWT      JWTConfig
	Postgres PostgresConfig
	Redis    RedisConfig
	App      AppConfig
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

func (c *Config) OAuthStateGoogle() *oauth2.Config {
	googleConfig := oauth2.Config{
		ClientID:     c.OAuth.ClientID,
		ClientSecret: c.OAuth.ClientSecret,
		RedirectURL:  c.OAuth.RedirectURL,
		Endpoint:     google.Endpoint,
		Scopes: []string{
			"openid",
			"email",
			"profile",
		},
	}

	return &googleConfig
}

// FrontendURL is a trusted HTTPS origin, not an arbitrary redirect target.
func (c *Config) ValidateOAuth() error {
	u, err := url.Parse(c.OAuth.FrontendURL)
	if err != nil || u.Scheme != "https" || u.Host == "" || u.Hostname() == "" || u.User != nil || u.Path != "" || u.RawQuery != "" || u.ForceQuery || u.Fragment != "" || u.Opaque != "" {
		return fmt.Errorf("FRONTEND_URL must be an HTTPS origin without credentials, path, query or fragment")
	}
	return nil
}
