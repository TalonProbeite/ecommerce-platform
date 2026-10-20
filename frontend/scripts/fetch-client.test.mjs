import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'

test('REAL transport uses fetch with credentials, JSON, query parameters and cancellation', async () => {
  const server = await createServer({ configFile: false, envDir: false, optimizeDeps: { noDiscovery: true, include: [] },
    define: { 'import.meta.env.VITE_API_MODE': '"real"', 'import.meta.env.VITE_API_URL': '"https://rentify.test"' },
    server: { middlewareMode: true, watch: null, ws: false } })
  const originalFetch = globalThis.fetch
  try {
    const { api } = await server.ssrLoadModule('/src/api/client.ts')
    const controller = new AbortController()
    let calls = 0
    globalThis.fetch = async (url, options) => {
      calls++
      assert.equal(String(url), 'https://rentify.test/example?recipient=a%2Bb%40example.com')
      assert.equal(options.credentials, 'include')
      assert.equal(options.method, 'POST')
      assert.equal(options.body, '{"hello":"world"}')
      assert.equal(new Headers(options.headers).get('Content-Type'), 'application/json')
      assert.equal(options.signal, controller.signal)
      return new Response('{"ok":true}', { status: 200 })
    }
    const response = await api.post('/example', { hello: 'world' }, { params: { recipient: 'a+b@example.com' }, signal: controller.signal })
    assert.equal(calls, 1)
    assert.deepEqual(response.data, { ok: true })
    controller.abort()
    await assert.rejects(api.get('/example', { signal: controller.signal }), error => error.code === 'ERR_CANCELED' || error.name === 'AbortError')
    assert.equal(calls, 1)
  } finally { globalThis.fetch = originalFetch; await server.close() }
})
