package domain

import (
	"shop/notification/internal/infra/mailer"
	"time"

	"go.mongodb.org/mongo-driver/bson/primitive"
)

type EventLog struct {
	CreatedAt time.Time          `bson:"created_at" json:"created_at"`
	Payload   any                `bson:"payload" json:"payload"`
	Type      string             `bson:"type" json:"type"`
	Channel   string             `bson:"channel" json:"channel"`
	Recipient string             `bson:"recipient" json:"recipient"`
	Status    mailer.SendStatus  `bson:"status" json:"status"`
	Error     string             `bson:"error,omitempty" json:"error,omitempty"`
	Attempts  int                `bson:"attempts" json:"attempts"`
	ID        primitive.ObjectID `bson:"_id,omitempty" json:"id"`
}
type HistoryFilters struct {
	StartDate time.Time
	EndDate   time.Time
	Status    string
	Channel   string
	Recipient string
}
