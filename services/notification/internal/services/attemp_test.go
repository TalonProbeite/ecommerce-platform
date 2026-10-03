package services

import (
	"context"
	"testing"

	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/stretchr/testify/assert"
)

func TestAttemptFromContext(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name     string
		hasValue bool
		value    int
		want     int
	}{
		{name: "no value defaults to first attempt", hasValue: false, want: 1},
		{name: "stored value is returned", hasValue: true, value: 4, want: 4},
		{name: "zero falls back to first attempt", hasValue: true, value: 0, want: 1},
		{name: "negative falls back to first attempt", hasValue: true, value: -3, want: 1},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			ctx := context.Background()
			if tc.hasValue {
				ctx = withAttempt(ctx, tc.value)
			}

			assert.Equal(t, tc.want, attemptFromContext(ctx))
		})
	}
}

func TestDeliveryAttempt(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		headers amqp.Table
		want    int
	}{
		{name: "nil headers is first delivery", headers: nil, want: 1},
		{name: "missing header is first delivery", headers: amqp.Table{}, want: 1},
		{name: "int64 count is incremented", headers: amqp.Table{headerDeliveryCount: int64(2)}, want: 3},
		{name: "int32 count is incremented", headers: amqp.Table{headerDeliveryCount: int32(1)}, want: 2},
		{name: "int count is incremented", headers: amqp.Table{headerDeliveryCount: 4}, want: 5},
		{name: "zero count is first attempt", headers: amqp.Table{headerDeliveryCount: int64(0)}, want: 1},
		{name: "unexpected type falls back to first attempt", headers: amqp.Table{headerDeliveryCount: "3"}, want: 1},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			d := &amqp.Delivery{Headers: tc.headers}

			assert.Equal(t, tc.want, deliveryAttempt(d))
		})
	}
}
