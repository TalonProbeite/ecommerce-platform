.DEFAULT_GOAL := help

PHP ?= php
COMPOSER ?= composer
DOCKER ?= docker

COMPOSE_FILE := docker-compose.local.yaml
COMPOSE := $(DOCKER) compose -f $(COMPOSE_FILE)
APP := order-app
TEST_DATABASE ?= order_test

TEST_DB_ENV := -e APP_ENV=testing \
	-e DB_CONNECTION=pgsql \
	-e DB_HOST=order-db \
	-e DB_PORT=5432 \
	-e DB_DATABASE="$(TEST_DATABASE)" \
	-e DB_URL= \
	-e BROADCAST_CONNECTION=null \
	-e MAIL_MAILER=array \
	-e QUEUE_CONNECTION=sync \
	-e SESSION_DRIVER=array

UNIT_TEST_ENV := $(TEST_DB_ENV) -e CACHE_STORE=array

FEATURE_TEST_ENV := $(TEST_DB_ENV) \
	-e CACHE_STORE=redis \
	-e REDIS_CLIENT=phpredis \
	-e REDIS_HOST=order-test-redis \
	-e REDIS_PORT=6379 \
	-e REDIS_PASSWORD=null \
	-e REDIS_DB=2 \
	-e REDIS_CACHE_DB=2

.PHONY: help \
	test test-docker test-unit test-feature test-integration \
	test-services test-db test-redis \
	lint format analyse check \
	up down restart build logs ps \
	exec sh artisan migrate seed

help:
	@printf '%s\n' \
		'Tests:' \
		'  test / test-docker Run unit tests on array and feature tests on Redis in Docker' \
		'  test-unit         Run unit tests on host (SQLite, array cache)' \
		'  test-feature      Run feature/integration tests in Docker against Redis' \
		'  test-integration  Alias for test-feature' \
		'  test-services     Start order-db and order-test-redis' \
		'  test-db           Start order-db and ensure test database exists' \
		'  test-redis        Start order-test-redis and wait for health' \
		'' \
		'Local development tools:' \
		'  lint              Check PHP syntax on host' \
		'  format            Format PHP files with Laravel Pint' \
		'  analyse           Run Larastan / PHPStan' \
		'  check             Run lint, analysis and all tests in Docker' \
		'' \
		'Docker application commands:' \
		'  up                Build and start the local stack' \
		'  down              Stop containers (preserve volumes)' \
		'  restart           Restart containers' \
		'  build             Build application images' \
		'  logs              Follow container logs' \
		'  ps                Show container status' \
		'  exec CMD="..."    Execute command inside order-app' \
		'  sh                Open shell inside order-app' \
		'  artisan ARGS="..." Run Artisan inside order-app' \
		'  migrate           Apply database migrations' \
		'  seed              Run DatabaseSeeder'

# --------------------------------------------------
# Tests
# --------------------------------------------------

test: test-docker

test-unit:
	CACHE_STORE=array $(PHP) vendor/bin/phpunit --testsuite Unit

test-feature: test-services
	$(COMPOSE) build $(APP)
	$(COMPOSE) run --rm --no-deps $(FEATURE_TEST_ENV) \
		$(APP) php vendor/bin/phpunit --testsuite Feature --do-not-cache-result $(PHPUNIT_ARGS)

test-integration: test-feature

test-docker: test-services
	$(COMPOSE) build $(APP)
	$(COMPOSE) run --rm --no-deps $(UNIT_TEST_ENV) \
		$(APP) php vendor/bin/phpunit --testsuite Unit --do-not-cache-result
	$(COMPOSE) run --rm --no-deps $(FEATURE_TEST_ENV) \
		$(APP) php vendor/bin/phpunit --testsuite Feature --do-not-cache-result $(PHPUNIT_ARGS)

test-services: test-db test-redis

test-db:
	$(COMPOSE) up -d --wait order-db
	@$(COMPOSE) exec -T order-db sh -c \
		'psql -U "$$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '\''$(TEST_DATABASE)'\''" | grep -q 1 || createdb -U "$$POSTGRES_USER" "$(TEST_DATABASE)"'

test-redis:
	$(COMPOSE) up -d --wait order-test-redis

lint:
	find app bootstrap config database routes tests \
		-type f -name '*.php' -print0 | xargs -0 -n 1 $(PHP) -l

format:
	$(PHP) vendor/bin/pint --format agent

analyse:
	$(PHP) vendor/bin/phpstan analyse --no-progress

check: lint analyse test

# --------------------------------------------------
# Docker Compose
# --------------------------------------------------

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

build:
	$(COMPOSE) build

logs:
	$(COMPOSE) logs --follow --tail=100

ps:
	$(COMPOSE) ps --all

# --------------------------------------------------
# Application container shortcuts
# --------------------------------------------------

exec:
	$(COMPOSE) exec $(APP) $(if $(CMD),$(CMD),sh)

sh:
	$(COMPOSE) exec $(APP) sh

artisan:
	$(COMPOSE) exec $(APP) php artisan $(ARGS)

# --------------------------------------------------
# Database
# --------------------------------------------------

migrate:
	$(COMPOSE) exec $(APP) php artisan migrate --no-interaction

seed:
	$(COMPOSE) exec $(APP) php artisan db:seed --no-interaction $(ARGS)
