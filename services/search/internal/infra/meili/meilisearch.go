package meili

import (
	"context"
	"fmt"

	"github.com/meilisearch/meilisearch-go"
)

func NewMeliClient(url, apiKey string, ctx context.Context) (meilisearch.ServiceManager, error) {
	client := meilisearch.New(url, meilisearch.WithAPIKey(apiKey))

	_, err := client.Health(ctx)
	if err != nil {
		return nil, fmt.Errorf("Error connecting to meilisearch: %w", err)
	}

	return client, nil
}
