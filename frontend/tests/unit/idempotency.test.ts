import test from 'node:test'
import assert from 'node:assert/strict'
import {
  beginCheckoutAttempt,
  checkoutKey,
  completeCheckoutAttempt,
  retryCheckoutAttempt,
  syncCheckoutAttempt,
} from '@/lib/idempotency'

const line = {
  productId: '33333333-3333-4333-8333-333333333333',
  quantity: 1,
  rentalStartAt: '2026-11-21T09:00:00.000Z',
  rentalEndAt: '2026-11-21T11:00:00.000Z',
}

test('starts a checkout attempt with a random UUID key', () => {
  const first = beginCheckoutAttempt([line])
  const second = beginCheckoutAttempt([line])
  assert.match(first.key ?? '', /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/)
  assert.notEqual(first.key, second.key)
})

test('a successful order clears the key and the same cart gets a new one', () => {
  const first = beginCheckoutAttempt([line])
  const completed = completeCheckoutAttempt(first)
  assert.equal(completed.key, null)
  assert.notEqual(checkoutKey(completed), first.key)
})

test('changing the cart resets the key', () => {
  const first = beginCheckoutAttempt([line])
  const changed = syncCheckoutAttempt(first, [{ ...line, quantity: 2 }])
  assert.notEqual(changed.key, first.key)
  assert.notEqual(changed.fingerprint, first.fingerprint)
})

test('an unchanged cart keeps the existing key', () => {
  const first = beginCheckoutAttempt([line])
  assert.equal(syncCheckoutAttempt(first, [line]).key, first.key)
})

test('409, 422 and 429 keep the key for the retry', () => {
  const first = beginCheckoutAttempt([line])
  for (const status of [409, 422, 429]) {
    assert.equal(retryCheckoutAttempt(first, status).key, first.key)
  }
})

test('an order-independent cart signature keeps the key stable', () => {
  const other = { ...line, productId: '33333333-3333-4333-8333-444444444444' }
  const first = beginCheckoutAttempt([line, other])
  assert.equal(syncCheckoutAttempt(first, [other, line]).key, first.key)
})

test('an empty cart has no idempotency key', () => {
  assert.equal(beginCheckoutAttempt([]).key, null)
  assert.equal(syncCheckoutAttempt(beginCheckoutAttempt([line]), []).key, null)
})
