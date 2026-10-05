COMPOSE_FILE := docker-compose.local.yaml
COMPOSE := docker compose -f $(COMPOSE_FILE)

.PHONY: info up down restart logs ps \
	test test-unit test-integration test-cover test-race \
	lint

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
	go test -race -v -count=1 ./internal/services/...

test-integration:
	go test -tags=integration -v -count=1 ./tests/integration/...

test-cover:
	go test -v -coverprofile=coverage.out ./...
	go tool cover -html=coverage.out

test-race:
	go test -race -v ./...

lint:
	golangci-lint run --fix