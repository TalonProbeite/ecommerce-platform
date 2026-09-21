package handler

import (
	"log/slog"

	"shop/auth/internal/application"
)

type ProfileHandler struct {
	profileService *application.ProfileService
	log            *slog.Logger
}

func NewProfileHandlers(profileService *application.ProfileService, log *slog.Logger) *ProfileHandler {
	return &ProfileHandler{
		profileService: profileService,
		log:            log,
	}
}
