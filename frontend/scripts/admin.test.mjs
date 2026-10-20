import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter, Routes, Route } from 'react-router-dom'

test('admin mock actions enforce access, validate parameters and persist roles and bans', async () => {
  const storage = new Map()
  globalThis.sessionStorage = {
    getItem: (key) => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, value),
    removeItem: (key) => storage.delete(key),
  }
  const server = await createServer({
    configFile: false,
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    server: { middlewareMode: true, ws: false, watch: null },
    appType: 'custom',
  })
  try {
    const { api } = await server.ssrLoadModule('/src/api/client.ts')
    const rolePath = '/auth/api/admin/users/u-user/role'
    const banPath = '/auth/api/admin/users/u-user/ban'
    await assert.rejects(api.patch(rolePath, { role: 'analyst' }), (e) => e.response.status === 401)
    for (const email of ['user@rentify.dev', 'analyst@rentify.dev']) {
      await api.post('/auth/login', { email, password: 'password123' })
      await assert.rejects(api.patch(rolePath, { role: 'admin' }), (e) => e.response.status === 403)
      await assert.rejects(api.patch(banPath, { is_banned: true }), (e) => e.response.status === 403)
    }
    await api.post('/auth/login', { email: 'admin@rentify.dev', password: 'password123' })
    for (const role of ['', 'owner', null]) {
      await assert.rejects(api.patch(rolePath, { role }), (e) => e.response.status === 422)
    }
    for (const is_banned of ['true', null, 1]) {
      await assert.rejects(api.patch(banPath, { is_banned }), (e) => e.response.status === 422)
    }
    await assert.rejects(api.patch('/auth/api/admin/users/missing/role', { role: 'admin' }), (e) => e.response.status === 404)
    await assert.rejects(api.patch('/auth/api/admin/users/missing/ban', { is_banned: true }), (e) => e.response.status === 404)
    const { updateUserRole, updateUserBan } = await server.ssrLoadModule('/src/services/admin.ts')
    await assert.rejects(updateUserRole('   ', 'analyst'))
    for (const role of ['analyst', 'admin', 'customer']) {
      await updateUserRole(' u-user ', role)
      const { data } = await api.post('/auth/login', { email: 'user@rentify.dev', password: 'password123' })
      assert.equal(data.user.role, role)
      await api.post('/auth/login', { email: 'admin@rentify.dev', password: 'password123' })
    }
    await updateUserBan('u-user', true)
    await assert.rejects(api.post('/auth/login', { email: 'user@rentify.dev', password: 'password123' }), (e) => e.response.status === 403)
    await updateUserBan('u-user', false)
    await api.post('/auth/login', { email: 'user@rentify.dev', password: 'password123' })
    assert.equal((await api.get('/auth/me')).data.user.role, 'customer')
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const { ProtectedRoute } = await server.ssrLoadModule('/src/components/auth/protected-route.tsx')
    const { NAV_BY_ROLE } = await server.ssrLoadModule('/src/lib/roles.ts')
    const { AdminPage } = await server.ssrLoadModule('/src/pages/admin-page.tsx')
    await useAuthStore.getState().login({ email: 'admin@rentify.dev', password: 'password123' })
    const admin = useAuthStore.getState().user
    const renderGuard = () => {
      // Zustand SSR uses the initial snapshot; supply the current test session.
      Object.assign(useAuthStore.getInitialState(), useAuthStore.getState())
      return renderToStaticMarkup(createElement(MemoryRouter, { initialEntries: ['/admin'] },
      createElement(Routes, null,
        createElement(Route, { element: createElement(ProtectedRoute, { roles: ['admin'] }) },
          createElement(Route, { path: '/admin', element: createElement('p', null, 'Admin content') })),
      )))
    }
    assert.match(renderGuard(), /Admin content/)
    for (const role of ['customer', 'analyst']) {
      useAuthStore.setState({ user: { ...admin, role } })
      assert.doesNotMatch(renderGuard(), /Admin content/)
      assert.equal(NAV_BY_ROLE[role].some((link) => link.to === '/admin'), false)
    }
    useAuthStore.setState({ user: null, status: 'unauthenticated' })
    assert.doesNotMatch(renderGuard(), /Admin content/)
    useAuthStore.setState({ user: admin, status: 'authenticated' })
    const page = renderToStaticMarkup(createElement(MemoryRouter, null, createElement(AdminPage)))
    assert.match(page, /Change user role/)
    assert.match(page, /Ban or unban user/)
    assert.match(page, /User ID/)
    await updateUserRole('u-user', 'admin')
    await updateUserRole('u-admin', 'analyst')
    assert.equal(useAuthStore.getState().user.role, 'analyst')
    await assert.rejects(api.patch(rolePath, { role: 'admin' }), (e) => e.response.status === 403)
    await useAuthStore.getState().login({ email: 'user@rentify.dev', password: 'password123' })
    await updateUserBan('u-user', true)
    assert.equal(useAuthStore.getState().user, null)
    assert.equal(sessionStorage.getItem('rentify_mock_session'), null)
    await assert.rejects(api.post('/auth/login', { email: 'user@rentify.dev', password: 'password123' }), (e) => e.response.status === 403)
  } finally {
    await server.close()
    delete globalThis.sessionStorage
  }
})

test('admin actions in REAL mode require backend confirmation and expose enabled forms', async () => {
  const server = await createServer({
    configFile: false,
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    define: { 'import.meta.env.VITE_API_MODE': '"real"', 'import.meta.env.VITE_API_URL': '"https://example.invalid"' },
    server: { middlewareMode: true, ws: false, watch: null }, appType: 'custom',
  })
  try {
    const { api } = await server.ssrLoadModule('/src/api/client.ts')
    const requests = []
    let response = { message: 'confirmed' }
    api.defaults.adapter = async config => {
      requests.push({ path: config.url, method: config.method, body: JSON.parse(config.data) })
      return { status: 200, data: response, headers: {}, config }
    }
    const { updateUserRole, updateUserBan } = await server.ssrLoadModule('/src/services/admin.ts')
    assert.equal(await updateUserRole(' u/user ', 'admin'), 'confirmed')
    assert.equal(await updateUserBan('u-user', true), 'confirmed')
    assert.deepEqual(requests, [
      { path: '/auth/api/admin/users/u%2Fuser/role', method: 'patch', body: { role: 'admin' } },
      { path: '/auth/api/admin/users/u-user/ban', method: 'patch', body: { is_banned: true } },
    ])
    response = { user: { role: 'admin' } }
    await assert.rejects(updateUserRole('u-user', 'admin'), /response/i)
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    useAuthStore.setState({ user: { id: 'u-user', role: 'customer' }, status: 'authenticated' })
    await assert.rejects(updateUserRole('u-user', 'admin'), /response/i)
    assert.equal(useAuthStore.getState().user.role, 'customer')
    response = { message: 'confirmed' }
    await updateUserRole('u-user', 'analyst')
    assert.equal(useAuthStore.getState().user, null)
    assert.equal(useAuthStore.getState().status, 'error')
    const { AdminPage } = await server.ssrLoadModule('/src/pages/admin-page.tsx')
    const page = renderToStaticMarkup(createElement(MemoryRouter, null, createElement(AdminPage)))
    assert.doesNotMatch(page, /mock mode only/)
    assert.equal((page.match(/<fieldset disabled=""/g) ?? []).length, 0)
  } finally { await server.close() }
})
