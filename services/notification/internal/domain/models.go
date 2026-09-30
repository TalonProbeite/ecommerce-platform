package domain

import "time"

type EventLog struct {
	Type      string    `bson:"type"`
	Status    string    `bson:"status"`
	Payload   any       `bson:"payload"`
	Error     string    `bson:"error,omitempty"`
	CreatedAt time.Time `bson:"created_at"`
}


