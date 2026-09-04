package main

import (
	"log"

	"shop/auth/internal/application"
	"shop/auth/internal/config"
)

func main() {
	cfg := config.MustLoad()

	app, err := application.New(cfg)
	if err != nil {
		log.Fatalf("failed to initialize application: %v", err)
	}

	if err := app.Run(); err != nil {
		log.Fatalf("application error: %v", err)
	}
}
