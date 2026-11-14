.DEFAULT_GOAL := help

PHP ?= php

.PHONY: help lint format analyse check

help:
	@printf '%s\n' \
		'Code quality:' \
		'  lint              Check PHP syntax on host' \
		'  format            Format PHP files with Laravel Pint' \
		'  analyse           Run Larastan / PHPStan' \
		'  check             Run lint, analysis and tests'

lint:
	find app bootstrap config database routes tests \
		-type f -name '*.php' -print0 | xargs -0 -n 1 $(PHP) -l

format:
	$(PHP) vendor/bin/pint --format agent

analyse:
	$(PHP) vendor/bin/phpstan analyse --no-progress

check: lint analyse test

test:
	$(PHP) vendor/bin/phpunit
