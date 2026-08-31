.PHONY: prod-up prod-down prod-stop

prod-up:
	docker compose up -d --build $(ARGS)

prod-down:
	docker compose down $(ARGS)

prod-stop:
	docker compose stop $(ARGS)