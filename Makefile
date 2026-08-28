.PHONY: prod-up prod-down prod-stop

prod-up:
	docker compose up --build - d

prod-down:
	docker compose down $(ARGS)

prod-stop:
	docker compose stop $(ARGS)