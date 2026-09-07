package app

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"github.com/jmoiron/sqlx"
	"github.com/labstack/echo/v4"
	"github.com/redis/go-redis/v9"

	"shop/auth/internal/config"
	"shop/auth/internal/infra/db"
	"shop/auth/internal/infra/logger"
	"shop/auth/internal/infra/rabbitmq"
	transporthttp "shop/auth/internal/transport/http"
	"shop/auth/internal/transport/http/handler"
	"shop/auth/internal/transport/http/middleware"
	"shop/auth/internal/infra/crypto"
	"shop/auth/internal/infra/repository"
	"shop/auth/internal/application"
)

type App struct {
	cfg    *config.Config
	log    *slog.Logger
	echo   *echo.Echo
	pg     *sqlx.DB
	rdb    *redis.Client
	rabbit *rabbitmq.RabbitClient
}

func New(cfg *config.Config) (*App, error) {
	log := logger.Init(cfg.App.Env)

	pg, err := db.NewPostgresDB(cfg.Postgres.DatabaseURL)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to postgres: %w", err)
	}

	if cfg.App.AutoMigrate {
		if err := db.RunMigrations(cfg.Postgres.DatabaseURL); err != nil {
			_ = pg.Close()
			return nil, fmt.Errorf("failed to run migrations: %w", err)
		}
	}

	rdb, err := db.NewRedisClient(cfg.Redis.RedisURL)
	if err != nil {
		_ = pg.Close()
		return nil, fmt.Errorf("failed to connect to redis: %w", err)
	}

	rabbit, err := rabbitmq.NewRabbitClient(cfg.Rabbit.RabbitURL)
	if err != nil {
		_ = pg.Close()
		_ = rdb.Close()
		return nil, fmt.Errorf("failed to connect to rabbitmq: %w", err)
	}

	tokenMeneger := crypto.NewJWTManager(cfg.RSAPrivateKey())
	sessionRepo := repository.NewSessionRepo(rdb)
	userRepo := repository.NewUserRepo(pg)
	eventPublisher, err := rabbitmq.NewEventPublisher(rabbit, cfg.Rabbit.ExchangeName, cfg.Rabbit.ExchangeType)
	if err != nil {
		_ = pg.Close()
		_ = rdb.Close()
		_ = rabbit.Close()
		return nil, fmt.Errorf("failed to init event publisher: %w", err)
	}
	authService := application.NewAuthService(userRepo, sessionRepo, eventPublisher, tokenMeneger)

	handlers := transporthttp.Handlers{
		HealthHandler: handler.NewHealthHandler(),
		ReadyzHandler: handler.NewReadyzHandler(pg, rdb, rabbit),
		AuthHandler: handler.NewAuthHandler(authService,log),
	}
	middlewares := transporthttp.Middlewares{
		AuthCheck: middleware.AuthCheck(*tokenMeneger,*sessionRepo),
	}

	router := transporthttp.NewRouter(cfg, log, handlers, middlewares)

	return &App{
		cfg:    cfg,
		log:    log,
		echo:   router,
		pg:     pg,
		rdb:    rdb,
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

	if a.rdb != nil {
		if err := a.rdb.Close(); err != nil {
			a.log.Error("failed to close redis connection", slog.Any("err", err))
		}
	}

	if a.pg != nil {
		if err := a.pg.Close(); err != nil {
			a.log.Error("failed to close postgres connection", slog.Any("err", err))
		}
	}
}
