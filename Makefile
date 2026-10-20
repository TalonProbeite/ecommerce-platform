.PHONY: prod-up prod-down prod-stop seed seed-catalog

prod-up:
	docker compose up -d --build $(ARGS)

prod-down:
	docker compose down $(ARGS)

prod-stop:
	docker compose stop $(ARGS)

# Catalog is the only service with a DEMO seeder; Laravel uses its configured database.
seed: seed-catalog

seed-catalog:
	$(MAKE) -C services/catalog seed
