import { HttpError, type Adapter, type RequestConfig } from './http'
import { mockNotifications } from './mock-notifications'
import { DEMO_VERIFICATION_CODE, EXPIRED_VERIFICATION_CODE, MOCK_ACCOUNTS as accounts } from './mock-fixtures'
import type { ChangePasswordPayload, LoginPayload, ProfileChanges, RegisterPayload, User, VerifyEmailPayload } from '@/types'
import type { MockAccount } from './mock-fixtures'

export { ADMIN_MOCK_TARGETS, DEMO_ACCOUNTS, DEMO_VERIFICATION_CODE } from './mock-fixtures'

export const MOCK_ADAPTER_BUNDLE_MARKER = 'RENTIFY_MOCK_ADAPTER_SHOULD_NOT_SHIP'

// Dev-only stand-in for the backend's httpOnly cookies. The real app never touches auth cookies.
const SESSION_KEY = 'rentify_mock_session'


const delay = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))

function readSession(): User | null {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY)
    return raw ? (JSON.parse(raw) as User) : null
  } catch {
    return null
  }
}

function writeSession(user: User | null) {
  if (user) sessionStorage.setItem(SESSION_KEY, JSON.stringify(user))
  else sessionStorage.removeItem(SESSION_KEY)
}

function toPublicUser({ password: _password, verified, isOAuth, ...user }: MockAccount): User {
  return {
    ...user,
    emailVerified: verified ?? true,
    isOAuth: Boolean(isOAuth),
  }
}

function findAccount(email: string) {
  return accounts.find((item) => item.email.toLowerCase() === email.trim().toLowerCase())
}

function reply(config: RequestConfig, status: number, data: unknown) {
  const response = { data, status, statusText: String(status), headers: {}, config }
  if (status >= 200 && status < 300) return Promise.resolve(response)
  const message = (data as { message?: string }).message ?? 'Request failed'
  return Promise.reject(new HttpError(message, String(status), config, response))
}

export const mockAdapter: Adapter = async (config) => {
  await delay(350)
  const path = config.url ?? ''
  const method = (config.method ?? 'get').toLowerCase()
  const body = typeof config.data === 'string' ? JSON.parse(config.data) : (config.data ?? {})

  const session = readSession()
  const notificationReply = mockNotifications(config, accounts.find((item) => item.id === session?.id))
  if (notificationReply) return reply(config, notificationReply.status, notificationReply.data)

  if (method === 'post' && path === '/auth/login') {
    const { email, password } = body as LoginPayload
    const account = findAccount(email)
    if (!account || account.password !== password) {
      return reply(config, 401, { message: 'Incorrect email or password.' })
    }
    if (account.isBanned) return reply(config, 403, { error: 'This account is banned.' })
    const user = toPublicUser(account)
    writeSession(user)
    return reply(config, 200, { user })
  }

  if (method === 'post' && path === '/auth/register') {
    const { firstName, lastName, email, phone, password } = body as RegisterPayload
    if (findAccount(email)) {
      return reply(config, 409, { message: 'An account with this email already exists.' })
    }
    const account: MockAccount = {
      id: `u-${Date.now()}`,
      firstName: firstName.trim(),
      lastName: lastName?.trim() || undefined,
      email: email.trim(),
      phone: phone.trim(),
      role: 'customer',
      password,
      verified: false,
    }
    accounts.push(account)
    const user = toPublicUser(account)
    writeSession(user)
    return reply(config, 201, { user })
  }

  if (method === 'post' && path === '/auth/verify-email') {
    const { email, code } = body as VerifyEmailPayload
    const account = findAccount(email)
    if (!account) return reply(config, 404, { message: 'No account found for this email.' })
    if (code === EXPIRED_VERIFICATION_CODE) {
      return reply(config, 410, { code: 'CODE_EXPIRED', message: 'This code has expired. Request a new one.' })
    }
    if (code !== DEMO_VERIFICATION_CODE) {
      return reply(config, 400, { code: 'INVALID_CODE', message: 'That code is not valid. Check it and try again.' })
    }
    account.verified = true
    if (readSession()?.id === account.id) writeSession(toPublicUser(account))
    return reply(config, 200, { ok: true })
  }

  if (method === 'post' && path === '/auth/send-verification') {
    const { email } = body as { email: string }
    if (!findAccount(email)) return reply(config, 404, { message: 'No account found for this email.' })
    return reply(config, 200, { ok: true })
  }

  if (method === 'post' && (path === '/auth/logout' || path === '/auth/api/logout')) {
    writeSession(null)
    return reply(config, 200, { ok: true })
  }

  if (method === 'post' && path === '/auth/api/refresh') {
    const session = readSession()
    if (!session) return reply(config, 401, { message: 'Session expired.' })
    const account = accounts.find((item) => item.id === session.id)
    if (!account) return reply(config, 401, { error: 'Session expired.' })
    if (account?.isBanned) return reply(config, 403, { error: 'This account is banned.' })
    return reply(config, 200, { ok: true })
  }

  const adminAction = path.match(/^\/auth\/api\/admin\/users\/([^/]+)\/(role|ban)$/)
  if (method === 'patch' && adminAction) {
    const session = readSession()
    if (!session) return reply(config, 401, { error: 'Not authenticated.' })
    const actor = accounts.find((item) => item.id === session.id)
    if (!actor || actor.role !== 'admin' || actor.isBanned) {
      return reply(config, 403, { error: 'Only active admins can manage users.' })
    }
    const target = accounts.find((item) => item.id === decodeURIComponent(adminAction[1]))
    if (!target) return reply(config, 404, { error: 'User not found.' })
    if (adminAction[2] === 'role') {
      if (!['admin', 'customer', 'analyst'].includes(body.role)) {
        return reply(config, 422, { error: 'Choose admin, customer or analyst.' })
      }
      target.role = body.role
    } else {
      if (typeof body.is_banned !== 'boolean') return reply(config, 422, { error: 'Ban status must be true or false.' })
      target.isBanned = body.is_banned
    }
    if (target.id === session.id) writeSession(target.isBanned ? null : toPublicUser(target))
    return reply(config, 200, { message: adminAction[2] === 'role'
      ? 'user role updated successfully' : 'user ban status updated successfully' })
  }

  if (method === 'put' && path === '/auth/api/private/profile/password') {
    const session = readSession()
    if (!session) return reply(config, 401, { error: 'Not authenticated.' })
    const account = accounts.find((item) => item.id === session.id)
    if (session.isOAuth || account?.isOAuth) {
      return reply(config, 422, { error: 'Password is managed by your Google account.' })
    }
    const { old_pass, new_pass } = body as ChangePasswordPayload
    if (typeof old_pass !== 'string' || typeof new_pass !== 'string' || old_pass.length < 8 || new_pass.length < 8) {
      return reply(config, 422, { error: 'Both passwords must be at least 8 characters.' })
    }
    if (!account || account.password !== old_pass) {
      return reply(config, 422, { error: 'The current password is incorrect.' })
    }
    account.password = new_pass
    writeSession(null)
    return reply(config, 200, { message: 'password has been successfully reset' })
  }

  if (path === '/auth/me' || path === '/auth/api/private/profile') {
    const savedSession = readSession()
    const activeAccount = accounts.find((item) => item.id === savedSession?.id)
    if (!activeAccount) return reply(config, 401, { message: 'Not authenticated.' })
    if (activeAccount.isBanned) return reply(config, 403, { error: 'This account is banned.' })
    const session = toPublicUser(activeAccount)
    if (method === 'patch') {
      const changes = body as Partial<ProfileChanges>
      const account = accounts.find((item) => item.id === session.id)

      if (changes.email && changes.email.trim().toLowerCase() !== session.email.toLowerCase()) {
        if (account?.isOAuth || session.isOAuth) {
          return reply(config, 403, { message: 'Email cannot be changed for OAuth accounts.' })
        }
        const existing = accounts.find((item) => item.email.toLowerCase() === changes.email?.trim().toLowerCase() && item.id !== session.id)
        if (existing) {
          return reply(config, 409, { message: 'An account with this email already exists.' })
        }
      }

      const updated: User = {
        ...session,
        email: changes.email?.trim() ?? session.email,
        firstName: changes.firstName ?? session.firstName,
        lastName: changes.lastName ?? session.lastName,
        phone: changes.phone ?? session.phone,
      }
      if (account) {
        Object.assign(account, {
          ...account,
          ...updated,
          email: changes.email?.trim() ?? account.email,
        })
      }
      writeSession(updated)
      return reply(config, 200, { user: updated })
    }
    writeSession(session)
    return reply(config, 200, { user: session })
  }

  return reply(config, 404, { message: 'Not found.' })
}
