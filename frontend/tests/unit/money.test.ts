import test from 'node:test'
import assert from 'node:assert/strict'
import { assertNonNegativeCents, formatEurCents } from '@/lib/money'

test('formats integer EUR cents without floating-point maths', () => {
  assert.equal(formatEurCents(0), '€0.00')
  assert.equal(formatEurCents(1234), '€12.34')
  assert.equal(formatEurCents(10), '€0.10')
})

test('rejects non-integer or negative money', () => {
  assert.throws(() => formatEurCents(12.5))
  assert.throws(() => formatEurCents(-1))
  assert.throws(() => assertNonNegativeCents(-100))
  assert.equal(assertNonNegativeCents(100), 100)
})
