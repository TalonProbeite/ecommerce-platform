import assert from 'node:assert/strict'
import { test } from 'node:test'
import { loadHttpErrors } from './http-errors.mjs'
import { createServer } from 'vite'

const { HttpError, CanceledError } = await loadHttpErrors()

async function context(mode = 'mock') {
  const server = await createServer({
    configFile: false, envDir: false,
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    define: { 'import.meta.env.VITE_API_MODE': JSON.stringify(mode), 'import.meta.env.VITE_API_URL': '"https://example.invalid"' },
    server: { middlewareMode: true, watch: null, ws: false }, appType: 'custom',
  })
  return { server, ...await server.ssrLoadModule('/src/api/client.ts') }
}

function failure(config, status) {
  return new HttpError(`HTTP ${status}`, String(status), config,
    { status, data: { error: `HTTP ${status}` }, headers: {}, config })
}

test('successful refresh preserves retry errors and does not expire valid sessions', async () => {
  const { server, api, onSessionExpired } = await context()
  try {
    let expired = 0
    onSessionExpired(() => { expired += 1 })
    for (const outcome of [403, 500, 'network', 'cancel']) {
      let refreshes = 0
      api.defaults.adapter = async config => {
        if (config.url === '/auth/api/refresh') {
          refreshes += 1
          return { status: 200, data: {}, headers: {}, config }
        }
        if (!config._retry) throw failure(config, 401)
        if (outcome === 'network') throw new HttpError('offline', 'ERR_NETWORK', config)
        if (outcome === 'cancel') throw new CanceledError('cancelled', config)
        throw failure(config, outcome)
      }
      await assert.rejects(api.get('/notification/api/private/notifications'), error => {
        if (outcome === 'network') return error.code === 'ERR_NETWORK'
        if (outcome === 'cancel') return error.code === 'ERR_CANCELED'
        return error.response?.status === outcome
      })
      assert.equal(refreshes, 1)
      assert.equal(expired, 0)
    }
  } finally { await server.close() }
})

test('parallel protected 401s share refresh; failed refresh and retry 401 terminate sessions without loops', async () => {
  const { server, api, onSessionExpired } = await context()
  try {
    let expired = 0
    onSessionExpired(() => { expired += 1 })
    for (const refreshFails of [false, true]) {
      let refreshes = 0
      let retries = 0
      api.defaults.adapter = async config => {
        if (config.url === '/auth/api/refresh') {
          refreshes += 1
          await new Promise(resolve => setTimeout(resolve, 20))
          if (refreshFails) throw failure(config, 401)
          return { status: 200, data: {}, headers: {}, config }
        }
        if (config._retry) retries += 1
        throw failure(config, 401)
      }
      const results = await Promise.allSettled([
        api.get('/auth/api/private/profile'), api.get('/notification/api/private/notifications'),
      ])
      assert.ok(results.every(result => result.status === 'rejected' && result.reason.response.status === 401))
      assert.equal(refreshes, 1)
      assert.equal(retries, refreshFails ? 0 : 2)
    }
    assert.ok(expired > 0)
    let requests = 0
    api.defaults.adapter = async config => { requests += 1; throw failure(config, 401) }
    for (const path of ['/auth/api/login', '/auth/api/register', '/auth/api/logout', '/auth/api/refresh', '/auth/api/oauth/complete', '/public-resource']) {
      await assert.rejects(api.post(path), error => error.response.status === 401)
    }
    assert.equal(requests, 6)
  } finally { await server.close() }
})

test('REAL auth store refuses malformed successes without populating mock users', async () => {
  const { server, api, getErrorMessage, GOOGLE_OAUTH_URL } = await context('real')
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    let requests = 0
    api.defaults.adapter = async config => { requests += 1; return { status: 200, data: {}, headers: {}, config } }
    await useAuthStore.getState().initialize()
    assert.equal(useAuthStore.getState().status, 'error')
    for (const action of [
      () => useAuthStore.getState().login({ email: 'user@example.com', password: 'password123' }),
      () => useAuthStore.getState().register({ email: 'user@example.com', password: 'password123', firstName: 'User', phone: '1234567890' }),
      () => useAuthStore.getState().verifyEmail({ email: 'user@example.com', code: '1234567890' }),
      () => useAuthStore.getState().sendVerification('user@example.com'),
    ]) {
      await assert.rejects(action(), error => /response/i.test(getErrorMessage(error)))
    }
    useAuthStore.setState({ user: { id: 'u', role: 'customer', email: 'user@example.com', firstName: 'User' }, status: 'authenticated' })
    await assert.rejects(useAuthStore.getState().updateProfile({ firstName: 'Updated' }), /response/i)
    assert.equal(requests, 6)
    assert.equal(GOOGLE_OAUTH_URL, 'https://example.invalid/auth/api/oauth/google')
    await assert.rejects(useAuthStore.getState().logout(), /response/i)
    assert.equal(requests, 7)
    assert.equal(useAuthStore.getState().user, null)
  } finally { await server.close() }
})

test('mock profile reload observes current roles and rejects banned sessions', async () => {
  const storage = new Map()
  globalThis.sessionStorage = { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) }
  const { server, api } = await context()
  try {
    const user = (await api.post('/auth/login', { email: 'user@rentify.dev', password: 'password123' })).data.user
    await api.post('/auth/login', { email: 'admin@rentify.dev', password: 'password123' })
    await api.patch('/auth/api/admin/users/u-user/role', { role: 'analyst' })
    sessionStorage.setItem('rentify_mock_session', JSON.stringify(user))
    assert.equal((await api.get('/auth/me')).data.user.role, 'analyst')
    await api.post('/auth/login', { email: 'admin@rentify.dev', password: 'password123' })
    await api.patch('/auth/api/admin/users/u-user/ban', { is_banned: true })
    sessionStorage.setItem('rentify_mock_session', JSON.stringify(user))
    await assert.rejects(api.get('/auth/me'), error => error.response.status === 403)
  } finally { await server.close(); delete globalThis.sessionStorage }
})
