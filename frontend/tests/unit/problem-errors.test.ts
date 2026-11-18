import test from 'node:test'
import assert from 'node:assert/strict'
import { HttpError } from '@/api/http'
import { orderErrorMessage } from '@/lib/order-error'

function problem(status: number, body: Record<string, unknown>): HttpError {
  return new HttpError(`HTTP ${status}`, String(status), {}, {
    status,
    data: { type: 'https://example.test/problem', title: 'Problem', status, ...body },
    headers: {},
    config: {},
  })
}

test('parses problem+json details and field errors', () => {
  assert.equal(
    orderErrorMessage(problem(422, { detail: 'The product is unavailable.', product_id: 'p-1' })),
    'The product is unavailable.',
  )
  assert.equal(
    orderErrorMessage(problem(422, { errors: { items: ['Mixed-store order is not allowed.'] } })),
    'Mixed-store order is not allowed.',
  )
})

test('maps lifecycle responses to actionable messages', () => {
  assert.match(orderErrorMessage(problem(409, {})), /already being processed/i)
  assert.match(orderErrorMessage(problem(429, {})), /wait/i)
  assert.match(orderErrorMessage(problem(401, {})), /session expired/i)
  assert.match(orderErrorMessage(problem(403, {})), /do not have access/i)
})
