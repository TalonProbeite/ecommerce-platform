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
	"shop/notification/internal/infra/crypto"
	"shop/notification/internal/infra/logger"
	"shop/notification/internal/infra/mailer"
	"shop/notification/internal/infra/mongodb"
	"shop/notification/internal/infra/rabbitmq"
	"shop/notification/internal/infra/repository"
	"shop/notification/internal/infra/template"
	"shop/notification/internal/services"
	"shop/notification/internal/transport/http/handlers"
	"shop/notification/internal/transport/http/middleware"
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

	a := &App{cfg: cfg, log: log}

	mongoClient, err := mongodb.NewMongoClient(cfg.Mongo.URI, cfg.Mongo.DatabaseName)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to mongodb: %w", err)
	}
	a.mongo = mongoClient

	rabbit, err := rabbitmq.NewRabbitClient(cfg.Rabbit.RabbitURL)
	if err != nil {
		a.closeResources()
		return nil, fmt.Errorf("failed to connect to rabbitmq: %w", err)
	}
	a.rabbit = rabbit

	sender, err := mailer.NewSender(&cfg.SMTP)
	if err != nil {
		a.closeResources()
		return nil, fmt.Errorf("failed to init mailer: %w", err)
	}

	renderer, err := template.NewMessageBuilder(cfg.App.Env)
	if err != nil {
		a.closeResources()
		return nil, fmt.Errorf("failed to init template builder: %w", err)
	}

	eventRepo := repository.NewEventRepo(mongoClient)

	userHandler := services.NewUserEventHandler(renderer, sender, eventRepo)
	orderHandler := services.NewOrderEventHandler(renderer, sender, eventRepo)

	consumer, err := rabbitmq.NewConsumer(rabbit)
	if err != nil {
		a.closeResources()
		return nil, fmt.Errorf("failed to init rabbitmq consumer: %w", err)
	}
	a.consumer = consumer

	a.router = services.NewEventRouter(
		consumer.ConsumerList,
		eventHandlers(userHandler, orderHandler),
		log,
	)

	jwtManager := crypto.NewJWTManager(cfg.RSAPublicKey())

	h := transporthttp.Handlers{
		HealthHandler: handlers.NewHealthHandler(mongoClient.Client, rabbit),
	}

	a.echo = transporthttp.NewRouter(cfg, log, h)
	
	a.echo.Use(middleware.AuthCheck(jwtManager))
	a.echo.Use(middleware.AdminOnly)

	return a, nil
}

func eventHandlers(
	user *services.UserEventHandler,
	order *services.OrderEventHandler,
) map[string]services.HandlerFunc {
	return map[string]services.HandlerFunc{
		domain.UserRegisteredEventKey:    user.HandleUserRegistered,
		domain.UserEmailVerifiedEventKey: user.HandleEmailVerified,
		domain.OrderPaidEventKey:         order.HandleOrderPaid,
		domain.OrderConfirmedEventKey:    order.HandleOrderConfirmed,
		domain.OrderCancelledEventKey:    order.HandleOrderCancelled,
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
