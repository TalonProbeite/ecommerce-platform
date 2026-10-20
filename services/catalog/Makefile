.DEFAULT_GOAL := help

PHP ?= php
COMPOSER ?= composer
export CATALOG_TEST_DATABASE

.PHONY: help test test-unit test-integration lint format analyse check ide ide-models

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
