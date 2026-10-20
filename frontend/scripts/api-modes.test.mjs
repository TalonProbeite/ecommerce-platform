import assert from 'node:assert/strict'
import { createServer as createHttpServer } from 'node:http'
import { test } from 'node:test'
import { createServer, loadEnv } from 'vite'

async function loadClient(mode, url) {
  const server = await createServer({
    configFile: false,
    envDir: false,
    mode,
    optimizeDeps: { entries: [] },
    define: {
      'import.meta.env.VITE_API_MODE': JSON.stringify(mode),
      'import.meta.env.VITE_API_URL': JSON.stringify(url),
    },
    server: { middlewareMode: true, watch: null, ws: false },
    appType: 'custom',
  })
  try {
    const client = await server.ssrLoadModule('/src/api/client.ts')
    return { server, client }
  } catch (error) {
    await server.close()
    throw error
  }
}

test('MOCK explicitly uses the existing adapter even when an API URL is configured', async () => {
  const storage = new Map()
  globalThis.sessionStorage = {
    getItem: (key) => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, value),
    removeItem: (key) => storage.delete(key),
  }
  const { server, client } = await loadClient('mock', 'https://example.invalid')
  try {
    const { mockAdapter } = await server.ssrLoadModule('/src/api/mock-adapter.ts')
    assert.equal(client.isMockApi, true)
    assert.equal(client.api.defaults.adapter, mockAdapter)
    assert.equal(client.api.defaults.credentials, 'include')
    const { data } = await client.api.post('/auth/login', { email: 'user@rentify.dev', password: 'password123' })
    assert.equal(data.user.email, 'user@rentify.dev')
    assert.ok(storage.has('rentify_mock_session'))
  } finally {
    await server.close()
    delete globalThis.sessionStorage
  }
})

test('REAL uses HTTP at the configured address and never falls back on errors', async () => {
  const requests = []
  const fixture = createHttpServer((request, response) => {
    requests.push({ method: request.method, url: request.url })
    const status = request.url === '/auth/api/refresh' ? 200 : request.url === '/auth/api/private/profile' ? 401 : request.url === '/missing' ? 404 : 503
    response.writeHead(status, { 'Content-Type': 'application/json' })
    response.end(JSON.stringify(status === 200 ? { message: 'fixture refresh' } : { error: `fixture HTTP ${status}` }))
  })
  await new Promise((resolve) => fixture.listen(0, '127.0.0.1', resolve))
  const url = `http://127.0.0.1:${fixture.address().port}`
  let storageAccesses = 0
  globalThis.sessionStorage = {
    getItem: () => { storageAccesses += 1; return null },
    setItem: () => { storageAccesses += 1 },
    removeItem: () => { storageAccesses += 1 },
  }
  const { server, client } = await loadClient('real', url)
  try {
    const { mockAdapter } = await server.ssrLoadModule('/src/api/mock-adapter.ts')
    assert.equal(client.isMockApi, false)
    assert.notEqual(client.api.defaults.adapter, mockAdapter)
    assert.equal(client.api.defaults.baseURL, url)
    assert.equal(client.api.defaults.credentials, 'include')
    await assert.rejects(client.api.post('/auth/api/login', { email: 'user@rentify.dev', password: 'password123' }), (error) => {
      assert.equal(error.response.status, 503)
      assert.equal(client.getErrorMessage(error), 'fixture HTTP 503')
      return true
    })
    assert.deepEqual(requests[0], { method: 'POST', url: '/auth/api/login' })
    await assert.rejects(client.api.get('/missing'), (error) => error.response.status === 404)
    let sessionExpired = 0
    client.onSessionExpired(() => { sessionExpired += 1 })
    await assert.rejects(client.api.get('/auth/api/private/profile'), (error) => error.response.status === 401)
    assert.equal(sessionExpired, 1)
    assert.deepEqual(requests.slice(2), [
      { method: 'GET', url: '/auth/api/private/profile' },
      { method: 'POST', url: '/auth/api/refresh' },
      { method: 'GET', url: '/auth/api/private/profile' },
    ])
    await new Promise((resolve) => fixture.close(resolve))
    await assert.rejects(client.api.post('/auth/api/login', { email: 'user@rentify.dev', password: 'password123' }), (error) => {
      assert.equal(error.response, undefined)
      assert.equal(error.code, 'ERR_NETWORK')
      return true
    })
    assert.equal(storageAccesses, 0)
    assert.equal(client.isMockApi, false)
  } finally {
    if (fixture.listening) await new Promise((resolve) => fixture.close(resolve))
    await server.close()
    delete globalThis.sessionStorage
  }
})

test('API configuration rejects invalid modes and missing REAL addresses', async () => {
  assert.equal(loadEnv('mock', process.cwd(), 'VITE_').VITE_API_MODE, 'mock')
  assert.equal(loadEnv('real', process.cwd(), 'VITE_').VITE_API_MODE, 'real')
  const server = await createServer({ configFile: false, optimizeDeps: { entries: [] }, server: { middlewareMode: true, watch: null, ws: false } })
  try {
    const { resolveApiConfig } = await server.ssrLoadModule('/src/api/config.ts')
    assert.throws(() => resolveApiConfig({ VITE_API_MODE: 'real' }), /VITE_API_URL/)
    assert.throws(() => resolveApiConfig({ VITE_API_MODE: 'typo' }), /VITE_API_MODE/)
    assert.throws(() => resolveApiConfig({ VITE_API_MODE: 'real', VITE_API_URL: 'not-a-url' }), /VITE_API_URL/)
    for (const url of ['https://example.invalid/auth/api', 'https://user:secret@example.invalid', 'https://example.invalid?token=secret', 'https://example.invalid/#fragment']) {
      assert.throws(() => resolveApiConfig({ VITE_API_MODE: 'real', VITE_API_URL: url }), /origin/i)
    }
    assert.equal(resolveApiConfig({}).isMockApi, true)
    assert.equal(resolveApiConfig({ VITE_API_URL: 'https://example.invalid' }).isMockApi, false)
  } finally {
    await server.close()
  }
})
