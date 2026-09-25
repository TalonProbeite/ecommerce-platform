package handlers

import (
	"context"
	"errors"
	"fmt"
	"net/http"
	"shop/notification/internal/infra/rabbitmq"
	"time"

	"github.com/labstack/echo/v4"
	"go.mongodb.org/mongo-driver/mongo"
	"go.mongodb.org/mongo-driver/mongo/readpref"
	"golang.org/x/sync/errgroup"
)

type HealthHandler struct {
	mongoClient *mongo.Client
	rabbit      *rabbitmq.RabbitClient
}

func NewHealthHandler(m *mongo.Client, rabbit *rabbitmq.RabbitClient) *HealthHandler {
	return &HealthHandler{
		mongoClient: m,
		rabbit:      rabbit,
	}
}

func (h *HealthHandler) Healthz(c echo.Context) error {
	return c.JSON(http.StatusOK, echo.Map{
		"status": "ok",
	})
}

func (h *HealthHandler) Readyz(c echo.Context) error {
	reqCtx := c.Request().Context()

	timeoutCtx, cancel := context.WithTimeout(reqCtx, 2*time.Second)
	defer cancel()

	g, gCtx := errgroup.WithContext(timeoutCtx)

	g.Go(func() error {
		return h.checkMongo(gCtx)
	})

	g.Go(func() error {
		return h.checkRabbit()
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

func (h *HealthHandler) checkMongo(ctx context.Context) error {
	if h.mongoClient == nil {
		return errors.New("mongodb connection is nil")
	}
	if err := h.mongoClient.Ping(ctx, readpref.Primary()); err != nil {
		return fmt.Errorf("mongodb ping failed: %w", err)
	}
	return nil
}

func (h *HealthHandler) checkRabbit() error {
	if h.rabbit == nil || h.rabbit.Conn == nil || h.rabbit.Conn.IsClosed() {
		return errors.New("rabbitmq connection is closed or nil")
	}
	return nil
}
