package repository

import (
	"context"
	"errors"
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mongodb"
	"time"

	"go.mongodb.org/mongo-driver/bson"
	"go.mongodb.org/mongo-driver/mongo"
	"go.mongodb.org/mongo-driver/mongo/options"
)

const contactCollection = "user_contacts"

type ContactRepo struct {
	client *mongodb.MongoClient
}

func NewContactRepo(client *mongodb.MongoClient) *ContactRepo {
	return &ContactRepo{client: client}
}

func (r *ContactRepo) SaveContact(ctx context.Context, userID, email string) error {
	collection := r.client.DB.Collection(contactCollection)

	_, err := collection.UpdateOne(
		ctx,
		bson.M{"_id": userID},
		bson.M{"$set": bson.M{
			"email":      email,
			"updated_at": time.Now().UTC(),
		}},
		options.Update().SetUpsert(true),
	)

	return err
}

func (r *ContactRepo) GetEmail(ctx context.Context, userID string) (string, error) {
	collection := r.client.DB.Collection(contactCollection)

	var contact struct {
		Email string `bson:"email"`
	}

	err := collection.FindOne(ctx, bson.M{"_id": userID}).Decode(&contact)
	if err != nil {
		if errors.Is(err, mongo.ErrNoDocuments) {
			return "", domain.ErrContactNotFound
		}
		return "", err
	}

	if contact.Email == "" {
		return "", domain.ErrContactNotFound
	}

	return contact.Email, nil
}
