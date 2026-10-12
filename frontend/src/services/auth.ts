import { api, isMockApi } from '@/api/client'
import { messageResponse } from '@/api/response'
import type { ChangePasswordPayload, LoginPayload, ProfileChanges, RegisterPayload, User, VerifyEmailPayload } from '@/types'

const PROFILE_PATH = '/auth/api/private/profile'

// Mock envelopes are deliberately confined to this branch. A profile DTO mapper
// must be added from a confirmed backend schema, never derived from our fixtures.
export async function loadAccountProfile(): Promise<User> {
  if (isMockApi) return (await api.get<{ user: User }>('/auth/me')).data.user
  await api.get<unknown>(PROFILE_PATH)
  throw new Error('The backend profile response schema is not documented. Account access cannot be restored until this contract is confirmed.')
}

export async function loginAccount(payload: LoginPayload): Promise<User | undefined> {
  if (isMockApi) return (await api.post<{ user: User }>('/auth/login', payload)).data.user
  messageResponse((await api.post<unknown>('/auth/api/login', payload)).data)
}

export async function registerAccount(payload: RegisterPayload): Promise<void> {
  if (isMockApi) { await api.post('/auth/register', payload); return }
  const { email, password, firstName, lastName, phone } = payload
  messageResponse((await api.post<unknown>('/auth/api/register', {
    email, password, first_name: firstName, ...(lastName === undefined ? {} : { last_name: lastName }), phone,
  })).data)
}

export async function completeOAuthProfile(payload: Pick<RegisterPayload, 'firstName' | 'lastName' | 'phone'>): Promise<string> {
  if (isMockApi) throw new Error('OAuth completion requires the backend profile_key cookie and a confirmed frontend handoff.')
  const { firstName, lastName, phone } = payload
  return messageResponse((await api.post<unknown>('/auth/api/oauth/complete', {
    first_name: firstName, ...(lastName === undefined ? {} : { last_name: lastName }), phone,
  })).data)
}

export async function verifyAccountEmail(payload: VerifyEmailPayload): Promise<void> {
  const code = payload.code.trim()
  if (code.length !== 10) throw new Error('Verification code must contain exactly 10 characters.')
  if (isMockApi) { await api.post('/auth/verify-email', { ...payload, code }); return }
  messageResponse((await api.post<unknown>('/auth/api/private/email/verify', { code })).data)
}

export async function resendAccountVerification(email: string): Promise<void> {
  if (isMockApi) { await api.post('/auth/resend-verification', { email }); return }
  messageResponse((await api.post<unknown>('/auth/api/private/email/resend')).data)
}

export async function patchAccountProfile(changes: ProfileChanges, user: User): Promise<User | undefined> {
  const changedFields: Partial<ProfileChanges> = {}
  for (const field of ['email', 'firstName', 'lastName', 'phone'] as const) {
    const value = changes[field]
    if (value === undefined || value === (user[field] ?? '')) continue
    if (!value.trim()) throw new Error('Changed profile fields cannot be empty.')
    if (field === 'email' && user.isOAuth) throw new Error('Email cannot be changed for OAuth accounts.')
    changedFields[field] = value
  }
  if (Object.keys(changedFields).length === 0) return user
  if (isMockApi) return (await api.patch<{ user: User }>('/auth/me', changedFields)).data.user
  const names = { email: 'email', firstName: 'first_name', lastName: 'last_name', phone: 'phone' } as const
  const body = Object.fromEntries(Object.entries(changedFields).map(([key, value]) => [names[key as keyof typeof names], value]))
  messageResponse((await api.patch<unknown>(PROFILE_PATH, body)).data)
}

export async function changeAccountPassword(payload: ChangePasswordPayload): Promise<void> {
  if (payload.old_pass.length < 8 || payload.new_pass.length < 8) throw new Error('Both passwords must contain at least 8 characters.')
  const { data } = await api.put<unknown>('/auth/api/private/profile/password', payload)
  if (!isMockApi) messageResponse(data)
}

export async function logoutAccount(): Promise<void> {
  const { data } = await api.post<unknown>('/auth/api/logout')
  if (!isMockApi) messageResponse(data)
}
