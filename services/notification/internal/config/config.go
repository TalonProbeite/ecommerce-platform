package config

import (
	"time"

	"github.com/ilyakaznacheev/cleanenv"
)

type AppConfig struct {
	Port            string        `env:"APP_PORT" env-default:"8080"`
	Env             string        `env:"APP_ENV" env-default:"local"`
	ShutdownTimeout time.Duration `env:"SHUTDOWN_TIMEOUT" env-default:"10s"`
}

type MongoDBConfig struct {
	URI          string `env:"MONGO_URI" env-required:"true"`
	DatabaseName string `env:"MONGO_DB_NAME" env-required:"true"`
}

type RabbitConfig struct {
	RabbitURL string `env:"RABBITMQ_URL" env-required:"true"`
}

type SMTPConfig struct {
	SMTPHost string `env:"SMTP_HOST" env-required:"true"`
	SMTPPort string `env:"SMTP_PORT" env-default:"587"`
	SMTPUser string `env:"SMTP_USER" env-required:"true"`
	SMTPPass string `env:"SMTP_PASS" env-required:"true"`
	From     string `env:"SMTP_FROM" env-required:"true"`
}

type Config struct {
	SMTP   SMTPConfig
	Mongo  MongoDBConfig
	Rabbit RabbitConfig
	App    AppConfig
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
