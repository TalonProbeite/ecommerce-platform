import test from 'node:test'
import assert from 'node:assert/strict'
import { isPollableStatus, ORDER_POLL_CEILING_MS, shouldPollOrder } from '@/lib/polling'

test('keeps polling only while the order is still being finalised', () => {
  assert.equal(isPollableStatus('created'), true)
  assert.equal(isPollableStatus('stock_reserved'), true)
  assert.equal(isPollableStatus('payment_pending'), true)
  assert.equal(isPollableStatus('paid'), false)
  assert.equal(isPollableStatus('in_rental'), false)
  assert.equal(isPollableStatus('completed'), false)
  assert.equal(isPollableStatus('cancelled'), false)
})

test('stops at the 60 second ceiling', () => {
  const startedAt = 1_000
  assert.equal(shouldPollOrder('created', startedAt, startedAt), true)
  assert.equal(shouldPollOrder('created', startedAt, startedAt + ORDER_POLL_CEILING_MS - 1), true)
  assert.equal(shouldPollOrder('created', startedAt, startedAt + ORDER_POLL_CEILING_MS), false)
  assert.equal(shouldPollOrder('paid', startedAt, startedAt + 1_000), false)
})
