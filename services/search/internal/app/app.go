package app

import (
	"context"
	"fmt"
	"log/slog"
	"time"

	"shop/search/internal/config"
	"shop/search/internal/infra/meili"
	"shop/shared/events"
	"shop/shared/jwt"
	"shop/shared/logger"
	"shop/shared/middleware"
	"shop/shared/rabbitmq"
	"shop/shared/redisx"

	"github.com/labstack/echo/v4"
	"github.com/meilisearch/meilisearch-go"
	"github.com/redis/go-redis/v9"

	transporthttp "shop/search/internal/transport/http"
	"shop/search/internal/transport/http/handler"
)

type App struct {
	cfg    *config.Config
	log    *slog.Logger
	echo   *echo.Echo
	meili  meilisearch.ServiceManager
	rdb    *redis.Client
	rabbit *rabbitmq.RabbitClient
}

const searchCatalogQueue = "search.catalog-events"

var searchCatalogRoutingKeys = []string{
	events.ProductCreatedEventKey,
	events.ProductUpdatedEventKey,
	events.ProductDeletedEventKey,
	events.CategoryCreatedEventKey,
	events.CategoryUpdatedEventKey,
	events.CategoryDeletedEventKey,
	events.StockChangedEventKey,
}

func New(cfg *config.Config) (*App, error) {
	log := logger.Init(cfg.App.Env)

	rdb, err := redisx.NewRedisClient(cfg.Redis.RedisURL)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to redis: %w", err)
	}

	rabbit, _, _, err := initRabbitMQ(cfg.Rabbit.RabbitURL, log)
	if err != nil {
		return nil, fmt.Errorf("error initializing rabbit: %w", err)
	}

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	meili, err := meili.NewMeliClient(cfg.Meli.MeiliUrl, cfg.Meli.APIKey, ctx)
	if err != nil {
		return nil, fmt.Errorf("meilisearch client initialization error: %w", err)
	}

	jwtManager := jwt.NewJWTManager(cfg.RSAPublicKey())

	h := transporthttp.Handlers{
		HealthHandler: handler.NewHealthHandler(),
		ReadyzHandler: handler.NewReadyzHandler(rdb, rabbit, meili),
	}

	m := transporthttp.Middlewares{
		AuthCheck: middleware.AuthCheck(jwtManager),
	}

	e := transporthttp.NewRouter(cfg, log, h, m)

	a := App{
		cfg:    cfg,
		log:    log,
		meili:  meili,
		rdb:    rdb,
		rabbit: rabbit,
		echo:   e,
	}

	return &a, nil
}

func initRabbitMQ(rabbitURL string, log *slog.Logger) (*rabbitmq.RabbitClient, *rabbitmq.EventPublisher, *rabbitmq.Consumer, error) {
	rabbit, err := rabbitmq.NewRabbitClient(rabbitURL)
	if err != nil {
		return nil, nil, nil, fmt.Errorf("failed to connect to rabbitmq: %w", err)
	}

	closeRabbit := func() {
		if closeErr := rabbit.Close(); closeErr != nil {
			log.Error("failed to close rabbitmq", slog.Any("err", closeErr))
		}
	}

	if err := declareSearchTopology(rabbit); err != nil {
		closeRabbit()
		return nil, nil, nil, fmt.Errorf("failed to init rabbitmq topology: %w", err)
	}

	eventPublisher, err := rabbitmq.NewEventPublisher(rabbit)
	if err != nil {
		closeRabbit()
		return nil, nil, nil, fmt.Errorf("failed to init event publisher: %w", err)
	}

	catalogConsumer, err := rabbitmq.NewConsumer(rabbit, []string{searchCatalogQueue})
	if err != nil {
		closeRabbit()
		return nil, nil, nil, fmt.Errorf("failed to init catalog consumer: %w", err)
	}

	return rabbit, eventPublisher, catalogConsumer, nil
}

func declareSearchTopology(rabbit *rabbitmq.RabbitClient) error {
	if err := rabbitmq.DeclareExchange(rabbit, events.ExchangeCatalog, events.ExchangeKindTopic); err != nil {
		return err
	}
	if err := rabbitmq.DeclareExchange(rabbit, events.ExchangeSearch, events.ExchangeKindTopic); err != nil {
		return err
	}

	if _, err := rabbit.Chan.QueueDeclare(searchCatalogQueue, true, false, false, false, nil); err != nil {
		return fmt.Errorf("failed to declare queue %q: %w", searchCatalogQueue, err)
	}

	for _, key := range searchCatalogRoutingKeys {
		if err := rabbit.Chan.QueueBind(searchCatalogQueue, key, events.ExchangeCatalog, false, nil); err != nil {
			return fmt.Errorf(
				"failed to bind queue %q to exchange %q with routing key %q: %w",
				searchCatalogQueue,
				events.ExchangeCatalog,
				key,
				err,
			)
		}
	}

	return nil
}
