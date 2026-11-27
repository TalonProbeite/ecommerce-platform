package app

import (
	"fmt"
	"log/slog"

	"shop/search/internal/config"
	"shop/shared/events"
	"shop/shared/logger"
	"shop/shared/rabbitmq"
	"shop/shared/redisx"

	"github.com/labstack/echo/v4"
	"github.com/meilisearch/meilisearch-go"
	"github.com/redis/go-redis/v9"
)

type App struct {
	cfg    *config.Config
	log    *slog.Logger
	echo   *echo.Echo
	meili  *meilisearch.ServiceManager
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
}

func initRabbitMQ(rabbitURL string, log *slog.Logger) (*rabbitmq.RabbitClient, *rabbitmq.EventPublisher, error) {
	rabbit, err := rabbitmq.NewRabbitClient(rabbitURL)
	if err != nil {
		return nil, nil, fmt.Errorf("failed to connect to rabbitmq: %w", err)
	}

	closeRabbit := func() {
		if closeErr := rabbit.Close(); closeErr != nil {
			log.Error("failed to close rabbitmq", slog.Any("err", closeErr))
		}
	}

	if err := declareSearchTopology(rabbit); err != nil {
		closeRabbit()
		return nil, nil, fmt.Errorf("failed to init rabbitmq topology: %w", err)
	}

	eventPublisher, err := rabbitmq.NewEventPublisher(rabbit)
	if err != nil {
		closeRabbit()
		return nil, nil, fmt.Errorf("failed to init event publisher: %w", err)
	}

	return rabbit, eventPublisher, nil
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
