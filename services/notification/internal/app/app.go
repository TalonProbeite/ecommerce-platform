package app

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"shop/notification/internal/config"
	"shop/notification/internal/infra/logger"
	"shop/notification/internal/infra/mongodb"
	"shop/notification/internal/infra/rabbitmq"
	"shop/notification/internal/transport/http/handlers"
	"syscall"

	"github.com/labstack/echo/v4"

	transporthttp "shop/notification/internal/transport/http"
)

type App struct {
	cfg    *config.Config
	log    *slog.Logger
	echo   *echo.Echo
	mongo  *mongodb.MongoClient
	rabbit *rabbitmq.RabbitClient
}

func New(cfg *config.Config) (*App, error) {
	log := logger.Init(cfg.App.Env)

	mongoClient, err := mongodb.NewMongoClient(cfg.Mongo.URI, cfg.Mongo.DatabaseName)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to mongodb: %w", err)
	}

	rabbit, err := rabbitmq.NewRabbitClient(cfg.Rabbit.RabbitURL)
	if err != nil {
		if closeErr := mongoClient.Close(); closeErr != nil {
			log.Error("failed to close mongodb", slog.Any("err", closeErr))
		}
		return nil, fmt.Errorf("failed to connect to rabbitmq: %w", err)
	}

	h := transporthttp.Handlers{
		HealthHandler: handlers.NewHealthHandler(mongoClient.Client, rabbit),
	}

	router := transporthttp.NewRouter(cfg, log, h)

	return &App{
		cfg:    cfg,
		log:    log,
		echo:   router,
		mongo:  mongoClient,
		rabbit: rabbit,
	}, nil
}

func (a *App) Run() error {
	defer a.closeResources()

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	serverErrCh := make(chan error, 1)

	go func() {
		a.log.Info("starting http server", slog.String("port", a.cfg.App.Port))
		if err := a.echo.Start(":" + a.cfg.App.Port); err != nil && !errors.Is(err, http.ErrServerClosed) {
			serverErrCh <- err
			return
		}
		serverErrCh <- nil
	}()

	select {
	case <-ctx.Done():
		a.log.Info("shutdown signal received")
	case err := <-serverErrCh:
		if err != nil {
			a.log.Error("server failed to start", slog.Any("err", err))
			return fmt.Errorf("server failed to start: %w", err)
		}
	}

	shutdownCtx, cancel := context.WithTimeout(context.Background(), a.cfg.App.ShutdownTimeout)
	defer cancel()

	if err := a.echo.Shutdown(shutdownCtx); err != nil {
		a.log.Error("failed to gracefully shutdown http server", slog.Any("err", err))
		return fmt.Errorf("failed to gracefully shutdown server: %w", err)
	}

	a.log.Info("http server stopped gracefully")
	return nil
}

func (a *App) closeResources() {
	a.log.Info("closing infrastructure connections")

	if a.rabbit != nil {
		if err := a.rabbit.Close(); err != nil {
			a.log.Error("failed to close rabbitmq connection", slog.Any("err", err))
		}
	}

	if a.mongo != nil {
		if err := a.mongo.Close(); err != nil {
			a.log.Error("failed to close mongodb connection", slog.Any("err", err))
		}
	}
}
