.PHONY: prod-up prod-down prod-stop seed seed-catalog seed-order seed-auth seed-notification stripe-listen

STRIPE_WEBHOOK_URL ?= https://localhost/order/webhooks/stripe
STRIPE_EVENTS ?= payment_intent.succeeded,payment_intent.payment_failed,payment_intent.canceled

prod-up:
	docker compose up -d --build $(ARGS)

stripe-listen:
	@command -v stripe >/dev/null 2>&1 || { echo 'Stripe CLI is not installed: https://docs.stripe.com/stripe-cli'; exit 1; }
	stripe listen --forward-to $(STRIPE_WEBHOOK_URL) --skip-verify --events $(STRIPE_EVENTS)

prod-down:
	docker compose down $(ARGS)

prod-stop:
	docker compose stop $(ARGS)

# Explicit DEMO seeds. Every service targets the global Compose stack.
seed: seed-auth seed-notification seed-catalog seed-order

seed-catalog:
	$(MAKE) -C services/catalog seed COMPOSE_FILE="$(CURDIR)/docker-compose.yaml"

seed-order:
	$(MAKE) -C services/order seed COMPOSE_FILE="$(CURDIR)/docker-compose.yaml"

seed-auth:
	$(MAKE) -C services/auth seed COMPOSE_FILE="$(CURDIR)/docker-compose.yaml"

seed-notification:
	$(MAKE) -C services/notification seed COMPOSE_FILE="$(CURDIR)/docker-compose.yaml"
