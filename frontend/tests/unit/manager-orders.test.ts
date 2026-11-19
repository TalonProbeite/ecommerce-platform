import test from 'node:test'
import assert from 'node:assert/strict'
import { HttpError } from '@/api/http'
import { managerActionErrorMessage } from '@/lib/order-error'
import {
  canCancelOrder,
  canIssueOrder,
  canReturnOrder,
  issuableItems,
  returnableItems,
  returnedDepositCents,
  selectedEligibleIds,
  toggleSelection,
} from '@/lib/manager-orders'
import type { RentalOrder, RentalOrderItem, RentalOrderStatus } from '@/types/rental-order'

function item(overrides: Partial<RentalOrderItem> = {}): RentalOrderItem {
  return {
    id: '01930000-0000-7000-8000-000000000001',
    productId: '33333333-3333-4333-8333-333333333333',
    productName: 'Drill',
    quantity: 1,
    rentalStartAt: '2026-11-21T09:00:00.000000+00:00',
    rentalEndAt: '2026-11-21T11:00:00.000000+00:00',
    billedHours: 2,
    pricePerHourCents: 1000,
    depositPerUnitCents: 2500,
    lineTotalCents: 4000,
    depositTotalCents: 2500,
    status: 'reserved',
    issuedAt: null,
    returnedAt: null,
    ...overrides,
  }
}

function order(status: RentalOrderStatus, items: RentalOrderItem[]): RentalOrder {
  return {
    id: '77777777-7777-4777-8777-777777777771',
    customerId: '11111111-1111-4111-8111-111111111111',
    storeId: '22222222-2222-4222-8222-222222222222',
    status,
    cancelReason: null,
    currency: 'EUR',
    subtotalAmount: 4000,
    depositAmount: 2500,
    totalAmount: 6500,
    version: 1,
    items,
    createdAt: '2026-11-20T10:00:00.000000+00:00',
    updatedAt: '2026-11-20T10:00:00.000000+00:00',
    cancelledAt: null,
    completedAt: null,
  }
}

function problemError(status: number, detail: string): HttpError {
  return new HttpError(`HTTP ${status}`, String(status), undefined, {
    data: { type: 'about:blank', title: 'Error', status, detail },
    status,
  })
}

test('issue selection only offers reserved items while the order is paid or in rental', () => {
  const reservable = order('paid', [item({ id: 'a', status: 'reserved' }), item({ id: 'b', status: 'issued' })])
  assert.deepEqual(issuableItems(reservable).map((entry) => entry.id), ['a'])
  assert.equal(canIssueOrder(reservable), true)

  assert.equal(canIssueOrder(order('created', [item()])), false)
  assert.deepEqual(issuableItems(order('created', [item()])), [])
})

test('return selection only offers issued items while the order is in rental', () => {
  const inRental = order('in_rental', [item({ id: 'a', status: 'issued' }), item({ id: 'b', status: 'returned' })])
  assert.deepEqual(returnableItems(inRental).map((entry) => entry.id), ['a'])
  assert.equal(canReturnOrder(inRental), true)

  assert.equal(canReturnOrder(order('paid', [item({ status: 'issued' })])), false)
  assert.deepEqual(returnableItems(order('paid', [item({ status: 'issued' })])), [])
})

test('cancel is available only before issuance and after payment', () => {
  for (const status of ['created', 'stock_reserved', 'payment_pending', 'paid'] as const) {
    assert.equal(canCancelOrder(order(status, [item()])), true, status)
  }
  for (const status of ['in_rental', 'completed', 'cancelled'] as const) {
    assert.equal(canCancelOrder(order(status, [item()])), false, status)
  }
})

test('selection toggles and drops ineligible ids before submit', () => {
  let selected = toggleSelection(new Set<string>(), 'a')
  selected = toggleSelection(selected, 'b')
  assert.deepEqual([...selected].sort(), ['a', 'b'])

  selected = toggleSelection(selected, 'a')
  assert.deepEqual([...selected], ['b'])

  const eligible = [item({ id: 'b' })]
  assert.deepEqual(selectedEligibleIds(selected, eligible), ['b'])
  assert.deepEqual(selectedEligibleIds(new Set(['stale']), eligible), [])
})

test('returned deposit is the sum of the selected item deposits', () => {
  const deposit = returnedDepositCents([
    item({ id: 'a', depositTotalCents: 2500 }),
    item({ id: 'b', depositTotalCents: 1500 }),
  ])
  assert.equal(deposit, 4000)
})

test('maps manager action errors without leaking server detail', () => {
  assert.equal(managerActionErrorMessage(problemError(404, 'Order "x" was not found.')), 'The order was not found.')
  assert.equal(managerActionErrorMessage(problemError(403, 'Only admin')), 'You do not have access to this order.')
  assert.equal(managerActionErrorMessage(problemError(422, 'item does not belong')), 'One of the selected items is not part of this order.')
  assert.match(managerActionErrorMessage(problemError(409, 'Refund of 10 minor units exceeds')), /deposit refund/i)
  assert.match(managerActionErrorMessage(problemError(409, 'Order item cannot transition from "reserved"')), /can no longer change/i)
  assert.match(managerActionErrorMessage(problemError(409, 'Payment is still processing; retry later.')), /still processing/i)
  assert.match(managerActionErrorMessage(problemError(409, 'Something unexpected happened.')), /can no longer be completed/i)
})

test('falls back to the provided message for unknown errors', () => {
  assert.equal(managerActionErrorMessage(new Error('boom'), 'Custom fallback'), 'boom')
  assert.equal(managerActionErrorMessage(undefined, 'Custom fallback'), 'Custom fallback')
})
