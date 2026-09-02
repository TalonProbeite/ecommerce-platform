DB_URL ?= postgres://postgres:postgres@localhost:5432/shop_auth?sslmode=disable

.PHONY: migrate-up migrate-down migrate-new

migrate-up:
	migrate -path migrations -database "$(DB_URL)" up

migrate-down:
	migrate -path migrations -database "$(DB_URL)" down 1

migrate-new:
	migrate create -ext sql -dir migrations -seq $(name)
