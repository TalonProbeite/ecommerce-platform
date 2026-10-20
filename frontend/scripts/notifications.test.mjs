import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter, Routes, Route } from 'react-router-dom'

const setup = (real = false) => createServer({
  configFile: false, envDir: false, resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
  define: { 'import.meta.env.VITE_API_MODE': JSON.stringify(real ? 'real' : 'mock'), 'import.meta.env.VITE_API_URL': '"https://example.invalid"' },
  server: { middlewareMode: true, ws: false, watch: null }, appType: 'custom',
})

test('notification query validation, omission and filter pagination reset', async () => {
  const server = await setup()
  try {
    const { buildNotificationQuery, changeNotificationFilters } = await server.ssrLoadModule('/src/services/notifications.ts')
    assert.equal(buildNotificationQuery({ recipient: '  ', status: '', channel: '', limit: 20, offset: 0 }).toString(), 'limit=20&offset=0')
    const query = buildNotificationQuery({ start_date: '2026-10-01T09:30', end_date: '2026-10-02T10:30', recipient: ' maya ', limit: 100, offset: 20 })
    assert.equal(query.get('start_date'), new Date('2026-10-01T09:30').toISOString())
    assert.equal(query.get('recipient'), 'maya')
    for (const filters of [{ start_date: 'invalid' }, { start_date: '2026-10-02', end_date: '2026-10-01' }, { limit: 101 }, { offset: -1 }, { limit: 1.5 }]) {
      assert.throws(() => buildNotificationQuery(filters))
    }
    assert.deepEqual(changeNotificationFilters({ limit: 20, offset: 40 }, { recipient: 'maya' }), { limit: 20, offset: 0, recipient: 'maya' })
  } finally { await server.close() }
})

test('mock notification list, pagination, details and access restrictions', async () => {
  const storage = new Map()
  globalThis.sessionStorage = { getItem: k => storage.get(k) ?? null, setItem: (k,v) => storage.set(k,v), removeItem: k => storage.delete(k) }
  const server = await setup()
  try {
    const { api } = await server.ssrLoadModule('/src/api/client.ts')
    const { listNotifications, getNotification } = await server.ssrLoadModule('/src/services/notifications.ts')
    const path = '/notification/api/private/notifications'
    await assert.rejects(listNotifications({}), e => e.response.status === 401)
    await api.post('/auth/login', { email: 'user@rentify.dev', password: 'password123' })
    await assert.rejects(listNotifications({}), e => e.response.status === 403)
    await assert.rejects(getNotification('n-1'), e => e.response.status === 403)
    for (const email of ['admin@rentify.dev', 'analyst@rentify.dev']) {
      await api.post('/auth/login', { email, password: 'password123' })
      const first = await listNotifications({ limit: 20, offset: 0 })
      const next = await listNotifications({ limit: 20, offset: 20 })
      assert.equal(first.items.length, 20)
      assert.equal(first.hasNext, true)
      assert.ok(next.items.every(item => !first.items.some(other => other.id === item.id)))
      const item = first.items[0]
      assert.deepEqual(await getNotification(item.id), item)
      const filtered = await listNotifications({ status: item.status, channel: item.channel, recipient: item.recipient, start_date: item.created_at, end_date: item.created_at })
      assert.ok(filtered.items.length > 0)
      assert.ok(filtered.items.every(n => n.status === item.status && n.channel === item.channel && n.recipient === item.recipient && n.created_at === item.created_at))
      assert.equal((await listNotifications({ recipient: 'nonexistent' })).items.length, 0)
      assert.equal((await listNotifications({ limit: 0 })).items.length, 53)
      assert.equal((await listNotifications({ offset: 1000 })).items.length, 0)
      await assert.rejects(getNotification('missing'), e => e.response.status === 404)
      await assert.rejects(api.get(path, { params: { start_date: 'invalid' } }), e => e.response.status === 400)
      await assert.rejects(api.post(path, {}), e => e.response.status === 404)
    }
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const { ProtectedRoute } = await server.ssrLoadModule('/src/components/auth/protected-route.tsx')
    const { NAV_BY_ROLE } = await server.ssrLoadModule('/src/lib/roles.ts')
    for (const role of ['customer', 'admin', 'analyst']) {
      useAuthStore.setState({ status: 'authenticated', user: { id: 'test', role } })
      Object.assign(useAuthStore.getInitialState(), useAuthStore.getState())
      const markup = renderToStaticMarkup(createElement(MemoryRouter, { initialEntries: ['/notifications'] }, createElement(Routes, null,
        createElement(Route, { element: createElement(ProtectedRoute, { roles: ['admin', 'analyst'], forbidden: true }) },
          createElement(Route, { path: '/notifications', element: createElement('p', null, 'Journal content') })))) )
      assert.match(markup, role === 'customer' ? /Forbidden/ : /Journal content/)
      assert.equal(NAV_BY_ROLE[role].some(link => link.to === '/notifications'), role !== 'customer')
    }
  } finally { await server.close(); delete globalThis.sessionStorage }
})

test('real notifications consume backend arrays and event records', async () => {
  const server = await setup(true)
  try {
    const { api } = await server.ssrLoadModule('/src/api/client.ts')
    const { listNotifications, getNotification } = await server.ssrLoadModule('/src/services/notifications.ts')
    const record = { id: '507f1f77bcf86cd799439011', created_at: '2026-10-09T10:00:00Z', type: 'order.confirmed', channel: 'email', recipient: 'maya@example.com', status: 'failed', payload: { order_id: '123' }, attempts: 3, error: 'SMTP unavailable' }
    const requests = []
    let response = [record]
    api.defaults.adapter = async config => {
      requests.push(config)
      return { data: response, status: 200, statusText: 'OK', headers: {}, config }
    }
    const controller = new AbortController()
    const page = await listNotifications({ limit: 1, offset: 20, status: 'failed' }, controller.signal)
    assert.deepEqual(page.items, [record])
    assert.equal(page.hasNext, true)
    assert.equal(requests[0].url, '/notification/api/private/notifications')
    assert.equal(requests[0].params.toString(), 'status=failed&limit=1&offset=20')
    assert.equal(requests[0].signal, controller.signal)
    assert.equal(requests[0].credentials, 'include')
    response = record
    assert.deepEqual(await getNotification(record.id), record)
    assert.equal(requests.at(-1).url, `/notification/api/private/notifications/${record.id}`)
    response = []
    assert.equal((await listNotifications({})).hasNext, false)
    response = { items: [record], total: 1 }
    await assert.rejects(listNotifications({}), /invalid notification response/i)
  } finally { await server.close() }
})

test('notification UI renders loading, error, forbidden and not found feedback', async () => {
  const server = await setup()
  try {
    const { ResourceFeedback } = await server.ssrLoadModule('/src/components/notifications/resource-feedback.tsx')
    const { notificationError } = await server.ssrLoadModule('/src/hooks/use-notification-resource.ts')
    const { HttpError } = await server.ssrLoadModule('/src/api/http.ts')
    for (const [status, message] of [[404, 'Notification not found'], [403, 'Forbidden'], [401, 'session has expired'], [500, 'Delivery journal unavailable']]) {
      const error = new HttpError('failed', undefined, undefined, { status, data: { error: 'Delivery journal unavailable' } })
      const text = notificationError(error)
      assert.match(text, new RegExp(message))
      const html = renderToStaticMarkup(createElement(ResourceFeedback, { resource: { state: 'error', message: text }, retry() {} }))
      assert.match(html, /role="alert"/)
      assert.match(html, /Try again/)
    }
    assert.match(renderToStaticMarkup(createElement(ResourceFeedback, { resource: { state: 'loading' }, retry() {} })), /role="status"/)
    const { NotificationsPage } = await server.ssrLoadModule('/src/pages/notifications-page.tsx')
    const html = renderToStaticMarkup(createElement(NotificationsPage))
    for (const label of ['Recipient', 'Start date', 'End date', 'All statuses', 'All channels', 'Per page', 'Loading notifications']) assert.ok(html.includes(label))
  } finally { await server.close() }
})
