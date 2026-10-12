import assert from 'node:assert/strict'
import { test } from 'node:test'
import { AxiosError, CanceledError } from 'axios'
import { createServer } from 'vite'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter, Routes, Route } from 'react-router-dom'

async function context(mode = 'real') {
  const server = await createServer({
    configFile: false, envDir: false,
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    define: { 'import.meta.env.VITE_API_MODE': JSON.stringify(mode), 'import.meta.env.VITE_API_URL': '"https://example.invalid"' },
    server: { middlewareMode: true, watch: null, ws: false }, appType: 'custom',
  })
  const client = await server.ssrLoadModule('/src/api/client.ts')
  return { server, ...client }
}
function failure(config, status) {
  return new AxiosError(`HTTP ${status}`, String(status), config, null, { status, data: { error: `HTTP ${status}` }, headers: {}, config })
}
const user = { id: 'u', email: 'user@example.com', firstName: 'User', lastName: 'Name', phone: '1234567890', role: 'customer', isOAuth: false }

for (const mode of ['real', 'mock']) test(`${mode}: home renders immediately for guests, expired sessions, account errors and every role`, async () => {
  const { server } = await context(mode)
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const { RootPage } = await server.ssrLoadModule('/src/pages/root-page.tsx')
    const states = [
      ...['idle', 'loading', 'unauthenticated', 'error'].map(status => ({ status, user: null })),
      { status: 'unauthenticated', user: null, sessionExpired: true },
      ...['customer', 'admin', 'analyst'].map(role => ({ status: 'authenticated', user: { ...user, role } })),
    ]
    for (const state of states) {
      useAuthStore.setState(state)
      Object.assign(useAuthStore.getInitialState(), useAuthStore.getState())
      const html = renderToStaticMarkup(createElement(MemoryRouter, null, createElement(RootPage)))
      assert.match(html, /Premium gear, without the price tag/, JSON.stringify(state))
    }
  } finally { await server.close() }
})

test('REAL profile restoration refreshes expired access and preserves exact profile fields', async () => {
  const { server, api } = await context()
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const paths = []
    api.defaults.adapter = async config => {
      paths.push(config.url)
      if (config.url === '/auth/api/refresh') return { status: 200, data: { message: 'successfully refreshed' }, headers: {}, config }
      if (!config._retry) throw failure(config, 401)
      return { status: 200, data: { email: user.email, first_name: user.firstName, last_name: '', phone: '' }, headers: {}, config }
    }
    await useAuthStore.getState().initialize()
    assert.equal(useAuthStore.getState().status, 'authenticated')
    assert.deepEqual(useAuthStore.getState().user, { email: user.email, firstName: user.firstName, lastName: '', phone: '' })
    assert.deepEqual(paths, ['/auth/api/private/profile', '/auth/api/refresh', '/auth/api/private/profile'])
    assert.equal(useAuthStore.getState().sessionExpired, false)
  } finally { await server.close() }
})

test('REAL rejects malformed profile responses and does not trust extra privilege fields', async () => {
  const { server, api } = await context()
  try {
    const { loadAccountProfile } = await server.ssrLoadModule('/src/services/auth.ts')
    const profile = { email: user.email, first_name: user.firstName, last_name: '', phone: '' }
    for (const data of [null, [], {}, { user }, { ...profile, phone: null }, { ...profile, first_name: 123 }]) {
      api.defaults.adapter = async config => ({ status: 200, data, headers: {}, config })
      await assert.rejects(loadAccountProfile(), /invalid profile response/)
    }
    api.defaults.adapter = async config => ({ status: 200, data: { ...profile, role: 'admin', id: 'invented', emailVerified: true }, headers: {}, config })
    assert.deepEqual(await loadAccountProfile(), { email: user.email, firstName: user.firstName, lastName: '', phone: '' })
  } finally { await server.close() }
})

test('REAL expired access refreshes once and returns the successful protected response', async () => {
  const { server, api, onSessionExpired } = await context()
  try {
    const paths = []
    let expired = 0
    onSessionExpired(() => { expired++ })
    api.defaults.adapter = async config => {
      paths.push(config.url)
      assert.equal(config.withCredentials, true)
      if (config.url === '/auth/api/refresh') return { status: 200, data: { message: 'successfully refreshed' }, headers: {}, config }
      if (!config._retry) throw failure(config, 401)
      return { status: 200, data: { email: user.email, first_name: user.firstName }, headers: {}, config }
    }
    assert.equal((await api.get('/auth/api/private/profile')).data.email, user.email)
    assert.deepEqual(paths, ['/auth/api/private/profile', '/auth/api/refresh', '/auth/api/private/profile'])
    assert.equal(expired, 0)
  } finally { await server.close() }
})

test('REAL auth mutations map documented bodies and never use legacy mock paths', async () => {
  const { server, api } = await context()
  try {
    const auth = await server.ssrLoadModule('/src/services/auth.ts')
    const requests = []
    api.defaults.adapter = async config => {
      requests.push({ method: config.method, path: config.url, body: config.data === undefined ? undefined : JSON.parse(config.data), credentials: config.withCredentials })
      return { status: config.url.endsWith('/register') || config.url.endsWith('/complete') ? 201 : 200, data: { message: 'confirmed' }, headers: {}, config }
    }
    await auth.loginAccount({ email: user.email, password: 'password123' })
    await auth.registerAccount({ email: user.email, password: 'password123', firstName: 'User', phone: user.phone })
    await auth.verifyAccountEmail({ email: user.email, code: ' 1234567890 ' })
    await auth.resendAccountVerification(user.email)
    await auth.patchAccountProfile({ ...user, firstName: 'Updated' }, user)
    await auth.completeOAuthProfile({ firstName: 'User', lastName: 'Name', phone: user.phone })
    await auth.changeAccountPassword({ old_pass: 'password123', new_pass: 'password456' })
    await auth.logoutAccount()
    assert.deepEqual(requests, [
      { method: 'post', path: '/auth/api/login', body: { email: user.email, password: 'password123' }, credentials: true },
      { method: 'post', path: '/auth/api/register', body: { email: user.email, password: 'password123', first_name: 'User', phone: user.phone }, credentials: true },
      { method: 'post', path: '/auth/api/private/email/verify', body: { code: '1234567890' }, credentials: true },
      { method: 'post', path: '/auth/api/private/email/resend', body: undefined, credentials: true },
      { method: 'patch', path: '/auth/api/private/profile', body: { first_name: 'Updated' }, credentials: true },
      { method: 'post', path: '/auth/api/oauth/complete', body: { first_name: 'User', last_name: 'Name', phone: user.phone }, credentials: true },
      { method: 'put', path: '/auth/api/private/profile/password', body: { old_pass: 'password123', new_pass: 'password456' }, credentials: true },
      { method: 'post', path: '/auth/api/logout', body: undefined, credentials: true },
    ])
    await auth.patchAccountProfile(user, user)
    assert.equal(requests.length, 8)
    await assert.rejects(auth.patchAccountProfile({ firstName: '' }, user), /empty/i)
    await assert.rejects(auth.patchAccountProfile({ firstName: user.firstName, email: 'changed@example.com' }, { ...user, isOAuth: true }), /OAuth/)
    await assert.rejects(auth.verifyAccountEmail({ email: '', code: 'short' }), /10/)
    await assert.rejects(auth.changeAccountPassword({ old_pass: 'short', new_pass: 'password123' }), /8/)
    assert.equal(requests.length, 8)
  } finally { await server.close() }
})

test('REAL message validation rejects malformed successes; HTTP failures never become mock success', async () => {
  const { server, api } = await context()
  try {
    const auth = await server.ssrLoadModule('/src/services/auth.ts')
    for (const data of [null, {}, { user }, { message: 123 }, { message: '' }]) {
      api.defaults.adapter = async config => ({ status: 200, data, headers: {}, config })
      await assert.rejects(auth.loginAccount({ email: user.email, password: 'password123' }), /response/i)
    }
    for (const status of [400, 401, 403, 409, 422, 500]) {
      let requests = 0
      api.defaults.adapter = async config => { requests++; throw failure(config, status) }
      await assert.rejects(auth.loginAccount({ email: user.email, password: 'password123' }), e => e.response.status === status)
      assert.equal(requests, 1)
    }
  } finally { await server.close() }
})

test('REAL bootstrap, login and registration restore the actual backend profile without inventing roles', async () => {
  const { server, api } = await context()
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const paths = []
    api.defaults.adapter = async config => {
      paths.push(config.url)
      return { status: 200, data: config.method === 'get' ? { email: user.email, first_name: user.firstName, last_name: user.lastName, phone: user.phone } : { message: 'confirmed' }, headers: {}, config }
    }
    await useAuthStore.getState().initialize()
    assert.deepEqual(paths, ['/auth/api/private/profile'])
    assert.equal(useAuthStore.getState().status, 'authenticated')
    assert.deepEqual(useAuthStore.getState().user, { email: user.email, firstName: user.firstName, lastName: user.lastName, phone: user.phone })
    assert.equal(useAuthStore.getState().sessionError, null)
    await useAuthStore.getState().login({ email: user.email, password: 'password123' })
    assert.deepEqual(paths.slice(1), ['/auth/api/login', '/auth/api/private/profile'])
    assert.equal(useAuthStore.getState().user.role, undefined)
    await useAuthStore.getState().register({ email: user.email, password: 'password123', firstName: 'User', phone: user.phone })
    assert.deepEqual(paths.slice(3), ['/auth/api/register', '/auth/api/private/profile'])
    assert.equal(useAuthStore.getState().status, 'authenticated')
    await useAuthStore.getState().verifyEmail({ email: '', code: '1234567890' })
    assert.deepEqual(paths.slice(-2), ['/auth/api/private/email/verify', '/auth/api/private/profile'])
  } finally { await server.close() }
})

test('failed refresh preserves session for transient errors and cancellation, expires only on 401', async () => {
  const { server, api, onSessionExpired } = await context()
  try {
    let expired = 0
    onSessionExpired(() => { expired++ })
    for (const outcome of [403, 500, 'network', 'cancel', 401]) {
      let refreshes = 0
      api.defaults.adapter = async config => {
        if (config.url !== '/auth/api/refresh') throw failure(config, 401)
        refreshes++
        if (outcome === 'network') throw new AxiosError('offline', 'ERR_NETWORK', config)
        if (outcome === 'cancel') throw new CanceledError('cancelled', config)
        throw failure(config, outcome)
      }
      await assert.rejects(api.get('/auth/api/private/profile'), error => outcome === 'network' ? error.code === 'ERR_NETWORK' : outcome === 'cancel' ? error.code === 'ERR_CANCELED' : error.response.status === outcome)
      assert.equal(refreshes, 1)
      assert.equal(expired, outcome === 401 ? 1 : 0)
    }
  } finally { await server.close() }
})

test('profile bootstrap failures can be retried without false logout or stale privileged content', async () => {
  const { server, api } = await context()
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    for (const status of [403, 500]) {
      api.defaults.adapter = async config => { throw failure(config, status) }
      useAuthStore.setState({ user, status: 'authenticated' })
      await useAuthStore.getState().reloadProfile()
      assert.equal(useAuthStore.getState().status, 'error')
      assert.equal(useAuthStore.getState().sessionExpired, false)
      assert.match(useAuthStore.getState().sessionError, new RegExp(String(status)))
    }
    api.defaults.adapter = async config => { throw failure(config, 401) }
    await useAuthStore.getState().reloadProfile()
    assert.equal(useAuthStore.getState().status, 'unauthenticated')
    assert.equal(useAuthStore.getState().user, null)
  } finally { await server.close() }
})

test('REAL checkout never writes a simulated confirmed order', async () => {
  const { server } = await context()
  try {
    const { useOrdersStore } = await server.ssrLoadModule('/src/store/orders-store.ts')
    const { placeMockOrder } = await server.ssrLoadModule('/src/services/checkout.ts')
    const before = useOrdersStore.getState().orders
    await assert.rejects(placeMockOrder({ fulfillment: 'pickup', contactName: 'User', phone: user.phone, address: '', city: '', postalCode: '' }), /contract/i)
    assert.equal(useOrdersStore.getState().orders, before)
  } finally { await server.close() }
})

test('late bootstrap cannot restore local auth after logout', async () => {
  const { server, api } = await context()
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    let release
    api.defaults.adapter = config => config.method === 'get' ? new Promise(resolve => { release = () => resolve({ status: 200, data: { user }, headers: {}, config }) }) : Promise.resolve({ status: 200, data: { message: 'confirmed' }, headers: {}, config })
    const loading = useAuthStore.getState().initialize()
    await new Promise(resolve => setTimeout(resolve, 0))
    await useAuthStore.getState().logout()
    release()
    await loading
    assert.equal(useAuthStore.getState().status, 'unauthenticated')
    assert.equal(useAuthStore.getState().sessionError, null)
  } finally { await server.close() }
})

test('guards show retryable session errors and deny mismatched roles without privileged content', async () => {
  const { server } = await context()
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const { ProtectedRoute } = await server.ssrLoadModule('/src/components/auth/protected-route.tsx')
    function render(roles) {
      Object.assign(useAuthStore.getInitialState(), useAuthStore.getState())
      return renderToStaticMarkup(createElement(MemoryRouter, { initialEntries: ['/protected'] }, createElement(Routes, null,
        createElement(Route, { element: createElement(ProtectedRoute, { roles }) },
          createElement(Route, { path: '/protected', element: createElement('p', null, 'Secret content') })))) )
    }
    useAuthStore.setState({ status: 'error', user: null, sessionError: 'HTTP 500' })
    assert.match(render(['admin']), /HTTP 500/)
    assert.match(render(['admin']), /Try again/)
    for (const role of ['customer', 'analyst', 'admin']) {
      useAuthStore.setState({ status: 'authenticated', user: { ...user, role } })
      for (const roles of [['customer'], ['admin'], ['admin', 'analyst']]) {
        const html = render(roles)
        if (roles.includes(role)) assert.match(html, /Secret content/)
        else { assert.match(html, /Forbidden/); assert.doesNotMatch(html, /Secret content/) }
      }
    }
    useAuthStore.setState({ status: 'authenticated', user: { ...user, isBanned: true } })
    assert.doesNotMatch(render(['customer']), /Secret content/)
    useAuthStore.setState({ status: 'authenticated', user: { email: user.email, firstName: user.firstName } })
    assert.match(render(undefined), /Secret content/)
    for (const roles of [['customer'], ['admin'], ['admin', 'analyst']]) {
      assert.match(render(roles), /Forbidden/)
      assert.doesNotMatch(render(roles), /Secret content/)
    }
  } finally { await server.close() }
})

test('post-login destinations respect roles and reject external or public auth targets', async () => {
  const { server } = await context()
  try {
    const { loginDestination } = await server.ssrLoadModule('/src/lib/roles.ts')
    assert.equal(loginDestination(undefined), '/')
    assert.equal(loginDestination(undefined, '/profile'), '/profile')
    for (const from of ['/admin', '//evil.invalid', '/notifications']) assert.equal(loginDestination(undefined, from), '/')
    for (const from of ['https://evil.invalid', '//evil.invalid', '/login', '/register', '/admin', '/notifications', '/missing']) {
      assert.equal(loginDestination('customer', from), '/catalog')
    }
    assert.equal(loginDestination('customer', '/checkout/delivery?step=1'), '/checkout/delivery?step=1')
    assert.equal(loginDestination('analyst', '/admin'), '/notifications')
    assert.equal(loginDestination('admin', '/notifications'), '/notifications')
  } finally { await server.close() }
})

test('REAL email verification page uses the cookie session rather than requiring an email field', async () => {
  const { server } = await context()
  try {
    const { VerifyEmailPage } = await server.ssrLoadModule('/src/pages/verify-email-page.tsx')
    const html = renderToStaticMarkup(createElement(MemoryRouter, null, createElement(VerifyEmailPage)))
    assert.doesNotMatch(html, /id="email"/)
    assert.doesNotMatch(html, /Enter your email and/)
    assert.match(html, /Verify email/)
  } finally { await server.close() }
})

test('REAL order history refuses fixtures instead of presenting them as live orders', async () => {
  const { server } = await context()
  try {
    const { loadRentalHistory } = await server.ssrLoadModule('/src/services/rental-history.ts')
    await assert.rejects(loadRentalHistory('u-user'), /unavailable/i)
    const { OrdersPage } = await server.ssrLoadModule('/src/pages/orders-page.tsx')
    const html = renderToStaticMarkup(createElement(MemoryRouter, null, createElement(OrdersPage)))
    assert.match(html, /Rental history unavailable/)
    assert.doesNotMatch(html, /No rentals yet/)
  } finally { await server.close() }
})

test('late parallel 401s reuse the completed refresh instead of refreshing the same session twice', async () => {
  const { server, api } = await context()
  try {
    let refreshes = 0
    api.defaults.adapter = async config => {
      if (config.url === '/auth/api/refresh') {
        refreshes++
        return { status: 200, data: { message: 'confirmed' }, headers: {}, config }
      }
      if (!config._retry) {
        if (config.url.includes('notification')) await new Promise(resolve => setTimeout(resolve, 30))
        throw failure(config, 401)
      }
      return { status: 200, data: { message: 'confirmed' }, headers: {}, config }
    }
    await Promise.all([api.get('/auth/api/private/profile'), api.get('/notification/api/private/notifications')])
    assert.equal(refreshes, 1)
  } finally { await server.close() }
})

test('a guest session probe does not mark public navigation as an expired signed-in session', async () => {
  const { server, api } = await context()
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    api.defaults.adapter = async config => { throw failure(config, 401) }
    await useAuthStore.getState().initialize()
    assert.equal(useAuthStore.getState().status, 'unauthenticated')
    assert.equal(useAuthStore.getState().sessionExpired, false)
  } finally { await server.close() }
})

test('REAL password and logout change local state only under their documented lifecycle', async () => {
  const { server, api } = await context()
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    for (const outcome of [422, 500, 'network', 'cancel', 'malformed']) {
      useAuthStore.setState({ user, status: 'authenticated', sessionExpired: false })
      api.defaults.adapter = async config => {
        if (outcome === 'network') throw new AxiosError('offline', 'ERR_NETWORK', config)
        if (outcome === 'cancel') throw new CanceledError('cancelled', config)
        if (outcome === 'malformed') return { status: 200, data: {}, headers: {}, config }
        throw failure(config, outcome)
      }
      await assert.rejects(useAuthStore.getState().changePassword({ old_pass: 'password123', new_pass: 'password456' }))
      assert.equal(useAuthStore.getState().user, user)
      assert.equal(useAuthStore.getState().status, 'authenticated')
    }
    api.defaults.adapter = async config => ({ status: 200, data: { message: 'password has been successfully reset' }, headers: {}, config })
    await useAuthStore.getState().changePassword({ old_pass: 'password123', new_pass: 'password456' })
    assert.equal(useAuthStore.getState().user, null)
    assert.equal(useAuthStore.getState().status, 'unauthenticated')
    useAuthStore.setState({ user, status: 'authenticated' })
    api.defaults.adapter = async config => { throw new AxiosError('offline', 'ERR_NETWORK', config) }
    await assert.rejects(useAuthStore.getState().logout())
    assert.equal(useAuthStore.getState().user, null)
    assert.equal(useAuthStore.getState().status, 'unauthenticated')
  } finally { await server.close() }
})

test('aborting one protected request during a shared refresh does not cancel the other or expire the session', async () => {
  const { server, api, onSessionExpired } = await context()
  try {
    let expired = 0, refreshes = 0
    onSessionExpired(() => { expired++ })
    const controller = new AbortController()
    api.defaults.adapter = async config => {
      if (config.url === '/auth/api/refresh') {
        refreshes++
        await new Promise(resolve => setTimeout(resolve, 20))
        controller.abort()
        return { status: 200, data: { message: 'confirmed' }, headers: {}, config }
      }
      if (!config._retry) throw failure(config, 401)
      return { status: 200, data: {}, headers: {}, config }
    }
    const outcomes = await Promise.allSettled([api.get('/auth/api/private/profile', { signal: controller.signal }), api.get('/notification/api/private/notifications')])
    assert.equal(outcomes[0].status, 'rejected')
    assert.equal(outcomes[0].reason.code, 'ERR_CANCELED')
    assert.equal(outcomes[1].status, 'fulfilled')
    assert.equal(refreshes, 1)
    assert.equal(expired, 0)
  } finally { await server.close() }
})
