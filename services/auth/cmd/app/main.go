package main

import (
	"log"
	"shop/auth/internal/app"
	"shop/auth/internal/config"
)

func main() {
	cfg := config.MustLoad()

	app, err := app.New(cfg)
	if err != nil {
		log.Fatalf("failed to initialize application: %v", err)
	}

	if err := app.Run(); err != nil {
		log.Fatalf("application error: %v", err)
	}
}
