package application

import "shop/auth/internal/domain"

type ProfileService struct {
	userRepo  domain.ProfileUserRepository
	sessRepo  domain.SessionRepository
	publisher domain.EventPublisher
}

func NewProfileService(
	us domain.ProfileUserRepository,
	sr domain.SessionRepository,
	ep domain.EventPublisher,
) *ProfileService {
	return &ProfileService{
		userRepo: us, sessRepo: sr, publisher: ep,
	}
}
