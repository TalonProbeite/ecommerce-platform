package repository

import (
	"context"
	"shop/notification/internal/infra/mongodb"
)

type EventRepo struct {
	client *mongodb.MongoClient
}

func NewEventRepo(client *mongodb.MongoClient) *EventRepo {
	return &EventRepo{
		client: client,
	}
}

func (er *EventRepo) SaveEvent(ctx context.Context, event any) error {
	collection := er.client.DB.Collection("events")

	_, err := collection.InsertOne(ctx, event)
	if err != nil {
		return err
	}

	return nil
}
