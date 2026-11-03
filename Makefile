.PHONY: prod-up prod-down prod-stop seed seed-catalog seed-auth seed-notification

prod-up:
	docker compose up -d --build $(ARGS)

prod-down:
	docker compose down $(ARGS)

prod-stop:
	docker compose stop $(ARGS)

# Explicit DEMO seeds. Auth and notification target the global Compose stack.
seed: seed-auth seed-notification seed-catalog

seed-catalog:
	$(MAKE) -C services/catalog seed

seed-auth:
	$(MAKE) -C services/auth seed COMPOSE_FILE="$(CURDIR)/docker-compose.yaml"

seed-notification:
	$(MAKE) -C services/notification seed COMPOSE_FILE="$(CURDIR)/docker-compose.yaml"
