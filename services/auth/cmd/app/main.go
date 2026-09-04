package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"shop/auth/internal/config"
	"shop/auth/internal/infra/db"
	"shop/auth/internal/infra/logger"
	"shop/auth/internal/infra/rabbitmq"
	"shop/auth/internal/infra/validator"
	"shop/auth/internal/transport/http/handler"
	"shop/auth/internal/transport/http/middleware"

	"github.com/labstack/echo/v4"
	echomw "github.com/labstack/echo/v4/middleware"
)

func main() {
	cfg := config.MustLoad()

	log := logger.Init(cfg.App.Env)

	pg, err := db.NewPostgresDB(cfg.Postgres.DatabaseURL)
	if err != nil {
		log.Error("failed to connect to postgres", slog.Any("err", err))
		os.Exit(1)
	}
	defer func() {
		if err := pg.Close(); err != nil {
			log.Error("failed to close postgres connection", slog.Any("err", err))
		}
	}()

	if cfg.App.AutoMigrate {
		if err := db.RunMigrations(cfg.Postgres.DatabaseURL); err != nil {
			log.Error("failed to run migrations", slog.Any("err", err))
			os.Exit(1)
		}
	}

	rdb, err := db.NewRedisClient(cfg.Redis.RedisURL)
	if err != nil {
		log.Error("failed to connect to redis", slog.Any("err", err))
		os.Exit(1)
	}
	defer func() {
		if err := rdb.Close(); err != nil {
			log.Error("failed to close redis connection", slog.Any("err", err))
		}
	}()

	rabbit, err := rabbitmq.NewRabbitClient(cfg.Rabbit.RabbitURL)
	if err != nil {
		log.Error("failed to connect to rabbitmq", slog.Any("err", err))
		os.Exit(1)
	}
	defer func() {
		if err := rabbit.Close(); err != nil {
			log.Error("failed to close rabbitmq connection", slog.Any("err", err))
		}
	}()

	e := echo.New()
	e.HideBanner = true
	e.HidePort = true
	e.Validator = validator.New()

	private := e.Group("/api/private")
	public := e.Group("/api")

	e.Use(echomw.Recover())
	e.Use(middleware.RequestLogger(log))

	private.Use(middleware.RequireAuth(cfg.RSAPublicKey()))

	healthHandler := handler.NewHealthHandler()
	readyzHandler := handler.NewReadyzHandler(pg, rdb, rabbit)
	public.GET("/healthz", healthHandler.Check)
	public.GET("/readyz", readyzHandler.Check)

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	serverErrCh := make(chan error, 1)

	go func() {
		log.Info("starting http server", slog.String("port", cfg.App.Port))
		if err := e.Start(":" + cfg.App.Port); err != nil && !errors.Is(err, http.ErrServerClosed) {
			serverErrCh <- err
			return
		}
		serverErrCh <- nil
	}()

	select {
	case <-ctx.Done():
		log.Info("shutdown signal received")
	case err := <-serverErrCh:
		if err != nil {
			log.Error("server failed to start", slog.Any("err", err))
			os.Exit(1)
		}
	}

	shutdownCtx, cancel := context.WithTimeout(context.Background(), cfg.App.ShutdownTimeout)
	defer cancel()

	if err := e.Shutdown(shutdownCtx); err != nil {
		log.Error("failed to gracefully shutdown http server", slog.Any("err", err))
	} else {
		log.Info("http server stopped gracefully")
	}

	log.Info("closing infrastructure connections")
}
