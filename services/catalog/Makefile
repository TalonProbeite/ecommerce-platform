
.DEFAULT_GOAL := help

PHP ?= php
COMPOSER ?= composer
DOCKER ?= docker

COMPOSE_FILE := docker-compose.local.yaml
COMPOSE := $(DOCKER) compose -f $(COMPOSE_FILE)
APP := catalog-app
TEST_DATABASE ?= catalog_test
IDE_DB_HOST ?= 127.0.0.1
IDE_DB_PORT ?= 5433

.PHONY: help \
	test test-docker test-unit test-feature test-integration test-db \
	lint format analyse check \
	ide ide-generate ide-models ide-db \
	up down restart build logs ps \
	exec sh artisan migrate seed

help:
	@printf '%s\n' \
		'Tests:' \
		'  test / test-docker Run all tests in Docker against PostgreSQL' \
		'  test-unit         Run unit tests on host (SQLite)' \
		'  test-feature      Run feature/integration tests in Docker' \
		'  test-integration  Alias for test-feature' \
		'  test-db           Start catalog-db and ensure test database exists' \
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
		'  exec CMD="..."    Execute command inside catalog-app' \
		'  sh                Open shell inside catalog-app' \
		'  artisan ARGS="..." Run Artisan inside catalog-app' \
		'  migrate           Apply database migrations' \
		'  seed              Run CatalogSeeder' \
		'' \
		'Other:' \
		'  ide               Generate facade/meta and model IDE helpers' \
		'  ide-generate      Generate only facade/meta IDE helpers' \
		'  ide-models        Generate model metadata using catalog-db' \
		'  ide-db            Start catalog-db for IDE model generation'

# --------------------------------------------------
# Tests
# --------------------------------------------------

test: test-docker

test-unit:
	$(PHP) vendor/bin/phpunit --testsuite Unit

test-feature:
	$(MAKE) test-docker PHPUNIT_ARGS="--testsuite Feature"

test-integration: test-feature

test-docker: test-db
	$(COMPOSE) build $(APP)
	$(COMPOSE) run --rm --no-deps \
		-e APP_ENV=testing \
		-e CATALOG_TEST_DATABASE="$(TEST_DATABASE)" \
		-e DB_CONNECTION=pgsql \
		-e DB_HOST=catalog-db \
		-e DB_PORT=5432 \
		-e DB_DATABASE="$(TEST_DATABASE)" \
		-e DB_URL= \
		-e BROADCAST_CONNECTION=null \
		-e CACHE_STORE=array \
		-e MAIL_MAILER=array \
		-e QUEUE_CONNECTION=sync \
		-e SESSION_DRIVER=array \
		$(APP) php vendor/bin/phpunit --do-not-cache-result $(PHPUNIT_ARGS)

test-db:
	$(COMPOSE) up -d --wait catalog-db
	@$(COMPOSE) exec -T catalog-db sh -c \
		'psql -U "$$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '\''$(TEST_DATABASE)'\''" | grep -q 1 || createdb -U "$$POSTGRES_USER" "$(TEST_DATABASE)"'

lint:
	find app bootstrap config database routes tests \
		-type f -name '*.php' -print0 | xargs -0 -n 1 $(PHP) -l

format:
	$(PHP) vendor/bin/pint --format agent

analyse:
	$(PHP) vendor/bin/phpstan analyse --no-progress

check: lint analyse test

# --------------------------------------------------
# IDE helpers
# --------------------------------------------------

ide: ide-generate ide-models

ide-generate:
	$(COMPOSER) run ide:generate

ide-models: ide-db
	DB_CONNECTION=pgsql DB_HOST=$(IDE_DB_HOST) DB_PORT=$(IDE_DB_PORT) DB_URL= \
		$(PHP) artisan ide-helper:models --write-mixin --no-interaction

ide-db:
	$(COMPOSE) up -d --wait catalog-db

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
	$(COMPOSE) exec $(APP) php artisan db:seed \
		--class=CatalogSeeder --no-interaction
