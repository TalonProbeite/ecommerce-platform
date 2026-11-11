import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'

function json(body, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

test('catalog admin uses cookie credentials, refreshes once and respects FormData boundaries', async () => {
  const server = await createServer({
    configFile: false,
    envDir: false,
    define: { 'import.meta.env.VITE_API_MODE': '"real"', 'import.meta.env.VITE_API_URL': '"https://example.invalid"' },
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    server: { middlewareMode: true, ws: false, watch: null },
    appType: 'custom',
  })
  const originalFetch = globalThis.fetch
  const calls = []
  let adminCalls = 0
  let refreshCalls = 0

  globalThis.fetch = async (input, init = {}) => {
    const url = new URL(String(input))
    calls.push({ url, init })
    if (url.pathname === '/auth/api/refresh') {
      refreshCalls += 1
      return json({ message: 'refreshed' })
    }
    if (url.pathname === '/catalog/api/v1/admin/stores') {
      adminCalls += 1
      if (adminCalls === 1) return json({ message: 'expired' }, 401)
      return json({ data: { id: 'store-1' } }, 201)
    }
    if (url.pathname.endsWith('/images')) return json({ data: { id: 'image-1' } }, 201)
    if (url.pathname === '/catalog/api/v1/stores') return json({ data: [] })
    return json({}, 404)
  }

  try {
    const { catalogAdmin } = await server.ssrLoadModule('/src/lib/catalog/api.ts')
    const { listAllStores } = await server.ssrLoadModule('/src/services/catalog.ts')

    const stores = await listAllStores()
    assert.deepEqual(stores.data, [])
    const storesCall = calls.find((call) => call.url.pathname === '/catalog/api/v1/stores')
    assert.equal(storesCall.url.searchParams.get('active'), '')
    assert.equal(storesCall.url.searchParams.get('per_page'), '100')
    assert.equal(storesCall.init.credentials, 'include')

    const created = await catalogAdmin('/admin/stores', { method: 'post', body: JSON.stringify({ name: 'Store' }) })
    assert.deepEqual(created, { data: { id: 'store-1' } })
    assert.equal(refreshCalls, 1)
    assert.equal(adminCalls, 2)

    const adminCall = calls.find((call) => call.url.pathname === '/catalog/api/v1/admin/stores')
    const adminHeaders = new Headers(adminCall.init.headers)
    assert.equal(adminHeaders.get('Accept'), 'application/json')
    assert.equal(adminHeaders.get('Content-Type'), 'application/json')
    assert.equal(adminHeaders.has('Authorization'), false)

    const form = new FormData()
    form.append('image', new Blob(['x'], { type: 'image/png' }), 'image.png')
    await catalogAdmin('/admin/stores/store-1/products/product-1/images', { method: 'post', body: form })
    const uploadCall = calls.find((call) => call.url.pathname.endsWith('/images'))
    const uploadHeaders = new Headers(uploadCall.init.headers)
    assert.equal(uploadHeaders.has('Content-Type'), false)
    assert.equal(uploadCall.init.credentials, 'include')
  } finally {
    globalThis.fetch = originalFetch
    await server.close()
  }
})
