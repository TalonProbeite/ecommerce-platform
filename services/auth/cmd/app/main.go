package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/labstack/echo/v4"
	"github.com/labstack/echo/v4/middleware"

	"shop/auth/internal/config"
	"shop/auth/internal/handler"
	"shop/auth/internal/infra/db"
	"shop/auth/internal/infra/logger"
	"shop/auth/internal/infra/rabbitmq"
)

const shutdownTimeout = 10 * time.Second

func main() {
	cfg := config.MustLoad()

	log := logger.Init(cfg.Env)

	pg, err := db.NewPostgresDB(cfg.PostgresDSN())
	if err != nil {
		log.Error("failed to connect to postgres", slog.Any("err", err))
		os.Exit(1)
	}
	defer func() {
		if err := pg.Close(); err != nil {
			log.Error("failed to close postgres connection", slog.Any("err", err))
		}
	}()

	if cfg.AutoMigrate {
		if err := db.RunMigrations(cfg.PostgresDSN()); err != nil {
			log.Error("failed to run migrations", slog.Any("err", err))
			os.Exit(1)
		}
	}

	rdb, err := db.NewRedisClient(cfg.RedisAddr(), cfg.RedisPass, cfg.RedisDB)
	if err != nil {
		log.Error("failed to connect to redis", slog.Any("err", err))
		os.Exit(1)
	}
	defer func() {
		if err := rdb.Close(); err != nil {
			log.Error("failed to close redis connection", slog.Any("err", err))
		}
	}()

	rabbit, err := rabbitmq.NewRabbitClient(cfg.RabbitURL())
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
	e.Use(middleware.Recover())

	healthHandler := handler.NewHealthHandler()
	readyzHandler := handler.NewReadyzHandler(pg ,rdb,rabbit)
	e.GET("/healthz", healthHandler.Check)
	e.GET("/readyz", readyzHandler.Check)

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	serverErrCh := make(chan error, 1)

	go func() {
		log.Info("starting http server", slog.String("port", cfg.Port))
		if err := e.Start(":" + cfg.Port); err != nil && !errors.Is(err, http.ErrServerClosed) {
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

	shutdownCtx, cancel := context.WithTimeout(context.Background(), shutdownTimeout)
	defer cancel()

	if err := e.Shutdown(shutdownCtx); err != nil {
		log.Error("failed to gracefully shutdown http server", slog.Any("err", err))
	} else {
		log.Info("http server stopped gracefully")
	}

	log.Info("closing infrastructure connections")
}
