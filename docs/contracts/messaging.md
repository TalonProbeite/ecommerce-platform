# Messaging contract

This document is the source of truth for asynchronous messaging between the
Catalog and Order services. If the implementation and this document disagree,
fix the implementation (or this document) and record the change in the related
report.

## Common envelope

Every message body is a JSON object. The envelope fields come first and are
followed by the event-specific payload fields.

| Field | Type | Description |
| --- | --- | --- |
| `event_id` | UUID string | Globally unique event identifier. It is also sent as the AMQP `message_id` property. |
| `schema_version` | integer | Envelope/schema version. Currently `1`. |
| `occurred_at` | string | UTC timestamp in ISO 8601 with microsecond precision: `Y-m-d\TH:i:s.uP`, for example `2026-11-20T10:00:00.123456+00:00`. |

Payload fields are flattened into the same object after the envelope fields.
No nested `payload` wrapper is used.

Guarantees:

- Delivery is **at least once**. Consumers must tolerate duplicate
  `event_id` values.
- Catalog preserves ordering **within one aggregate** (`aggregate_key`) by
  publishing outbox rows strictly in insertion order. Ordering between
  aggregates is not guaranteed.
- Consumers deduplicate by `event_id`. Catalog stores processed inbound event
  IDs in `processed_events`.
- Catalog writes outbound events to its transactional outbox in the same
  database transaction as the state change. A separate relay publishes them to
  RabbitMQ.

### Currency

The platform uses a single currency, **EUR**. Product and stock payloads do
not carry a currency field; every monetary value is expressed in EUR.

## Exchanges and topology

| Exchange | Type | Declared by | Purpose |
| --- | --- | --- | --- |
| `catalog.events` | `topic`, durable | Catalog | Outbound Catalog events consumed by Order. |
| `order.events` | `topic`, durable | Order | Outbound Order events consumed by Catalog. |

Rules:

- Catalog declares `catalog.events` itself before the first publish
  (`OutboundTopology`, also used by the relay and the
  `messaging:topology:observer` command).
- Catalog declares its inbound queue and bindings on `order.events`
  (`App\Messaging\Inbound\Topology`).
- Order declares its own queues and bindings on `catalog.events`. Catalog never
  declares Order queues.
- The development observer queue `catalog.events.observer` is a local tool only
  and is not part of the production contract.

## Catalog → Order (`catalog.events`)

Order subscribes to these routing keys:

- `stock.reserved`
- `stock.reservation_failed`
- `product.created`
- `product.updated`
- `product.deleted`

### `stock.reserved`

| Field | Type | Description |
| --- | --- | --- |
| `order_id` | UUID string | Order aggregate identifier. |
| `store_id` | UUID string | Store the reservation belongs to. |

Example:

```json
{
  "event_id": "018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e",
  "schema_version": 1,
  "occurred_at": "2026-11-20T10:00:00.123456+00:00",
  "order_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "store_id": "3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f"
}
```

### `stock.reservation_failed`

| Field | Type | Description |
| --- | --- | --- |
| `order_id` | UUID string | Order aggregate identifier. |
| `store_id` | UUID string | Store the reservation belongs to. |
| `reason` | string | `insufficient_stock` or `product_not_found`. |

Example:

```json
{
  "event_id": "018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e",
  "schema_version": 1,
  "occurred_at": "2026-11-20T10:00:00.123456+00:00",
  "order_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "store_id": "3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f",
  "reason": "insufficient_stock"
}
```

### `product.created` / `product.updated`

Both routing keys carry a **full product snapshot**.

| Field | Type | Description |
| --- | --- | --- |
| `product_id` | UUID string | Product identifier. |
| `store_id` | UUID string | Store that owns the product. |
| `category_id` | UUID string | Category identifier. |
| `name` | string | Product name. |
| `price_per_hour` | integer | Hourly price in EUR. |
| `deposit_amount` | integer | Deposit amount in EUR. |
| `is_active` | boolean | Whether the product is active in the storefront. |

Example:

```json
{
  "event_id": "018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e",
  "schema_version": 1,
  "occurred_at": "2026-11-20T10:00:00.123456+00:00",
  "product_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "store_id": "3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f",
  "category_id": "a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223",
  "name": "Drill",
  "price_per_hour": 1500,
  "deposit_amount": 5000,
  "is_active": true
}
```

### `product.deleted`

| Field | Type | Description |
| --- | --- | --- |
| `product_id` | UUID string | Product identifier. |
| `store_id` | UUID string | Store that owned the product. |

Example:

```json
{
  "event_id": "018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e",
  "schema_version": 1,
  "occurred_at": "2026-11-20T10:00:00.123456+00:00",
  "product_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "store_id": "3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f"
}
```

### `stock.changed`

Catalog publishes this event for stock counter changes. **Order is not
subscribed to `stock.changed`.** It exists for other consumers and for
observability.

| Field | Type | Description |
| --- | --- | --- |
| `product_id` | UUID string | Product identifier. |
| `store_id` | UUID string | Store that owns the stock row. |
| `quantity` | integer | Total usable quantity. |
| `reserved` | integer | Units currently reserved or issued. |
| `available` | integer | `quantity - reserved`. |

Example:

```json
{
  "event_id": "018f3c52-6b0e-7f5a-9c1d-2f3a4b5c6d7e",
  "schema_version": 1,
  "occurred_at": "2026-11-20T10:00:00.123456+00:00",
  "product_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "store_id": "3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f",
  "quantity": 10,
  "reserved": 3,
  "available": 7
}
```

Category events (`category.created`, `category.updated`,
`category.deleted`) are also published on `catalog.events`. Order does not
subscribe to them. Their aggregate key is `category_id`.

## Order → Catalog (`order.events`)

Catalog subscribes to these routing keys:

- `order.created`
- `order.cancelled`
- `order.issued`
- `order.returned`

All timestamps are ISO 8601 with an explicit numeric offset (for example
`+00:00`). The `Z` suffix and offset-less values are rejected. Microseconds are
optional inbound (`Y-m-d\TH:i:s.uP` or `Y-m-d\TH:i:sP`).

### `order.created`

| Field | Type | Description |
| --- | --- | --- |
| `event_id` | UUID string | Event identifier. |
| `occurred_at` | string | Event time with offset. |
| `order_id` | UUID string | Order identifier. |
| `store_id` | UUID string | Store identifier. |
| `items` | array | 1 to 100 order lines. |

Each item:

| Field | Type | Description |
| --- | --- | --- |
| `line_id` | UUID string | Unique order line identifier. Must be distinct within an event. |
| `product_id` | UUID string | Product identifier. |
| `quantity` | integer | 1 to 1000. |
| `rental_start_at` | string | Rental start with offset. |
| `rental_end_at` | string | Rental end with offset; must be after `rental_start_at`. |

Example:

```json
{
  "event_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
  "occurred_at": "2026-11-20T10:00:00.123456+00:00",
  "order_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "store_id": "3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f",
  "items": [
    {
      "line_id": "a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223",
      "product_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
      "quantity": 2,
      "rental_start_at": "2026-11-21T09:00:00.000000+00:00",
      "rental_end_at": "2026-11-23T18:00:00.000000+00:00"
    }
  ]
}
```

### `order.cancelled`, `order.issued`, `order.returned`

| Field | Type | Description |
| --- | --- | --- |
| `event_id` | UUID string | Event identifier. |
| `occurred_at` | string | Event time with offset. |
| `order_id` | UUID string | Order identifier. |
| `store_id` | UUID string | Store identifier. |
| `items` | array | 1 to 100 order lines. |

Each item:

| Field | Type | Description |
| --- | --- | --- |
| `line_id` | UUID string | Unique order line identifier. Must be distinct within an event. |
| `product_id` | UUID string | Product identifier. |
| `quantity` | integer | 1 to 1000. |

Example (`order.issued`):

```json
{
  "event_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
  "occurred_at": "2026-11-21T09:00:00.123456+00:00",
  "order_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "store_id": "3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f",
  "items": [
    {
      "line_id": "a1a2a3a4-b1b2-4c1c-8d1d-1e1f20212223",
      "product_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
      "quantity": 2
    }
  ]
}
```

## Contract limits

- `items` contains from 1 to 100 entries.
- `quantity` is an integer from 1 to 1000.
- `rental_end_at` must be strictly after `rental_start_at`.
- All timestamps carry an explicit offset; `Z` is rejected.
- `line_id` values are unique within one event.

## Assumptions Catalog makes about Order

- `order.issued` and `order.returned` are sent only for orders that received
  `stock.reserved`.
- `order.cancelled` is not sent when `stock.reservation_failed` was received.
  Sending it is harmless but unnecessary.
- Issue and return may be partial and contain only a subset of the order lines.
- `order.cancelled` after `order.issued` is forbidden by Order.
- Order owns its queue and bindings on `catalog.events` for
  `stock.reserved`, `stock.reservation_failed`, `product.created`,
  `product.updated`, and `product.deleted`.

## Operational notes

- Outbound events that cannot be delivered remain in the outbox and are retried
  by the relay. After `OUTBOX_MAX_ATTEMPTS` failed attempts a row moves to
  `outbox_failed` and blocks later rows of the same aggregate.
- Use `php artisan outbox:failed:list` and
  `php artisan outbox:failed:requeue {event_id}` to inspect and recover
  quarantined rows.
- Current relay behavior fails unroutable messages quickly (`mandatory: true`
  with publisher confirms). Deploy the consuming Order queue/bindings before
  enabling Catalog publications in an environment, or keep a permanent
  observer queue while integrating.
