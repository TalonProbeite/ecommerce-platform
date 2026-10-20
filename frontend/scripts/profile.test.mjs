import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'

test('profile sends changed fields only, verification updates session, logout clears local state on success and failure', async () => {
  const storage = new Map()
  globalThis.sessionStorage = {
    getItem: (key) => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, value),
    removeItem: (key) => storage.delete(key),
  }
  const server = await createServer({ configFile: false,
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    server: { middlewareMode: true, watch: null, ws: false }, appType: 'custom' })
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const { api } = await server.ssrLoadModule('/src/api/client.ts')
    await useAuthStore.getState().login({ email: 'user@rentify.dev', password: 'password123' })
    const original = useAuthStore.getState().user
    const { ProfilePage } = await server.ssrLoadModule('/src/pages/profile-page.tsx')
    const renderProfile = () => {
      // Match the signed-in browser snapshot for this SSR-only rendering check.
      Object.assign(useAuthStore.getInitialState(), useAuthStore.getState())
      return renderToStaticMarkup(createElement(MemoryRouter, null, createElement(ProfilePage)))
    }
    useAuthStore.setState({ user: { ...original, isOAuth: true } })
    assert.match(renderProfile(), /id="profile-email"[^>]*readOnly=""/)
    await assert.rejects(useAuthStore.getState().updateProfile({ ...original, email: 'changed@example.com' }), /OAuth/)
    useAuthStore.setState({ user: original })
    let payload
    let requests = 0
    const originalAdapter = api.defaults.adapter
    api.defaults.adapter = async config => {
      if (config.method === 'patch') { payload = JSON.parse(config.data); requests += 1 }
      return originalAdapter(config)
    }
    await useAuthStore.getState().updateProfile({ ...original, firstName: 'Updated' })
    assert.deepEqual(payload, { firstName: 'Updated' })
    assert.equal(useAuthStore.getState().user.lastName, 'Chen')
    assert.equal(useAuthStore.getState().user.phone, '+1 555 0142 000')
    await useAuthStore.getState().updateProfile({ ...useAuthStore.getState().user })
    assert.equal(requests, 1)
    await assert.rejects(useAuthStore.getState().updateProfile({ ...useAuthStore.getState().user, lastName: '' }), /empty/)
    assert.equal(requests, 1)
    api.defaults.adapter = originalAdapter

    await useAuthStore.getState().login({ email: 'admin@rentify.dev', password: 'password123' })
    // Staff fixtures can become customers through the documented role action.
    await api.patch('/auth/api/admin/users/u-admin/role', { role: 'customer' })
    await useAuthStore.getState().login({ email: 'admin@rentify.dev', password: 'password123' })
    await useAuthStore.getState().updateProfile({ firstName: 'Alexandra', phone: '' })
    assert.equal(useAuthStore.getState().user.firstName, 'Alexandra')
    assert.equal(useAuthStore.getState().user.phone, undefined)

    const unverified = { ...original, id: 'u-unverified', email: 'unverified@rentify.dev', emailVerified: false }
    sessionStorage.setItem('rentify_mock_session', JSON.stringify(unverified))
    useAuthStore.setState({ user: unverified, status: 'authenticated' })
    assert.match(renderProfile(), /Email not verified/)
    await useAuthStore.getState().verifyEmail({ email: unverified.email, code: '1234567890' })
    assert.equal(useAuthStore.getState().user.emailVerified, true)
    assert.doesNotMatch(renderProfile(), /Email not verified/)
    assert.equal((await api.get('/auth/me')).data.user.emailVerified, true)
    await useAuthStore.getState().logout()
    assert.equal(useAuthStore.getState().user, null)
    assert.equal(sessionStorage.getItem('rentify_mock_session'), null)

    await useAuthStore.getState().login({ email: 'user@rentify.dev', password: 'password123' })
    const adapter = api.defaults.adapter
    api.defaults.adapter = async () => { throw new Error('offline') }
    try {
      await assert.rejects(useAuthStore.getState().logout(), /offline/)
      assert.equal(useAuthStore.getState().user, null)
      assert.equal(useAuthStore.getState().status, 'unauthenticated')
    } finally { api.defaults.adapter = adapter }
  } finally {
    await server.close()
    delete globalThis.sessionStorage
  }
})
