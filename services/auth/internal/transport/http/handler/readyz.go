package handler

import (
	"context"
	"errors"
	"fmt"
	"net/http"
	"shop/auth/internal/infra/rabbitmq"
	"time"

	"github.com/jmoiron/sqlx"
	"github.com/labstack/echo/v4"
	"github.com/redis/go-redis/v9"
	"golang.org/x/sync/errgroup"
)

type ReadyzHandler struct {
	pg     *sqlx.DB
	rdb    *redis.Client
	rabbit *rabbitmq.RabbitClient
}

func NewReadyzHandler(pg *sqlx.DB, rdb *redis.Client, rabbit *rabbitmq.RabbitClient) *ReadyzHandler {
	return &ReadyzHandler{
		pg:     pg,
		rdb:    rdb,
		rabbit: rabbit,
	}
}

func (r *ReadyzHandler) Check(c echo.Context) error {
	reqCtx := c.Request().Context()

	timeoutCtx, cancel := context.WithTimeout(reqCtx, 2*time.Second)
	defer cancel()

	g, gCtx := errgroup.WithContext(timeoutCtx)

	g.Go(func() error {
		return r.checkPostgres(gCtx)
	})

	g.Go(func() error {
		return r.checkRedis(gCtx)
	})

	g.Go(func() error {
		return r.checkRabbit()
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

func (r *ReadyzHandler) checkPostgres(ctx context.Context) error {
	if r.pg == nil {
		return errors.New("postgres connection is nil")
	}
	if err := r.pg.PingContext(ctx); err != nil {
		return fmt.Errorf("postgres ping failed: %w", err)
	}
	return nil
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
