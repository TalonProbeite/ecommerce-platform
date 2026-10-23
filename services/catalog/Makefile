.DEFAULT_GOAL := help

PHP ?= php
COMPOSER ?= composer
DOCKER ?= docker
LOCAL_COMPOSE = $(DOCKER) compose -f docker-compose.local.yaml
export CATALOG_TEST_DATABASE

.PHONY: help test test-unit test-integration lint format analyse check ide ide-models up down restart logs ps serve migrate seed

help:
	@printf '%s\n' \
	  'test / test-unit  Run unit tests without infrastructure' \
	  'test-integration  Run PostgreSQL repository and schema tests (requires CATALOG_TEST_DATABASE)' \
	  'lint              Check PHP syntax' \
	  'format            Format PHP files with Laravel Pint' \
	  'analyse           Run Larastan / PHPStan using phpstan.neon' \
	  'check             Run syntax checks, static analysis and unit tests' \
	  'ide               Generate Laravel IDE Helper and PhpStorm metadata' \
	  'ide-models        Generate model mixins (requires database access)' \
	  'migrate           Apply pending Catalog migrations without resetting data' \
	  'seed              Seed Catalog DEMO data offline (requires migrated database)' \
	  '' \
	  'Isolated Catalog stack (docker-compose.local.yaml):' \
	  'up                Start PostgreSQL, Redis and SeaweedFS for host Laravel' \
	  'down              Remove local containers and network; keep data volumes' \
	  'restart           Restart existing local containers' \
	  'logs              Follow local stack logs (last 100 lines per service)' \
	  'ps                Show local container status' \
	  'serve             Run Laravel on the host at http://127.0.0.1:8000' \
	  '' \
	  'PostgreSQL example (dedicated test database must already exist):' \
	  '  CATALOG_TEST_DATABASE=catalog_test DB_HOST=127.0.0.1 DB_USERNAME=catalog_test make test-integration' \
	  '  Supply DB_PORT and DB_PASSWORD through the environment as needed.' \
	  '  Integration tests apply migrations in isolated schemas and remove those schemas afterward.'

test: test-unit

test-unit:
	$(PHP) vendor/bin/phpunit --testsuite Unit

test-integration:
	@test -n "$$CATALOG_TEST_DATABASE" || { printf '%s\n' 'Set CATALOG_TEST_DATABASE to a dedicated PostgreSQL test database (name must contain test/testing as a separate underscore-delimited part).'; exit 1; }
	APP_ENV=testing DB_URL='' DB_CONNECTION=pgsql DB_DATABASE="$$CATALOG_TEST_DATABASE" $(PHP) vendor/bin/phpunit --testsuite Feature --filter='CatalogRepositoriesTest|CatalogSchemaTest'

lint:
	find app bootstrap config database routes tests -type f -name '*.php' -print0 | xargs -0 -n 1 $(PHP) -l

format:
	$(PHP) vendor/bin/pint --format agent

analyse:
	$(PHP) vendor/bin/phpstan analyse --no-progress

check: lint analyse test-unit

ide:
	$(COMPOSER) run ide:generate

ide-models:
	$(COMPOSER) run ide:models

up:
	$(LOCAL_COMPOSE) up -d --wait --build

down:
	$(LOCAL_COMPOSE) down

restart:
	$(LOCAL_COMPOSE) restart

logs:
	$(LOCAL_COMPOSE) logs --follow --tail=100

ps:
	$(LOCAL_COMPOSE) ps --all

serve:
	$(PHP) artisan serve --host=127.0.0.1 --port=8000

migrate:
	$(PHP) artisan migrate --no-interaction

seed:
	$(PHP) artisan db:seed --class=CatalogSeeder --no-interaction
