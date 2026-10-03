//go:build integration

package integration

import (
	"shop/notification/internal/domain"
	"shop/notification/internal/infra/mailer"
	"slices"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
	"go.mongodb.org/mongo-driver/bson/primitive"
)

func recipientsOf(logs []domain.EventLog) []string {
	result := make([]string, 0, len(logs))
	for i := range logs {
		result = append(result, logs[i].Recipient)
	}

	slices.Sort(result)

	return result
}

func TestEventRepo_GetHistory_Filters(t *testing.T) {
	repo := newRepo(t)
	base := time.Now().UTC().Truncate(time.Millisecond)

	seed := []domain.EventLog{
		{
			Type:      domain.UserRegisteredEventKey,
			Channel:   "email",
			Recipient: "a@example.com",
			Status:    mailer.SendStatusSent,
			Attempts:  1,
			CreatedAt: base.Add(-2 * time.Hour),
		},
		{
			Type:      domain.OrderPaidEventKey,
			Channel:   "email",
			Recipient: "b@example.com",
			Status:    mailer.SendStatusFailed,
			Attempts:  3,
			Error:     "smtp unavailable",
			CreatedAt: base.Add(-time.Hour),
		},
		{
			Type:      domain.OrderPaidEventKey,
			Channel:   "sms",
			Recipient: "c@example.com",
			Status:    mailer.SendStatusSent,
			Attempts:  1,
			CreatedAt: base,
		},
	}

	for i := range seed {
		require.NoError(t, repo.SaveEvent(t.Context(), seed[i]))
	}

	tests := []struct {
		name    string
		filters domain.HistoryFilters
		want    []string
	}{
		{name: "no filters returns everything", filters: domain.HistoryFilters{}, want: []string{"a@example.com", "b@example.com", "c@example.com"}},
		{name: "by status", filters: domain.HistoryFilters{Status: string(mailer.SendStatusFailed)}, want: []string{"b@example.com"}},
		{name: "by channel", filters: domain.HistoryFilters{Channel: "sms"}, want: []string{"c@example.com"}},
		{name: "by recipient", filters: domain.HistoryFilters{Recipient: "a@example.com"}, want: []string{"a@example.com"}},
		{name: "from start date", filters: domain.HistoryFilters{StartDate: base.Add(-90 * time.Minute)}, want: []string{"b@example.com", "c@example.com"}},
		{name: "until end date", filters: domain.HistoryFilters{EndDate: base.Add(-90 * time.Minute)}, want: []string{"a@example.com"}},
		{
			name:    "date range",
			filters: domain.HistoryFilters{StartDate: base.Add(-150 * time.Minute), EndDate: base.Add(-30 * time.Minute)},
			want:    []string{"a@example.com", "b@example.com"},
		},
		{
			name:    "combined filters without match",
			filters: domain.HistoryFilters{Status: string(mailer.SendStatusFailed), Channel: "sms"},
			want:    []string{},
		},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			logs, err := repo.GetHistory(t.Context(), &tc.filters)

			require.NoError(t, err)
			require.NotNil(t, logs)
			assert.Equal(t, tc.want, recipientsOf(logs))
		})
	}
}

func TestEventRepo_GetByID(t *testing.T) {
	repo := newRepo(t)

	require.NoError(t, repo.SaveEvent(t.Context(), domain.EventLog{
		Type:      domain.OrderPaidEventKey,
		Channel:   "email",
		Recipient: "x@example.com",
		Status:    mailer.SendStatusSent,
		Attempts:  2,
		CreatedAt: time.Now().UTC(),
	}))

	saved, err := repo.GetHistory(t.Context(), &domain.HistoryFilters{Recipient: "x@example.com"})
	require.NoError(t, err)
	require.Len(t, saved, 1)

	t.Run("existing id returns the log", func(t *testing.T) {
		got, err := repo.GetByID(t.Context(), saved[0].ID.Hex())

		require.NoError(t, err)
		assert.Equal(t, domain.OrderPaidEventKey, got.Type)
		assert.Equal(t, "x@example.com", got.Recipient)
		assert.Equal(t, mailer.SendStatusSent, got.Status)
		assert.Equal(t, 2, got.Attempts)
	})

	t.Run("unknown id is not found", func(t *testing.T) {
		_, err := repo.GetByID(t.Context(), primitive.NewObjectID().Hex())

		require.ErrorIs(t, err, domain.ErrNotificationNotFound)
	})

	t.Run("malformed id is invalid", func(t *testing.T) {
		_, err := repo.GetByID(t.Context(), "not-an-object-id")

		require.ErrorIs(t, err, domain.ErrInvalidFilters)
	})
}
