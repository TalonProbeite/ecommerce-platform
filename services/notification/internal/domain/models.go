package domain

import (
	"shop/notification/internal/infra/mailer"
	"time"
)

type EventLog struct {
	CreatedAt time.Time         `bson:"created_at"`
	Payload   any               `bson:"payload"`
	Type      string            `bson:"type"`
	Status    mailer.SendStatus `bson:"status"`
	Error     string            `bson:"error,omitempty"`
}
