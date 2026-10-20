import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'

const profile = { email: 'user@example.com', first_name: 'User', last_name: 'Name', phone: '1234567890', role: 'customer', is_verified: true }
const user = { email: profile.email, firstName: profile.first_name, lastName: profile.last_name, phone: profile.phone, role: profile.role, emailVerified: true }
const response = (config, data = profile) => ({ status: 200, data, headers: {}, config })
function deferred() {
  let resolve, reject
  const promise = new Promise((yes, no) => { resolve = yes; reject = no })
  return { promise, resolve, reject }
}
async function context(mode = 'real') {
  const server = await createServer({ configFile: false, envDir: false,
    optimizeDeps: { noDiscovery: true, include: [] },
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    define: { 'import.meta.env.VITE_API_MODE': JSON.stringify(mode), 'import.meta.env.VITE_API_URL': '"https://example.invalid"' },
    server: { middlewareMode: true, watch: null, ws: false }, appType: 'custom' })
  const client = await server.ssrLoadModule('/src/api/client.ts')
  const { useAuthStore } = await server.ssrLoadModule('/src/store/auth-store.ts')
  const { HttpError } = await server.ssrLoadModule('/src/api/http.ts')
  useAuthStore.setState({ user, status: 'authenticated', sessionError: null, sessionExpired: false })
  return { server, ...client, useAuthStore, HttpError }
}

test('background profile checks keep authenticated UI mounted and deduplicate requests', async () => {
  const { server, api, useAuthStore } = await context()
  try {
    assert.equal(typeof useAuthStore.getState().revalidateProfile, 'function')
    const pending = deferred()
    let requests = 0
    api.defaults.adapter = config => { requests++; return pending.promise.then(() => response(config, { ...profile, first_name: 'Updated' })) }
    const statuses = []
    const unsubscribe = useAuthStore.subscribe(state => statuses.push(state.status))
    const one = useAuthStore.getState().revalidateProfile()
    const two = useAuthStore.getState().revalidateProfile()
    assert.equal(useAuthStore.getState().status, 'authenticated')
    assert.equal(useAuthStore.getState().user, user)
    pending.resolve()
    await Promise.all([one, two])
    unsubscribe()
    assert.equal(requests, 1)
    assert.ok(statuses.every(status => status === 'authenticated'))
    assert.equal(useAuthStore.getState().user.firstName, 'Updated')
  } finally { await server.close() }
})

test('transient background failures preserve the user and allow later retry', async () => {
  const { server, api, useAuthStore, HttpError } = await context()
  try {
    assert.equal(typeof useAuthStore.getState().revalidateProfile, 'function')
    for (const status of [403, 500, 'network']) {
      api.defaults.adapter = async config => {
        if (status === 'network') throw new Error('offline')
        throw new HttpError('unavailable', String(status), config, { status, data: {}, config })
      }
      await assert.rejects(useAuthStore.getState().revalidateProfile())
      assert.equal(useAuthStore.getState().user, user)
      assert.equal(useAuthStore.getState().status, 'authenticated')
      assert.equal(useAuthStore.getState().sessionExpired, false)
    }
    api.defaults.adapter = async config => response(config)
    await useAuthStore.getState().revalidateProfile()
    assert.equal(useAuthStore.getState().user.email, profile.email)
  } finally { await server.close() }
})

test('background 401 still expires the session after one refresh attempt', async () => {
  const { server, api, useAuthStore, HttpError } = await context()
  try {
    assert.equal(typeof useAuthStore.getState().revalidateProfile, 'function')
    const paths = []
    api.defaults.adapter = async config => { paths.push(config.url); throw new HttpError('expired', '401', config, { status: 401, data: {}, config }) }
    await assert.rejects(useAuthStore.getState().revalidateProfile())
    assert.deepEqual(paths, ['/auth/api/private/profile', '/auth/api/refresh'])
    assert.equal(useAuthStore.getState().user, null)
    assert.equal(useAuthStore.getState().status, 'unauthenticated')
    assert.equal(useAuthStore.getState().sessionExpired, true)
  } finally { await server.close() }
})

test('saving a REAL profile keeps authenticated state during follow-up reads and read failures', async () => {
  const { server, api, useAuthStore } = await context()
  try {
    for (const fails of [false, true]) {
      const pending = deferred()
      const readStarted = deferred()
      const statuses = []
      const unsubscribe = useAuthStore.subscribe(state => statuses.push(state.status))
      api.defaults.adapter = async config => {
        if (config.method === 'patch') return response(config, { message: 'updated' })
        readStarted.resolve()
        await pending.promise
        return response(config, { ...profile, first_name: 'Saved' })
      }
      const save = useAuthStore.getState().updateProfile({ firstName: fails ? 'Second save' : 'Saved' })
      await readStarted.promise
      assert.equal(useAuthStore.getState().status, 'authenticated')
      assert.ok(useAuthStore.getState().user)
      if (fails) {
        const rejection = assert.rejects(save, /offline/)
        pending.reject(new Error('offline'))
        await rejection
        assert.equal(useAuthStore.getState().user.firstName, 'Saved')
      } else {
        pending.resolve()
        await save
        assert.equal(useAuthStore.getState().user.firstName, 'Saved')
      }
      unsubscribe()
      assert.ok(statuses.every(status => status === 'authenticated'))
    }
  } finally { await server.close() }
})

test('a pre-save background response cannot overwrite the saved profile', async () => {
  const { server, api, useAuthStore } = await context()
  try {
    assert.equal(typeof useAuthStore.getState().revalidateProfile, 'function')
    const oldRead = deferred()
    let reads = 0
    api.defaults.adapter = async config => {
      if (config.method === 'patch') return response(config, { message: 'updated' })
      if (++reads === 1) { await oldRead.promise; return response(config) }
      return response(config, { ...profile, first_name: 'Saved' })
    }
    const background = useAuthStore.getState().revalidateProfile()
    await useAuthStore.getState().updateProfile({ firstName: 'Saved' })
    oldRead.resolve()
    await background
    assert.equal(useAuthStore.getState().user.firstName, 'Saved')
  } finally { await server.close() }
})

test('late background responses cannot restore a logged-out session', async () => {
  const { server, api, useAuthStore } = await context()
  try {
    assert.equal(typeof useAuthStore.getState().revalidateProfile, 'function')
    const read = deferred()
    api.defaults.adapter = async config => {
      if (config.url.endsWith('/logout')) return response(config, { message: 'logged out' })
      await read.promise
      return response(config)
    }
    const background = useAuthStore.getState().revalidateProfile()
    await useAuthStore.getState().logout()
    read.resolve()
    await background
    assert.equal(useAuthStore.getState().user, null)
    assert.equal(useAuthStore.getState().status, 'unauthenticated')
  } finally { await server.close() }
})

test('MOCK Google controls are disabled and never expose a localhost OAuth target', async () => {
  const { server, GOOGLE_OAUTH_URL, useAuthStore } = await context('mock')
  try {
    assert.equal(GOOGLE_OAUTH_URL, null)
    useAuthStore.setState({ user: null, status: 'unauthenticated' })
    Object.assign(useAuthStore.getInitialState(), useAuthStore.getState())
    const { GoogleButton } = await server.ssrLoadModule('/src/components/auth/google-button.tsx')
    const { LoginPage } = await server.ssrLoadModule('/src/pages/login-page.tsx')
    const button = renderToStaticMarkup(createElement(GoogleButton, { label: 'Google sign-in' }))
    assert.match(button, /disabled=""/)
    const login = renderToStaticMarkup(createElement(MemoryRouter, null, createElement(LoginPage)))
    assert.match(login, /<button[^>]*disabled=""[^>]*>Google Account<\/button>/)
  } finally { await server.close() }
})
