import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'

test('profile rental history shares orders, isolates users and renders its states', async () => {
  const server = await createServer({
    configFile: false, envDir: false,
    define: { 'import.meta.env.VITE_API_MODE': '"mock"', 'import.meta.env.VITE_API_URL': '""' },
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    server: { middlewareMode: true, ws: false, watch: null },
    appType: 'custom',
  })
  try {
    const { loadRentalHistory } = await server.ssrLoadModule('/src/services/rental-history.ts')
    const { useOrdersStore } = await server.ssrLoadModule('/src/store/orders-store.ts')
    const { RecentRentalsContent } = await server.ssrLoadModule('/src/components/profile/recent-rentals.tsx')
    const { OrdersPage } = await server.ssrLoadModule('/src/pages/orders-page.tsx')
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const render = (state) => renderToStaticMarkup(createElement(MemoryRouter, null,
      createElement(RecentRentalsContent, { state, onRetry: () => {} })))
    const original = useOrdersStore.getState().orders
    useOrdersStore.setState({ orders: [...original].reverse() })
    const orders = await loadRentalHistory('u-user')
    assert.deepEqual(orders.map((order) => order.id), original.map((order) => order.id))
    assert.deepEqual(useOrdersStore.getState().orders, [...original].reverse())
    assert.deepEqual(await loadRentalHistory('u-google'), [])
    const added = { ...original[0], id: 'RNT-NEW', createdAt: '2099-01-01' }
    useOrdersStore.getState().addOrder(added)
    assert.equal((await loadRentalHistory('u-user'))[0].id, 'RNT-NEW')
    const success = render({ status: 'success', orders })
    for (const order of orders) assert.ok(success.includes(order.id))
    assert.match(success, /Sony|sony/)
    assert.match(success, /confirmed|returned/)
    assert.match(success, /Total/)
    const missing = render({ status: 'success', orders: [{ ...added, lines: [{ ...added.lines[0], equipmentId: 'removed' }] }] })
    assert.match(missing, /Removed listing/)
    assert.match(render({ status: 'loading' }), /role="status"/)
    assert.match(render({ status: 'loading' }), /Loading rentals/)
    const empty = render({ status: 'success', orders: [] })
    assert.match(empty, /No rentals yet/)
    assert.match(empty, /href="\/catalog"/)
    const error = render({ status: 'error' })
    assert.match(error, /role="alert"/)
    assert.match(error, /Try again/)
    // Zustand's SSR snapshot normally holds the initial (signed-out) state.
    useOrdersStore.getInitialState().orders = useOrdersStore.getState().orders
    useAuthStore.getInitialState().user = { id: 'u-user' }
    const history = renderToStaticMarkup(createElement(MemoryRouter, null, createElement(OrdersPage)))
    assert.match(history, /RNT-NEW/)
    useAuthStore.getInitialState().user = { id: 'u-google' }
    assert.doesNotMatch(renderToStaticMarkup(createElement(MemoryRouter, null, createElement(OrdersPage))), /RNT-NEW/)
  } finally {
    await server.close()
  }
})
