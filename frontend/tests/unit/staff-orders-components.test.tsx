import test from 'node:test'
import assert from 'node:assert/strict'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { OrderErrorPanel } from '@/components/orders/order-error-panel'
import { StaffOrderActions } from '@/components/orders/staff-order-actions'
import type { RentalOrder, RentalOrderItem } from '@/types/rental-order'

const ITEM: RentalOrderItem = {
  id: '01930000-0000-7000-8000-000000000001',
  productId: '33333333-3333-4333-8333-333333333333',
  productName: 'Drill',
  quantity: 2,
  rentalStartAt: '2026-11-21T09:00:00.000000+00:00',
  rentalEndAt: '2026-11-21T11:00:00.000000+00:00',
  billedHours: 2,
  pricePerHourCents: 1000,
  depositPerUnitCents: 2500,
  lineTotalCents: 4000,
  depositTotalCents: 5000,
  status: 'reserved',
  issuedAt: null,
  returnedAt: null,
}

function order(): RentalOrder {
  return {
    id: '77777777-7777-4777-8777-777777777771',
    customerId: '11111111-1111-4111-8111-111111111111',
    storeId: '22222222-2222-4222-8222-222222222222',
    status: 'paid',
    cancelReason: null,
    currency: 'EUR',
    subtotalAmount: 4000,
    depositAmount: 5000,
    totalAmount: 9000,
    version: 1,
    items: [ITEM],
    createdAt: '2026-11-20T10:00:00.000000+00:00',
    updatedAt: '2026-11-20T10:00:00.000000+00:00',
    cancelledAt: null,
    completedAt: null,
  }
}

function actions(canManage: boolean): string {
  return renderToStaticMarkup(createElement(StaffOrderActions, {
    order: order(),
    canManage,
    busy: false,
    pendingAction: null,
    issueSelection: new Set<string>(),
    returnSelection: new Set<string>(),
    skippedIssueItems: [],
    skippedReturnItems: [],
    onToggleIssue: () => {},
    onToggleReturn: () => {},
    onSubmitIssue: () => {},
    onSubmitReturn: () => {},
    onCancel: () => {},
  }))
}

test('analyst does not see any order action buttons', () => {
  assert.equal(actions(false), '')
})

test('admin sees issue, return and cancel actions', () => {
  const html = actions(true)
  assert.match(html, /Cancel order/)
  assert.match(html, /Issue items/)
  assert.match(html, /Accept returns/)
})

test('a 403 response is rendered as an access message', () => {
  const html = renderToStaticMarkup(createElement(OrderErrorPanel, {
    title: 'Unable to load orders',
    error: 'You do not have access to this order.',
  }))
  assert.match(html, /No access/)
  assert.match(html, /You do not have access to this page\./)
  assert.doesNotMatch(html, /Unable to load orders/)
})
