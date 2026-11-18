import test from 'node:test'
import assert from 'node:assert/strict'
import { cartIdempotencyKey } from '@/lib/idempotency'

const line = {
  productId: '33333333-3333-4333-8333-333333333333',
  quantity: 1,
  rentalStartAt: '2026-11-21T09:00:00.000Z',
  rentalEndAt: '2026-11-21T11:00:00.000Z',
}

test('derives a stable UUID-shaped key from cart contents', async () => {
  const first = await cartIdempotencyKey('customer-1', [line])
  const second = await cartIdempotencyKey('customer-1', [line])
  assert.equal(first, second)
  assert.match(first, /^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/)
})

test('changing quantity changes the key', async () => {
  const first = await cartIdempotencyKey('customer-1', [line])
  const second = await cartIdempotencyKey('customer-1', [{ ...line, quantity: 2 }])
  assert.notEqual(first, second)
})

test('changing the customer changes the key', async () => {
  assert.notEqual(
    await cartIdempotencyKey('customer-1', [line]),
    await cartIdempotencyKey('customer-2', [line]),
  )
})

test('is order-independent for the same lines', async () => {
  const other = { ...line, productId: '33333333-3333-4333-8333-444444444444' }
  assert.equal(
    await cartIdempotencyKey('customer-1', [line, other]),
    await cartIdempotencyKey('customer-1', [other, line]),
  )
})

test('rejects an empty cart', async () => {
  await assert.rejects(() => cartIdempotencyKey('customer-1', []))
})
