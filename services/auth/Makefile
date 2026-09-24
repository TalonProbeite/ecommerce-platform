COMPOSE_FILE := docker-compose.local.yaml
COMPOSE := docker compose -f $(COMPOSE_FILE)

MIGRATIONS_PATH := ./migrations
DB_URL ?= postgres://postgres:postgres@localhost:5432/auth?sslmode=disable

.PHONY: info up down restart logs ps \
	test test-unit test-integration test-cover test-race \
	lint \
	migrate-up migrate-down migrate-create migrate-version migrate-force

info:
	@echo "Available commands:"
	@echo ""
	@echo "Docker:"
	@echo "  make up                              Start local services"
	@echo "  make up ARGS=\"--force-recreate\"      Start with additional arguments"
	@echo "  make down                            Stop local services"
	@echo "  make restart                         Restart local services"
	@echo "  make logs                            Show service logs"
	@echo "  make ps                              Show running services"
	@echo ""
	@echo "Tests:"
	@echo "  make test                            Run all tests"
	@echo "  make test-unit                       Run unit tests only"
	@echo "  make test-integration                Run integration tests"
	@echo "  make test-cover                      Run tests with coverage"
	@echo "  make test-race                       Run tests with race detector"
	@echo ""
	@echo "Database:"
	@echo "  make migrate-up                      Apply all migrations"
	@echo "  make migrate-down                    Roll back the last migration"
	@echo "  make migrate-create NAME=name        Create a new migration"
	@echo "  make migrate-version                Show current migration version"
	@echo "  make migrate-force VERSION=n         Force migration version"
	@echo ""
	@echo "Code quality:"
	@echo "  make lint                            Run golangci-lint with --fix"

up:
	$(COMPOSE) up --build -d $(ARGS)

down:
	$(COMPOSE) down $(ARGS)

restart:
	$(COMPOSE) down
	$(COMPOSE) up --build -d $(ARGS)

logs:
	$(COMPOSE) logs -f $(ARGS)

ps:
	$(COMPOSE) ps

test:
	go test -v ./...

test-unit:
	go test -v ./internal/...

test-integration:
	RUN_INTEGRATION=1 go test -v ./tests/integration

test-cover:
	RUN_INTEGRATION=1 go test -coverprofile=coverage.out ./...
	go tool cover -func=coverage.out

test-race:
	RUN_INTEGRATION=1 go test -race -v ./...

lint:
	golangci-lint run --fix ./...

migrate-up:
	migrate -path $(MIGRATIONS_PATH) -database "$(DB_URL)" up

migrate-down:
	migrate -path $(MIGRATIONS_PATH) -database "$(DB_URL)" down 1

migrate-create:
	migrate create -ext sql -dir $(MIGRATIONS_PATH) -seq $(NAME)

migrate-version:
	migrate -path $(MIGRATIONS_PATH) -database "$(DB_URL)" version

migrate-force:
	migrate -path $(MIGRATIONS_PATH) -database "$(DB_URL)" force $(VERSION)