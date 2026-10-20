import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'

test('password change preserves failed sessions and terminates successful sessions', async () => {
  const storage = new Map()
  globalThis.sessionStorage = {
    getItem: (key) => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, value),
    removeItem: (key) => storage.delete(key),
  }
  const server = await createServer({
    configFile: false,
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    server: { middlewareMode: true, hmr: false, watch: null },
    appType: 'custom',
  })
  try {
    const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
    const { api, getErrorMessage } = await server.ssrLoadModule('/src/api/client.ts')
    const { validatePassword } = await server.ssrLoadModule('/src/lib/validation.ts')
    const { PasswordSecurity } = await server.ssrLoadModule('/src/components/auth/password-security.tsx')
    const renderSecurity = (isOAuth) => renderToStaticMarkup(createElement(MemoryRouter, null, createElement(PasswordSecurity, { isOAuth })))
    const form = renderSecurity(false)
    assert.match(form, /name="old_pass"/)
    assert.match(form, /name="new_pass"/)
    assert.equal((form.match(/type="password"/g) ?? []).length, 2)
    assert.equal((form.match(/minLength="8"/g) ?? []).length, 2)
    assert.match(form, /Change password/)
    const oauth = renderSecurity(true)
    assert.match(oauth, /Manage your password and security in your Google account/)
    assert.doesNotMatch(oauth, /<form|<input|<button/)
    assert.ok(validatePassword(''))
    assert.ok(validatePassword('1234567'))
    assert.equal(validatePassword('12345678'), undefined)

    await useAuthStore.getState().login({ email: 'user@rentify.dev', password: 'password123' })
    const user = useAuthStore.getState().user
    await assert.rejects(useAuthStore.getState().changePassword({ old_pass: 'wrongpass', new_pass: 'newpass123' }), (error) => {
      assert.equal(error.response.status, 422)
      assert.match(getErrorMessage(error), /current password/i)
      return true
    })
    assert.equal(useAuthStore.getState().user, user)
    for (const payload of [
      { old_pass: '', new_pass: 'newpass123' },
      { old_pass: 'short', new_pass: 'newpass123' },
      { old_pass: 'password123', new_pass: 'short' },
    ]) {
      await assert.rejects(api.put('/auth/api/private/profile/password', payload), (error) => error.response.status === 422)
    }

    const oauthUser = { ...user, id: 'u-google', email: 'google.user@rentify.dev', isOAuth: true }
    sessionStorage.setItem('rentify_mock_session', JSON.stringify(oauthUser))
    useAuthStore.setState({ user: oauthUser })
    await assert.rejects(useAuthStore.getState().changePassword({ old_pass: 'password123', new_pass: 'newpass123' }))
    assert.equal(useAuthStore.getState().user, oauthUser)

    sessionStorage.setItem('rentify_mock_session', JSON.stringify(user))
    useAuthStore.setState({ user, sessionExpired: true })
    let request
    const originalAdapter = api.defaults.adapter
    api.defaults.adapter = async config => {
      request = config
      return originalAdapter(config)
    }
    await useAuthStore.getState().changePassword({ old_pass: 'password123', new_pass: 'newpass123' })
    api.defaults.adapter = originalAdapter
    assert.equal(request.method, 'put')
    assert.equal(request.url, '/auth/api/private/profile/password')
    assert.equal(request.credentials, 'include')
    assert.deepEqual(JSON.parse(request.data), { old_pass: 'password123', new_pass: 'newpass123' })
    assert.equal(useAuthStore.getState().user, null)
    assert.equal(useAuthStore.getState().status, 'unauthenticated')
    assert.equal(useAuthStore.getState().sessionExpired, false)
    assert.equal(sessionStorage.getItem('rentify_mock_session'), null)
    await assert.rejects(api.put('/auth/api/private/profile/password', { old_pass: 'newpass123', new_pass: 'another123' }), (error) => error.response.status === 401)
    assert.equal(useAuthStore.getState().user, null)
    assert.equal(useAuthStore.getState().sessionExpired, true)
    await assert.rejects(useAuthStore.getState().login({ email: user.email, password: 'password123' }))
    await useAuthStore.getState().login({ email: user.email, password: 'newpass123' })
  } finally {
    await server.close()
    delete globalThis.sessionStorage
  }
})
