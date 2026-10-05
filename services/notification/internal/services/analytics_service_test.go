package services

import (
	"errors"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"shop/notification/internal/domain"
)

func TestAnalyticsService_GetNotificationByID(t *testing.T) {
	t.Parallel()

	errRepo := errors.New("repo failure")

	tests := []struct {
		name          string
		id            string
		repoErr       error
		wantErr       error
		wantRepoCalls int
	}{
		{name: "empty id is rejected before hitting repo", id: "", wantErr: domain.ErrInvalidFilters, wantRepoCalls: 0},
		{name: "valid id is returned from repo", id: "abc123", wantRepoCalls: 1},
		{name: "repo error is propagated", id: "abc123", repoErr: errRepo, wantErr: errRepo, wantRepoCalls: 1},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			want := domain.EventLog{Type: "order.paid", Recipient: testRecipient}
			repo := &fakeReader{byID: want, err: tc.repoErr}
			svc := NewAnalyticsService(repo)

			got, err := svc.GetNotificationByID(t.Context(), tc.id)

			assert.Equal(t, tc.wantRepoCalls, repo.getByIDCalls)

			if tc.wantErr != nil {
				require.ErrorIs(t, err, tc.wantErr)

				return
			}

			require.NoError(t, err)
			assert.Equal(t, want, got)
			assert.Equal(t, tc.id, repo.gotID)
		})
	}
}

func TestAnalyticsService_GetNotificationsHistory(t *testing.T) {
	t.Parallel()

	errRepo := errors.New("repo failure")
	now := time.Now().UTC()

	tests := []struct {
		name          string
		filters       *domain.HistoryFilters
		repoErr       error
		wantErr       error
		wantRepoCalls int
	}{
		{name: "nil filters are rejected", filters: nil, wantErr: domain.ErrInvalidFilters},
		{
			name:    "start after end is rejected",
			filters: &domain.HistoryFilters{StartDate: now, EndDate: now.Add(-time.Hour)},
			wantErr: domain.ErrInvalidFilters,
		},
		{
			name:          "start before end is accepted",
			filters:       &domain.HistoryFilters{StartDate: now.Add(-time.Hour), EndDate: now},
			wantRepoCalls: 1,
		},
		{
			name:          "start equal to end is accepted",
			filters:       &domain.HistoryFilters{StartDate: now, EndDate: now},
			wantRepoCalls: 1,
		},
		{
			name:          "only start date is accepted",
			filters:       &domain.HistoryFilters{StartDate: now},
			wantRepoCalls: 1,
		},
		{
			name:          "only end date is accepted",
			filters:       &domain.HistoryFilters{EndDate: now},
			wantRepoCalls: 1,
		},
		{
			name:          "empty filters are accepted",
			filters:       &domain.HistoryFilters{},
			wantRepoCalls: 1,
		},
		{
			name:          "repo error is propagated",
			filters:       &domain.HistoryFilters{},
			repoErr:       errRepo,
			wantErr:       errRepo,
			wantRepoCalls: 1,
		},
		{
			name:          "negative limit is rejected",
			filters:       &domain.HistoryFilters{Limit: -1},
			wantErr:       domain.ErrInvalidFilters,
			wantRepoCalls: 0,
		},
		{
			name:          "negative offset is rejected",
			filters:       &domain.HistoryFilters{Offset: -1},
			wantErr:       domain.ErrInvalidFilters,
			wantRepoCalls: 0,
		},
		{
			name:          "positive limit and offset are accepted",
			filters:       &domain.HistoryFilters{Limit: 10, Offset: 20},
			wantRepoCalls: 1,
		},
		{
			name:          "zero limit and offset are accepted",
			filters:       &domain.HistoryFilters{Limit: 0, Offset: 0},
			wantRepoCalls: 1,
		},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			want := []domain.EventLog{{Type: "order.paid"}, {Type: "user.registered"}}
			repo := &fakeReader{history: want, err: tc.repoErr}
			svc := NewAnalyticsService(repo)

			got, err := svc.GetNotificationsHistory(t.Context(), tc.filters)

			assert.Equal(t, tc.wantRepoCalls, repo.historyCalls)

			if tc.wantErr != nil {
				require.ErrorIs(t, err, tc.wantErr)

				return
			}

			require.NoError(t, err)
			assert.Equal(t, want, got)
			assert.Same(t, tc.filters, repo.gotFilters)
		})
	}
}
