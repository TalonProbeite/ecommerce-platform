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
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/logger"
	"shop/notification/internal/infra/mongodb"
	"shop/notification/internal/infra/rabbitmq"
	"shop/notification/internal/services"
	"shop/notification/internal/transport/http/handlers"
	"syscall"

	"github.com/labstack/echo/v4"

	transporthttp "shop/notification/internal/transport/http"
)

type App struct {
	cfg      *config.Config
	log      *slog.Logger
	echo     *echo.Echo
	mongo    *mongodb.MongoClient
	rabbit   *rabbitmq.RabbitClient
	consumer *rabbitmq.Consumer
	router   *services.EventRouter
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

	consumer, err := rabbitmq.NewConsumer(rabbit)
	if err != nil {
		if closeErr := rabbit.Close(); closeErr != nil {
			log.Error("failed to close rabbitmq", slog.Any("err", closeErr))
		}
		if closeErr := mongoClient.Close(); closeErr != nil {
			log.Error("failed to close mongodb", slog.Any("err", closeErr))
		}
		return nil, fmt.Errorf("failed to init rabbitmq consumer: %w", err)
	}

	eventRouter := services.NewEventRouter(consumer.ConsumerList, eventHandlers(), log)

	h := transporthttp.Handlers{
		HealthHandler: handlers.NewHealthHandler(mongoClient.Client, rabbit),
	}

	router := transporthttp.NewRouter(cfg, log, h)

	return &App{
		cfg:      cfg,
		log:      log,
		echo:     router,
		mongo:    mongoClient,
		rabbit:   rabbit,
		consumer: consumer,
		router:   eventRouter,
	}, nil
}

func eventHandlers() map[string]services.HandlerFunc {
	noop := func(ctx context.Context, body []byte) error { return nil }

	return map[string]services.HandlerFunc{
		domain.UserRegisteredEventKey:    noop,
		domain.UserEmailVerifiedEventKey: noop,
		domain.OrderPaidEventKey:         noop,
		domain.OrderConfirmedEventKey:    noop,
		domain.OrderCancelledEventKey:    noop,
	}
}

func (a *App) Run() error {
	defer a.closeResources()

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	routerCtx, cancelRouter := context.WithCancel(ctx)
	defer cancelRouter()

	serverErrCh := make(chan error, 1)
	routerErrCh := make(chan error, 1)

	go func() {
		a.log.Info("starting http server", slog.String("port", a.cfg.App.Port))
		if err := a.echo.Start(":" + a.cfg.App.Port); err != nil && !errors.Is(err, http.ErrServerClosed) {
			serverErrCh <- err
			return
		}
		serverErrCh <- nil
	}()

	go func() {
		a.log.Info("starting event router")
		routerErrCh <- a.router.Run(routerCtx)
	}()

	var runErr error
	routerStopped := false

	select {
	case <-ctx.Done():
		a.log.Info("shutdown signal received")
	case err := <-serverErrCh:
		if err != nil {
			a.log.Error("server failed to start", slog.Any("err", err))
			runErr = fmt.Errorf("server failed to start: %w", err)
		}
	case err := <-routerErrCh:
		routerStopped = true
		if err != nil {
			a.log.Error("event router stopped", slog.Any("err", err))
			runErr = fmt.Errorf("event router stopped: %w", err)
		}
	}

	cancelRouter()

	if !routerStopped {
		if err := <-routerErrCh; err != nil {
			a.log.Error("event router stopped with error", slog.Any("err", err))
		}
	}

	a.log.Info("event router stopped")

	shutdownCtx, cancel := context.WithTimeout(context.Background(), a.cfg.App.ShutdownTimeout)
	defer cancel()

	if err := a.echo.Shutdown(shutdownCtx); err != nil {
		a.log.Error("failed to gracefully shutdown http server", slog.Any("err", err))
		return fmt.Errorf("failed to gracefully shutdown server: %w", err)
	}

	a.log.Info("http server stopped gracefully")
	return runErr
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
