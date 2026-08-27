package main

import (
	"log"

	"github.com/labstack/echo/v4"

	"shop/auth/internal/config"
	"shop/auth/internal/handler"
	"shop/auth/pkg/database"
	"shop/auth/pkg/rabbitmq"
)

func main() {
	cfg := config.MustLoad()

	db, err := database.NewPostgresDB(cfg.PostgresDSN())
	if err != nil {
		log.Fatalf("failed to connect to postgres: %v", err)
	}
	defer db.Close()

	if cfg.AutoMigrate {
		if err := database.RunMigrations(cfg.PostgresDSN()); err != nil {
			log.Fatalf("failed to run migrations: %v", err)
		}
	}

	rdb, err := database.NewRedisClient(cfg.RedisAddr(), cfg.RedisPass, cfg.RedisDB)
	if err != nil {
		log.Fatalf("failed to connect to redis: %v", err)
	}
	defer rdb.Close()

	rabbit, err := rabbitmq.NewRabbitClient(cfg.RabbitURL())
	if err != nil {
		log.Fatalf("failed to connect to rabbitmq: %v", err)
	}
	defer rabbit.Close()

	e := echo.New()

	healthHandler := handler.NewHealthHandler()
	e.GET("/health", healthHandler.Check)

	if err := e.Start(":" + cfg.Port); err != nil {
		e.Logger.Fatal("failed to start server")
	}
}
