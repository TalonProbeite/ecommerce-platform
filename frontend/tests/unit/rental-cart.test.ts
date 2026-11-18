import test from 'node:test'
import assert from 'node:assert/strict'
import { cartQuantity, cartTotals, MAX_CART_LINES, rentalItemKey, useRentalCartStore } from '@/store/rental-cart-store'
import type { RentalCartItem } from '@/store/rental-cart-store'

const base: RentalCartItem = {
  productId: '33333333-3333-4333-8333-333333333333',
  name: 'Drill',
  image: '/images/drill.png',
  storeId: '22222222-2222-4222-8222-222222222222',
  storeName: 'ToolShed',
  location: 'Chicago',
  pricePerHourCents: 500,
  depositPerUnitCents: 1000,
  quantity: 1,
  rentalStartAt: '2026-11-21T09:00:00.000Z',
  rentalEndAt: '2026-11-21T11:00:00.000Z',
}

test.beforeEach(() => {
  useRentalCartStore.setState({ items: [] })
})

test('adds and merges the same product and period', () => {
  const store = useRentalCartStore.getState()
  assert.equal(store.addItem(base), 'added')
  assert.equal(useRentalCartStore.getState().addItem({ ...base, quantity: 2 }), 'merged')
  assert.equal(useRentalCartStore.getState().items.length, 1)
  assert.equal(useRentalCartStore.getState().items[0].quantity, 3)
})

test('replaces the cart when the store changes', () => {
  const store = useRentalCartStore.getState()
  store.addItem(base)
  const result = useRentalCartStore.getState().addItem({
    ...base,
    productId: '33333333-3333-4333-8333-444444444444',
    storeId: '22222222-2222-4222-8222-333333333333',
  })
  assert.equal(result, 'replaced')
  const items = useRentalCartStore.getState().items
  assert.equal(items.length, 1)
  assert.equal(items[0].storeId, '22222222-2222-4222-8222-333333333333')
})

test('clamps quantities and removes lines', () => {
  const store = useRentalCartStore.getState()
  store.addItem(base)
  const key = rentalItemKey(base)
  useRentalCartStore.getState().updateQuantity(key, 0)
  assert.equal(useRentalCartStore.getState().items[0].quantity, 1)
  useRentalCartStore.getState().updateQuantity(key, 5000)
  assert.equal(useRentalCartStore.getState().items[0].quantity, 1000)
  useRentalCartStore.getState().removeItem(key)
  assert.equal(useRentalCartStore.getState().items.length, 0)
})

test('rejects an invalid period', () => {
  assert.throws(() => useRentalCartStore.getState().addItem({
    ...base,
    rentalEndAt: base.rentalStartAt,
  }))
})

test('rejects a rental shorter than one hour', () => {
  assert.throws(() => useRentalCartStore.getState().addItem({
    ...base,
    rentalEndAt: '2026-11-21T09:30:00.000Z',
  }))
})

test('rejects a new line beyond the 100 line limit', () => {
  const items = Array.from({ length: MAX_CART_LINES }, (_, index) => ({
    ...base,
    productId: `33333333-3333-4333-8333-${String(index).padStart(12, '0')}`,
  }))
  useRentalCartStore.setState({ items })
  assert.equal(items.length, 100)
  assert.throws(() => useRentalCartStore.getState().addItem({
    ...base,
    productId: '33333333-3333-4333-8333-999999999999',
  }), /100 lines/)
})

test('calculates rental and deposit totals in cents', () => {
  const items = [
    { ...base, quantity: 2 },
    {
      ...base,
      productId: '33333333-3333-4333-8333-444444444444',
      quantity: 1,
      rentalStartAt: '2026-11-22T09:00:00.000Z',
      rentalEndAt: '2026-11-22T12:00:00.000Z',
      pricePerHourCents: 700,
      depositPerUnitCents: 2000,
    },
  ]
  assert.deepEqual(cartTotals(items), {
    subtotalCents: 2 * 2 * 500 + 3 * 700,
    depositCents: 2 * 1000 + 2000,
    totalCents: 4100 + 4000,
  })
  assert.equal(cartQuantity(items), 3)
})
