package handler

import (
	"context"
	"errors"
	"fmt"
	"net/http"
	"time"

	"shop/shared/rabbitmq"

	"github.com/labstack/echo/v4"
	"github.com/meilisearch/meilisearch-go"
	"github.com/redis/go-redis/v9"
	"golang.org/x/sync/errgroup"
)

type ReadyzHandler struct {
	rdb    *redis.Client
	rabbit *rabbitmq.RabbitClient
	meili  meilisearch.ServiceManager
}

func NewReadyzHandler(rdb *redis.Client, rabbit *rabbitmq.RabbitClient, meili meilisearch.ServiceManager) *ReadyzHandler {
	return &ReadyzHandler{
		rdb:    rdb,
		rabbit: rabbit,
		meili:  meili,
	}
}

func (r *ReadyzHandler) Check(c echo.Context) error {
	reqCtx := c.Request().Context()

	timeoutCtx, cancel := context.WithTimeout(reqCtx, 2*time.Second)
	defer cancel()

	g, gCtx := errgroup.WithContext(timeoutCtx)

	g.Go(func() error {
		return r.checkRedis(gCtx)
	})

	g.Go(func() error {
		return r.checkRabbit()
	})

	g.Go(func() error {
		return r.checkMeili(gCtx)
	})

	if err := g.Wait(); err != nil {
		return c.JSON(http.StatusServiceUnavailable, echo.Map{
			"status": "unavailable",
			"error":  err.Error(),
		})
	}

	return c.JSON(http.StatusOK, echo.Map{
		"status": "ok",
	})
}

func (r *ReadyzHandler) checkRedis(ctx context.Context) error {
	if r.rdb == nil {
		return errors.New("redis connection is nil")
	}
	if err := r.rdb.Ping(ctx).Err(); err != nil {
		return fmt.Errorf("redis ping failed: %w", err)
	}
	return nil
}

func (r *ReadyzHandler) checkRabbit() error {
	if r.rabbit == nil || r.rabbit.Conn == nil || r.rabbit.Conn.IsClosed() {
		return errors.New("rabbitmq connection is closed or nil")
	}
	return nil
}

func (r *ReadyzHandler) checkMeili(ctx context.Context) error {
	if _, err := r.meili.Health(ctx); err != nil {
		return fmt.Errorf("Error connecting to meilisearch: %w", err)
	}
	return nil
}
