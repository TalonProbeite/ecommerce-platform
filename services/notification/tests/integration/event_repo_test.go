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

func TestEventRepo_GetHistory_Pagination(t *testing.T) {
	repo := newRepo(t)
	base := time.Now().UTC().Truncate(time.Millisecond)

	// We need enough events to test DefaultHistoryLimit (100) and MaxHistoryLimit (500).
	// Let's insert 550 events. We use identical CreatedAt to test stable sorting.
	totalEvents := 550
	for i := 0; i < totalEvents; i++ {
		require.NoError(t, repo.SaveEvent(t.Context(), domain.EventLog{
			Type:      domain.UserRegisteredEventKey,
			Channel:   "email",
			Recipient: "page@example.com",
			Status:    mailer.SendStatusSent,
			Attempts:  1,
			CreatedAt: base, // all have the same timestamp to test _id tie-breaker
		}))
	}

	t.Run("default limit", func(t *testing.T) {
		logs, err := repo.GetHistory(t.Context(), &domain.HistoryFilters{})
		require.NoError(t, err)
		assert.Len(t, logs, int(domain.DefaultHistoryLimit))
	})

	t.Run("max limit", func(t *testing.T) {
		logs, err := repo.GetHistory(t.Context(), &domain.HistoryFilters{Limit: 10000}) // over max
		require.NoError(t, err)
		assert.Len(t, logs, int(domain.MaxHistoryLimit))
	})

	t.Run("explicit limit and offset", func(t *testing.T) {
		logs, err := repo.GetHistory(t.Context(), &domain.HistoryFilters{Limit: 15, Offset: 10})
		require.NoError(t, err)
		assert.Len(t, logs, 15)
	})

	t.Run("stable pagination across same timestamps", func(t *testing.T) {
		// fetch all 550 events in pages of 50
		var allIDs []string
		pageSize := int64(50)
		for offset := int64(0); offset < int64(totalEvents); offset += pageSize {
			logs, err := repo.GetHistory(t.Context(), &domain.HistoryFilters{Limit: pageSize, Offset: offset})
			require.NoError(t, err)
			if offset+pageSize <= int64(totalEvents) {
				assert.Len(t, logs, int(pageSize))
			}
			for _, log := range logs {
				allIDs = append(allIDs, log.ID.Hex())
			}
		}

		require.Len(t, allIDs, totalEvents)

		// check uniqueness of IDs
		uniqueIDs := make(map[string]bool)
		for _, id := range allIDs {
			uniqueIDs[id] = true
		}
		assert.Len(t, uniqueIDs, totalEvents, "Pagination missed or duplicated items")
	})

	t.Run("order is newest to oldest", func(t *testing.T) {
		// Add one older and one newer event
		older := base.Add(-time.Hour)
		newer := base.Add(time.Hour)

		require.NoError(t, repo.SaveEvent(t.Context(), domain.EventLog{Recipient: "order@example.com", CreatedAt: older}))
		require.NoError(t, repo.SaveEvent(t.Context(), domain.EventLog{Recipient: "order@example.com", CreatedAt: newer}))

		logs, err := repo.GetHistory(t.Context(), &domain.HistoryFilters{Recipient: "order@example.com"})
		require.NoError(t, err)
		require.Len(t, logs, 2)

		assert.Equal(t, newer, logs[0].CreatedAt)
		assert.Equal(t, older, logs[1].CreatedAt)
	})
}
