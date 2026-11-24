import test from 'node:test'
import assert from 'node:assert/strict'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { OrderMetadata } from '@/components/orders/order-metadata'
import { StatusBadge } from '@/components/rental/status-badge'
import { RentalOrderCard } from '@/components/rental/rental-order-card'
import type { RentalOrder } from '@/types/rental-order'

const order: RentalOrder = {
  id: '77777777-7777-4777-8777-777777777771',
  customerId: '11111111-1111-4111-8111-111111111111',
  storeId: '22222222-2222-4222-8222-222222222222',
  status: 'stock_reserved',
  cancelReason: null,
  currency: 'EUR',
  subtotalAmount: 4000,
  depositAmount: 5000,
  totalAmount: 9000,
  version: 1,
  createdAt: '2026-11-20T10:00:00.000000+00:00',
  updatedAt: '2026-11-20T10:00:00.000000+00:00',
  cancelledAt: null,
  completedAt: null,
  items: [{
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
    issuedBy: null,
    returnedAt: null,
    returnedBy: null,
  }],
}

test('status badge renders the documented label', () => {
  const html = renderToStaticMarkup(createElement(StatusBadge, { status: 'stock_reserved', label: 'Ready to pay' }))
  assert.match(html, /Ready to pay/)
})

test('order card renders items, totals and a detail link', () => {
  const html = renderToStaticMarkup(
    createElement(MemoryRouter, null, createElement(RentalOrderCard, { order })),
  )
  assert.match(html, /Drill/)
  assert.match(html, /€90\.00/)
  assert.match(html, /\/orders\/77777777-7777-4777-8777-777777777771/)
})

test('order metadata renders lifecycle timestamps and internal ids', () => {
  const html = renderToStaticMarkup(createElement(OrderMetadata, {
    order: {
      ...order,
      status: 'completed',
      completedAt: '2026-11-22T12:00:00.000000+00:00',
    },
    showInternalIds: true,
  }))
  assert.match(html, /Order info/)
  assert.match(html, /Completed/)
  assert.match(html, /Customer ID/)
  assert.match(html, /11111111-1111-4111-8111-111111111111/)
})
