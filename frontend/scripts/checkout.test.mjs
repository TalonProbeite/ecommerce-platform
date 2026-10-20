import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'

test('checkout validates rentals and details, submits once, and preserves state on failure', async () => {
  const server = await createServer({ configFile: false, envDir: false,
    define: { 'import.meta.env.VITE_API_MODE': '"mock"', 'import.meta.env.VITE_API_URL': '""' },
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    server: { middlewareMode: true, watch: null, ws: false }, appType: 'custom' })
  try {
    const { CheckoutPage } = await server.ssrLoadModule('/src/pages/checkout-page.tsx')
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const { useCartStore } = await server.ssrLoadModule('/src/store/cart-store.ts')
    const { useOrdersStore } = await server.ssrLoadModule('/src/store/orders-store.ts')
    const { todayISO, addDaysISO } = await server.ssrLoadModule('/src/lib/pricing.ts')
    const user = { id: 'checkout-user', firstName: 'Maya', email: 'maya@example.com', phone: '+1 555 0142 000', role: 'customer' }
    const item = { equipmentId: 'sony-a7-iv', startDate: todayISO(), endDate: addDaysISO(todayISO(), 2) }
    const sync = () => {
      Object.assign(useAuthStore.getInitialState(), useAuthStore.getState())
      Object.assign(useCartStore.getInitialState(), useCartStore.getState())
      Object.assign(useOrdersStore.getInitialState(), useOrdersStore.getState())
    }
    const render = (path) => { sync(); return renderToStaticMarkup(createElement(MemoryRouter, { initialEntries: [path] }, createElement(CheckoutPage))) }
    useAuthStore.setState({ user, status: 'authenticated' })
    useCartStore.setState({ items: [item] })
    assert.match(render('/checkout/delivery'), /Contact name/)
    const { validateCheckoutDetails, reviewCart, placeMockOrder } = await server.ssrLoadModule('/src/services/checkout.ts')
    const { CheckoutDetailsFields, CheckoutFeedback } = await server.ssrLoadModule('/src/components/checkout/checkout-content.tsx')
    const pickup = { fulfillment: 'pickup', contactName: ' Maya ', phone: user.phone, address: '', city: '', postalCode: '' }
    const delivery = { ...pickup, fulfillment: 'delivery', address: ' 12 Main St ', city: ' Boston ', postalCode: ' 02110 ' }
    const fields = (details, errors = {}, disabled = false) => renderToStaticMarkup(createElement(CheckoutDetailsFields, { details, errors, disabled, onChange: () => {} }))
    assert.doesNotMatch(fields(pickup), /Street address/)
    assert.match(fields(delivery), /Street address/)
    assert.match(fields(delivery, { address: 'Required' }, true), /disabled=""/)
    assert.match(fields(delivery, { address: 'Required' }), /aria-describedby="checkout-address-error"/)
    const feedback = (props) => renderToStaticMarkup(createElement(CheckoutFeedback, { placing: false, error: '', invalid: false, ...props }))
    assert.match(feedback({ placing: true }), /role="status"/)
    assert.match(feedback({ error: 'Offline' }), /role="alert".*Offline/)
    assert.match(feedback({ invalid: true }), /highlighted delivery details/)
    assert.equal(feedback({}), '')
    assert.match(render('/checkout/cart'), /aria-current="step"/)
    assert.match(render('/checkout/cart'), /Continue to delivery details/)
    assert.deepEqual(validateCheckoutDetails(pickup), {})
    assert.equal(Object.keys(validateCheckoutDetails({ ...pickup, contactName: ' ', phone: '', fulfillment: 'delivery' })).length, 5)
    assert.ok(validateCheckoutDetails({ ...pickup, phone: 'abcdefghij' }).phone)
    assert.ok(validateCheckoutDetails({ ...pickup, fulfillment: 'invalid' }).fulfillment)
    assert.equal(reviewCart([item]).lines[0].days, 3)
    for (const bad of [
      { ...item, equipmentId: 'removed' }, { ...item, startDate: 'bad' },
      { ...item, startDate: '2026-02-30' }, { ...item, endDate: addDaysISO(todayISO(), -1) },
      { ...item, startDate: addDaysISO(todayISO(), -1) },
    ]) assert.ok(reviewCart([bad]).errors.length)
    const before = useOrdersStore.getState().orders.length
    await assert.rejects(placeMockOrder({ ...pickup, contactName: '' }), /details/i)
    assert.equal(useCartStore.getState().items.length, 1)
    const request = placeMockOrder(delivery)
    await assert.rejects(placeMockOrder(delivery), /progress/i)
    const order = await request
    assert.equal(order.userId, user.id)
    assert.equal(order.total, 210.6)
    assert.equal(order.checkoutDetails.address, '12 Main St')
    assert.equal(useOrdersStore.getState().orders.length, before + 1)
    assert.deepEqual(useCartStore.getState().items, [])
    const success = render(`/checkout/success?order=${order.id}`)
    assert.match(success, /Rental confirmed/)
    assert.match(success, new RegExp(order.id))
    assert.match(success, /12 Main St/)
    assert.match(success, /href="\/orders"/)
    assert.match(render('/checkout'), /Your cart is empty/)
    assert.match(render('/checkout/unknown'), /Checkout step not found/)
    assert.match(render('/checkout/success?order=missing'), /Confirmation not found/)
    useAuthStore.setState({ user: { ...user, id: 'other' } })
    assert.doesNotMatch(render(`/checkout/success?order=${order.id}`), /12 Main St/)
    useAuthStore.setState({ user })
    useCartStore.setState({ items: [item] })
    const stale = placeMockOrder(pickup)
    useCartStore.getState().removeItem(item.equipmentId)
    await assert.rejects(stale, /cart changed/i)
    assert.equal(useOrdersStore.getState().orders.length, before + 1)
    useCartStore.setState({ items: [item] })
    const expired = placeMockOrder(pickup)
    useAuthStore.setState({ user: null })
    await assert.rejects(expired, /session/i)
    assert.equal(useCartStore.getState().items.length, 1)
    useAuthStore.setState({ user: { ...user, role: 'admin' } })
    await assert.rejects(placeMockOrder(pickup), /customer/i)
    assert.equal(useOrdersStore.getState().orders.length, before + 1)
    useAuthStore.setState({ user })
    const retried = await placeMockOrder(pickup)
    assert.equal(retried.fulfillment, 'pickup')
    assert.equal(retried.checkoutDetails.address, '')
    assert.equal(useOrdersStore.getState().orders.length, before + 2)
    await assert.rejects(placeMockOrder(pickup), /empty/i)
    useCartStore.setState({ items: [{ ...item, equipmentId: 'removed' }] })
    assert.match(render('/checkout/cart'), /role="alert"/)
    await assert.rejects(placeMockOrder(pickup), /unavailable/i)
    assert.equal(useCartStore.getState().items.length, 1)
    assert.equal(useOrdersStore.getState().orders.length, before + 2)
  } finally { await server.close() }
})
