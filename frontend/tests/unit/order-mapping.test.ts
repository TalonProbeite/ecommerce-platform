import test from 'node:test'
import assert from 'node:assert/strict'
import { parseOrderPage, parseOrderResponse, parsePaymentIntent } from '@/lib/order-mapping'

const order = {
  id: '77777777-7777-4777-8777-777777777771',
  customer_id: '11111111-1111-4111-8111-111111111111',
  store_id: '22222222-2222-4222-8222-222222222222',
  status: 'stock_reserved',
  cancel_reason: null,
  currency: 'EUR',
  subtotal_amount: 2000,
  deposit_amount: 5000,
  total_amount: 7000,
  version: 2,
  items: [{
    id: '01930000-0000-7000-8000-000000000001',
    product_id: '33333333-3333-4333-8333-333333333333',
    product_name: 'Drill',
    quantity: 2,
    rental_start_at: '2026-11-21T09:00:00.000000+00:00',
    rental_end_at: '2026-11-21T11:00:00.000000+00:00',
    billed_hours: 2,
    price_per_hour: 1000,
    deposit_per_unit: 2500,
    line_total: 4000,
    deposit_total: 5000,
    status: 'reserved',
    issued_at: null,
    issued_by: null,
    returned_at: null,
    returned_by: null,
  }],
  created_at: '2026-11-20T10:00:00.123456+00:00',
  updated_at: '2026-11-20T10:01:00.123456+00:00',
  cancelled_at: null,
  completed_at: null,
}

test('maps the Order API response to the view model', () => {
  const parsed = parseOrderResponse({ data: order })
  assert.equal(parsed.status, 'stock_reserved')
  assert.equal(parsed.currency, 'EUR')
  assert.equal(parsed.items[0].pricePerHourCents, 1000)
  assert.equal(parsed.items[0].rentalStartAt, order.items[0].rental_start_at)
})

test('rejects a non-EUR order', () => {
  assert.throws(() => parseOrderResponse({ data: { ...order, currency: 'USD' } }))
})

test('rejects an unknown status', () => {
  assert.throws(() => parseOrderResponse({ data: { ...order, status: 'shipped' } }))
})

test('maps cursor pagination', () => {
  const page = parseOrderPage({
    data: [order],
    meta: { next_cursor: 'cursor-1', limit: 20 },
  })
  assert.equal(page.orders.length, 1)
  assert.equal(page.nextCursor, 'cursor-1')
})

test('rejects a malformed payment intent', () => {
  assert.deepEqual(parsePaymentIntent({
    client_secret: 'pi_1_secret',
    status: 'requires_payment_method',
    amount_total: 7000,
    currency: 'EUR',
  }), {
    clientSecret: 'pi_1_secret',
    status: 'requires_payment_method',
    amountTotal: 7000,
    currency: 'EUR',
  })
  assert.throws(() => parsePaymentIntent({ client_secret: 'x', status: 'ok', amount_total: 1, currency: 'USD' }))
})

test('maps item actor identifiers supplied by the API', () => {
  const parsed = parseOrderResponse({
    data: {
      ...order,
      items: [{
        ...order.items[0],
        issued_at: '2026-11-21T09:05:00.000000+00:00',
        issued_by: '44444444-4444-4444-8444-444444444444',
        returned_at: '2026-11-21T10:55:00.000000+00:00',
        returned_by: '55555555-5555-4555-8555-555555555555',
      }],
    },
  })
  assert.equal(parsed.items[0].issuedBy, '44444444-4444-4444-8444-444444444444')
  assert.equal(parsed.items[0].returnedBy, '55555555-5555-4555-8555-555555555555')
})
