package repository

import (
	"context"
	"errors"

	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mongodb"

	"go.mongodb.org/mongo-driver/bson"
	"go.mongodb.org/mongo-driver/bson/primitive"
	"go.mongodb.org/mongo-driver/mongo"
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

func (er *EventRepo) GetByID(ctx context.Context, id string) (domain.EventLog, error) {
	objID, err := primitive.ObjectIDFromHex(id)
	if err != nil {
		return domain.EventLog{}, domain.ErrInvalidFilters
	}

	var log domain.EventLog
	collection := er.client.DB.Collection("events")

	err = collection.FindOne(ctx, bson.M{"_id": objID}).Decode(&log)
	if err != nil {
		if errors.Is(err, mongo.ErrNoDocuments) {
			return domain.EventLog{}, domain.ErrNotificationNotFound
		}
		return domain.EventLog{}, err
	}

	return log, nil
}

func (er *EventRepo) GetHistory(ctx context.Context, f *domain.HistoryFilters) (logs []domain.EventLog, err error) {
	filter := bson.M{}

	if f.Status != "" {
		filter["status"] = f.Status
	}
	if f.Channel != "" {
		filter["channel"] = f.Channel
	}
	if f.Recipient != "" {
		filter["recipient"] = f.Recipient
	}

	dateFilter := bson.M{}
	if !f.StartDate.IsZero() {
		dateFilter["$gte"] = f.StartDate
	}
	if !f.EndDate.IsZero() {
		dateFilter["$lte"] = f.EndDate
	}
	if len(dateFilter) > 0 {
		filter["created_at"] = dateFilter
	}

	collection := er.client.DB.Collection("events")

	cursor, err := collection.Find(ctx, filter)
	if err != nil {
		return nil, err
	}
	defer func() {
		if closeErr := cursor.Close(ctx); closeErr != nil {
			err = errors.Join(err, closeErr)
		}
	}()

	if err = cursor.All(ctx, &logs); err != nil {
		return nil, err
	}

	if logs == nil {
		return []domain.EventLog{}, nil
	}

	return logs, nil
}
