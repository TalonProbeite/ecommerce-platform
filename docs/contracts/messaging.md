# Messaging contract

This document describes asynchronous messaging between the Catalog, Order, and
Notification services. The implementation is the source of truth for each
message a service emits or accepts. This document was synchronized with the
Order code in `services/order` during P12. If this document and the Order code
disagree, fix this document or record the difference as a P12 gap; do not change
application code as part of a documentation task.

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

Database time columns use precision 6; microseconds are preserved end to end (точность 6, микросекунды сохраняются).

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

| Exchange | Type | Durable | `auto_delete` | `internal` | Arguments | Declared by | Purpose |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `catalog.events` | `topic` | yes | no | no | none (empty table) | Catalog; Order declares it identically | Outbound Catalog events consumed by Order. |
| `order.events` | `topic` | yes | no | no | none (empty table) | Order, Catalog and Notification declare it identically | Outbound Order events consumed by Catalog and Notification. |

Order-side source of truth: `App\Infrastructure\Messaging\Topology`
(`OUTBOUND_*` and `INBOUND_*` constants). Both exchanges are declared with
`exchange_declare(name, type, passive=false, durable=true, auto_delete=false,
internal=false)` and no argument table.

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
- Notification declares the shared `order.events` exchange (same `topic`,
  durable parameters), its own `order.processing.q` queue, and its
  `dlx.exchange` dead-letter exchange. It never declares Catalog or Order
  queues.

## Queues

Queue names are owned by the consuming service. Dead-letter exchanges are
per-consumer; routing keys are prefixed with the owning service.

| Queue | Type | Durable | DLX | DL routing key | `x-delivery-limit` | `x-dead-letter-strategy` | `x-overflow` | Consumer |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `order.catalog-events` | quorum | yes | `order.catalog-events.dlx` | `order.catalog-events` | 5 | `at-least-once` | `reject-publish` | Order |
| `order.catalog-events.dead` | quorum | yes | — | — | — | — | — | Order DLQ (operator/replay) |
| `catalog.order-events` | quorum | yes | `catalog.order-events.dlx` | `catalog.order-events` | 5 | `at-least-once` | `reject-publish` | Catalog |
| `catalog.order-events.dead` | quorum | yes | — | — | — | — | — | Catalog DLQ (operator/replay) |
| `order.processing.q` | quorum | yes | `dlx.exchange` (fanout) | — | 5 | — (not set) | — (not set) | Notification |
| `failed.messages` | classic | yes | — | — | — | — | — | Notification DLQ (operator/replay) |

Order-side queue parameters (`App\Infrastructure\Messaging\Topology`):

- `order.catalog-events`: `x-queue-type=quorum`, durable, not exclusive, not
  auto-delete, `x-dead-letter-exchange=order.catalog-events.dlx`,
  `x-dead-letter-routing-key=order.catalog-events`, `x-delivery-limit=5`,
  `x-dead-letter-strategy=at-least-once`, `x-overflow=reject-publish`.
  Consumer: `php artisan messaging:consume-catalog-events`.
- `order.catalog-events.dead`: `x-queue-type=quorum`, durable, not exclusive,
  not auto-delete, no DLX and no `x-delivery-limit`/strategy/overflow
  arguments. It is bound to the direct DLX exchange `order.catalog-events.dlx`
  with routing key `order.catalog-events` (the inbound queue name). Consumer:
  operator/replay only.

`x-delivery-limit` comes from `INBOUND_DELIVERY_LIMIT` / `config/inbound.php`
(default `5`). Queue names come from `INBOUND_*` config with the same defaults.

## Subscribers

Every outbound routing key has an explicit owner of its bindings. A routing key
without a subscriber is still published for observability but is not consumed
by Order or Notification.

| Exchange | Routing key | Subscriber | Queue |
| --- | --- | --- | --- |
| `catalog.events` | `stock.reserved` | Order | `order.catalog-events` |
| `catalog.events` | `stock.reservation_failed` | Order | `order.catalog-events` |
| `catalog.events` | `product.created` | Order | `order.catalog-events` |
| `catalog.events` | `product.updated` | Order | `order.catalog-events` |
| `catalog.events` | `product.deleted` | Order | `order.catalog-events` |
| `catalog.events` | `stock.changed` | — | — |
| `catalog.events` | `category.created` | — | — |
| `catalog.events` | `category.updated` | — | — |
| `catalog.events` | `category.deleted` | — | — |
| `order.events` | `order.created` | Catalog | `catalog.order-events` |
| `order.events` | `order.cancelled` | Catalog, Notification | `catalog.order-events`, `order.processing.q` |
| `order.events` | `order.issued` | Catalog | `catalog.order-events` |
| `order.events` | `order.returned` | Catalog | `catalog.order-events` |
| `order.events` | `order.paid` | Notification | `order.processing.q` |

The Notification service is outside `services/order`. Its queue
`order.processing.q`, the `dlx.exchange` dead-letter exchange and the
`failed.messages` queue are an **external contract and are not verified by this
repository**. The Order side only guarantees that `order.paid` is published on
`order.events` with the payload below.

Order does **not** publish `order.confirmed`, although the TZ lists it and
Notification consumes it. This is a deviation from the TZ and is recorded in
`docs/order/tz-checklist.md`. The complete set of outbound keys in code is
`order.created`, `order.cancelled`, `order.issued`, `order.returned`,
`order.paid` (`App\Domain\Messaging\RoutingKeys::outbound()`).

The TZ also lists `order.payment_failed` and `stock.release_requested`; neither
exists in the Order code. A failed payment keeps the order in `payment_pending`
(the next PaymentIntent attempt is allowed), and cancellation publishes
`order.cancelled`, which Catalog uses to release the reservation. These are
recorded as TZ deviations, not as changes to the code.

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
| `price_per_hour` | integer, EUR cents (minor units) | Hourly price in minor units. |
| `deposit_amount` | integer, EUR cents (minor units) | Deposit amount in minor units. |
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

Every outbound Order message is written to the outbox as the event payload and
published by the relay with the common envelope added:

```json
{
  "event_id": "<outbox event_id, also AMQP message_id>",
  "schema_version": 1,
  "occurred_at": "<outbox occurred_at, UTC, Y-m-d\\TH:i:s.uP>"
}
```

The payload fields below are exactly what `toArray()` adds after the envelope.
`OrderEvent::toArray()` (used by `order.created`, `order.cancelled`,
`order.issued`, `order.returned`) returns `order_id`, `store_id`, `items`.
`OrderPaid::toArray()` returns `order_id`, `store_id`, `customer_id`,
`amount_total`, `currency`, `paid_at`.

Catalog subscribes to these routing keys:

- `order.created`
- `order.cancelled`
- `order.issued`
- `order.returned`

Order also publishes `order.paid` on `order.events`. It is consumed by
Notification (`order.processing.q`), not by Catalog. See the subscriber table
above for the full mapping.

All timestamps are ISO 8601 with an explicit numeric offset (for example
`+00:00`). The `Z` suffix and offset-less values are rejected. Microseconds are
optional inbound (`Y-m-d\TH:i:s.uP` or `Y-m-d\TH:i:sP`).

### `order.created`

| Field | Type | Description |
| --- | --- | --- |
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
  "schema_version": 1,
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
| `order_id` | UUID string | Order identifier. |
| `store_id` | UUID string | Store identifier. |
| `items` | array | 1 to 100 order lines. |

Each item:

| Field | Type | Description |
| --- | --- | --- |
| `line_id` | UUID string | Unique order line identifier. Must be distinct within an event. |
| `product_id` | UUID string | Product identifier. |
| `quantity` | integer | 1 to 1000. |

`order.cancelled` additionally includes `customer_id` (UUID string) so
Notification can resolve the recipient address from its stored user contact
mapping. `order.issued` and `order.returned` do not carry `customer_id`.

Example (`order.issued`):

```json
{
  "event_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
  "schema_version": 1,
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

### `order.paid`

Published on `order.events` and consumed by Notification. Catalog does not
subscribe to this routing key. Payload не содержит персональных данных; контакт
клиента Notification хранит в собственном маппинге `user_id → email`, который
обновляется по user-событиям Auth.

| Field | Type | Description |
| --- | --- | --- |
| `order_id` | UUID string | Order identifier. |
| `store_id` | UUID string | Store identifier. |
| `customer_id` | UUID string | Customer identifier. |
| `amount_total` | integer | Total paid amount in EUR minor units (cents). |
| `currency` | string | Always `EUR`. |
| `paid_at` | string | Payment time with offset. |

Example:

```json
{
  "event_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
  "schema_version": 1,
  "occurred_at": "2026-11-20T10:10:00.123456+00:00",
  "order_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "store_id": "3d9a1c2e-4b5f-4a6d-9e8f-1a2b3c4d5e6f",
  "customer_id": "11111111-1111-4111-8111-111111111111",
  "amount_total": 14000,
  "currency": "EUR",
  "paid_at": "2026-11-20T10:10:00.123456+00:00"
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
