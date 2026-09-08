// Package logger provides structured logging initialization using slog.
package logger

import (
	"log/slog"
	"os"
)

// Init constructs and sets up a new slog.Logger instance based on the environment.
func Init(env string) *slog.Logger {
	var handler slog.Handler

	if env == "prod" || env == "production" {
		handler = slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
			Level: slog.LevelInfo,
		})
	} else {
		handler = slog.NewTextHandler(os.Stdout, &slog.HandlerOptions{
			Level: slog.LevelDebug,
		})
	}

	logger := slog.New(handler)
	slog.SetDefault(logger)

	return logger
}
