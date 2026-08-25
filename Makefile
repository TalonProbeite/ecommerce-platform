.PHONY: local-up local-down prod-up prod-down

local-up:
	docker compose -f docker-compose.local.yaml up -d --build

local-down:
	docker compose -f docker-compose.local.yaml down

prod-up:
	docker compose -f docker-compose.prod.yaml up -d --build

prod-down:
	docker compose -f docker-compose.prod.yaml down