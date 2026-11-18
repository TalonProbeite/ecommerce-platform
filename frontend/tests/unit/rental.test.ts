import test from 'node:test'
import assert from 'node:assert/strict'
import { billedHours, hasMinimumRentalDuration, isValidRentalRange, localInputToIso } from '@/lib/rental'

test('bills whole and partial hours upwards', () => {
  assert.equal(billedHours('2026-11-21T09:00:00.000Z', '2026-11-21T11:00:00.000Z'), 2)
  assert.equal(billedHours('2026-11-21T09:00:00.000Z', '2026-11-21T09:00:01.000Z'), 1)
  assert.equal(billedHours('2026-11-21T09:00:00.000Z', '2026-11-21T10:00:01.000Z'), 2)
})

test('rejects empty or reversed periods', () => {
  assert.equal(billedHours('2026-11-21T09:00:00.000Z', '2026-11-21T09:00:00.000Z'), 0)
  assert.equal(billedHours('2026-11-21T11:00:00.000Z', '2026-11-21T09:00:00.000Z'), 0)
  assert.equal(billedHours('invalid', '2026-11-21T09:00:00.000Z'), 0)
})

test('requires a future start and an end at least one hour later', () => {
  const now = new Date('2026-11-20T12:00:00.000Z')
  assert.equal(isValidRentalRange('2026-11-20T13:00:00.000Z', '2026-11-20T15:00:00.000Z', now), true)
  assert.equal(isValidRentalRange('2026-11-20T11:00:00.000Z', '2026-11-20T15:00:00.000Z', now), false)
  assert.equal(isValidRentalRange('2026-11-20T13:00:00.000Z', '2026-11-20T13:00:00.000Z', now), false)
  assert.equal(isValidRentalRange('2026-11-20T13:00:00.000Z', '2026-11-20T13:30:00.000Z', now), false)
  assert.equal(hasMinimumRentalDuration('2026-11-20T13:00:00.000Z', '2026-11-20T14:00:00.000Z'), true)
  assert.equal(hasMinimumRentalDuration('2026-11-20T13:00:00.000Z', '2026-11-20T13:59:59.999Z'), false)
})

test('converts datetime-local values to an ISO instant', () => {
  const iso = localInputToIso('2026-11-21T09:30')
  assert.ok(iso)
  assert.equal(new Date(iso).getHours(), 9)
  assert.equal(localInputToIso('not-a-date'), null)
})
