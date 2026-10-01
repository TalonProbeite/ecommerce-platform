package domain

import (
	"time"

	"shop/notification/internal/infra/mailer"

	"go.mongodb.org/mongo-driver/bson/primitive"
)

type EventLog struct {
	ID        primitive.ObjectID `bson:"_id,omitempty" json:"id"`
	Type      string             `bson:"type" json:"type"`
	Channel   string             `bson:"channel" json:"channel"`
	Recipient string             `bson:"recipient" json:"recipient"`
	Status    mailer.SendStatus  `bson:"status" json:"status"`
	Attempts  int                `bson:"attempts" json:"attempts"`
	Payload   any                `bson:"payload" json:"payload"`
	Error     string             `bson:"error,omitempty" json:"error,omitempty"`
	CreatedAt time.Time          `bson:"created_at" json:"created_at"`
}
