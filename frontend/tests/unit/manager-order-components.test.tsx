import test from 'node:test'
import assert from 'node:assert/strict'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { ManagerActionPanel } from '@/components/manager/manager-action-panel'
import { ManagerOrderJournal } from '@/components/manager/manager-order-journal'
import type { RentalOrder, RentalOrderItem, RentalOrderStatus } from '@/types/rental-order'

function item(overrides: Partial<RentalOrderItem> = {}): RentalOrderItem {
  return {
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
    depositAmount: 5000,
    totalAmount: 9000,
    version: 1,
    items,
    createdAt: '2026-11-20T10:00:00.000000+00:00',
    updatedAt: '2026-11-20T10:00:00.000000+00:00',
    cancelledAt: null,
    completedAt: null,
  }
}

function submitButtonDisabled(html: string): boolean {
  const match = html.match(/<button[^>]*>(?:(?!<\/button>)[\s\S])*?Issue selected items/)
  assert.ok(match, 'issue submit button should be rendered')
  return /\sdisabled(=|\s|>)/.test(match[0].slice(0, match[0].indexOf('>')))
}

test('issue panel enables the button for a paid order with a selected reserved item', () => {
  const html = renderToStaticMarkup(createElement(ManagerActionPanel, {
    mode: 'issue',
    order: order('paid', [item({ status: 'reserved' })]),
    selectedIds: new Set(['01930000-0000-7000-8000-000000000001']),
  }))
  assert.match(html, /Issue selected items/)
  assert.equal(submitButtonDisabled(html), false)
})

test('issue panel disables the button when the order status does not allow issuance', () => {
  const html = renderToStaticMarkup(createElement(ManagerActionPanel, {
    mode: 'issue',
    order: order('created', [item({ status: 'reserved' })]),
    selectedIds: new Set(['01930000-0000-7000-8000-000000000001']),
  }))
  assert.match(html, /No reserved items are ready to be issued/)
})

test('return panel disables the button when nothing is selected', () => {
  const html = renderToStaticMarkup(createElement(ManagerActionPanel, {
    mode: 'return',
    order: order('in_rental', [item({ status: 'issued' })]),
    selectedIds: new Set<string>(),
  }))
  const match = html.match(/<button[^>]*>(?:(?!<\/button>)[\s\S])*?Return selected items/)
  assert.ok(match)
  assert.match(match[0].slice(0, match[0].indexOf('>')), /disabled/)
})

test('skipped items are reported as already issued instead of an error', () => {
  const skipped = item({ id: '01930000-0000-7000-8000-000000000002', productName: 'Ladder', status: 'issued' })
  const html = renderToStaticMarkup(createElement(ManagerActionPanel, {
    mode: 'issue',
    order: order('paid', [item({ status: 'reserved' })]),
    selectedIds: new Set<string>(),
    skippedItems: [skipped],
  }))
  assert.match(html, /Already issued/)
  assert.match(html, /Ladder/)
  assert.match(html, /role="status"/)
})

test('return panel previews the deposit refunded for the selected items', () => {
  const html = renderToStaticMarkup(createElement(ManagerActionPanel, {
    mode: 'return',
    order: order('in_rental', [item({ status: 'issued', depositTotalCents: 5000 })]),
    selectedIds: new Set(['01930000-0000-7000-8000-000000000001']),
  }))
  assert.match(html, /Deposit to refund/)
  assert.match(html, /€50\.00/)
})

test('journal renders the event transitions', () => {
  const html = renderToStaticMarkup(createElement(ManagerOrderJournal, {
    events: [{
      id: 10,
      fromStatus: 'created',
      toStatus: 'stock_reserved',
      orderItemId: null,
      actorRole: 'system',
      occurredAt: '2026-11-20T10:00:00.123456+00:00',
    }],
  }))
  assert.match(html, /created → stock reserved/)
  assert.match(html, /Journal/)
})
