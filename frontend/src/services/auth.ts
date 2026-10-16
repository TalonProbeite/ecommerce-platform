import { api, isMockApi } from '@/api/client'
import { messageResponse } from '@/api/response'
import type { ChangePasswordPayload, LoginPayload, ProfileChanges, RegisterPayload, User, VerifyEmailPayload } from '@/types'

const PROFILE_PATH = '/auth/api/private/profile'

// services/auth/internal/domain/models.go: UserProfile (returned directly).
function accountProfile(data: unknown): User {
  if (!data || typeof data !== 'object') throw new Error('The server returned an invalid profile response.')
  const profile = data as Record<string, unknown>
  for (const field of ['email', 'first_name', 'last_name', 'phone']) {
    if (typeof profile[field] !== 'string') throw new Error('The server returned an invalid profile response.')
  }
  if (profile.role !== undefined && !['customer', 'analyst', 'admin'].includes(profile.role as string)) {
    throw new Error('The server returned an invalid profile response.')
  }
  if (profile.is_verified !== undefined && typeof profile.is_verified !== 'boolean') {
    throw new Error('The server returned an invalid profile response.')
  }
  if (profile.is_oauth !== undefined && typeof profile.is_oauth !== 'boolean') {
    throw new Error('The server returned an invalid profile response.')
  }
  return {
    ...(profile.is_oauth === undefined ? {} : { isOAuth: profile.is_oauth }),
    ...(profile.role === undefined ? {} : { role: profile.role as User['role'] }),
    ...(profile.is_verified === undefined ? {} : { emailVerified: profile.is_verified as boolean }),
    email: profile.email as string,
    firstName: profile.first_name as string,
    lastName: profile.last_name as string,
    phone: profile.phone as string,
  }
}

export async function loadAccountProfile(): Promise<User> {
  if (isMockApi) return (await api.get<{ user: User }>('/auth/me')).data.user
  return accountProfile((await api.get<unknown>(PROFILE_PATH)).data)
}

export async function loginAccount(payload: LoginPayload): Promise<User> {
  if (isMockApi) return (await api.post<{ user: User }>('/auth/login', payload)).data.user
  return accountProfile((await api.post<unknown>('/auth/api/login', payload)).data)
}

export async function registerAccount(payload: RegisterPayload): Promise<User> {
  if (isMockApi) return (await api.post<{ user: User }>('/auth/register', payload)).data.user
  const { email, password, firstName, lastName, phone } = payload
  return accountProfile((await api.post<unknown>('/auth/api/register', {
    email, password, first_name: firstName, ...(lastName === undefined ? {} : { last_name: lastName }), phone,
  })).data)
}

export async function completeOAuthProfile(payload: Pick<RegisterPayload, 'firstName' | 'lastName' | 'phone'>): Promise<string> {
  if (isMockApi) throw new Error('Google registration requires the real backend.')
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

export async function sendAccountVerification(email: string): Promise<void> {
  if (isMockApi) { await api.post('/auth/send-verification', { email }); return }
  messageResponse((await api.post<unknown>('/auth/api/private/email/send-verification')).data)
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

export interface PendingOAuthProfile {
  email: string
  firstName: string
  lastName: string
}

export async function loadOAuthProfile(): Promise<PendingOAuthProfile> {
  if (isMockApi) throw new Error('Google registration requires the real backend.')
  const { data } = await api.get<unknown>('/auth/api/oauth/profile')
  if (!data || typeof data !== 'object') throw new Error('The server returned an invalid Google profile.')
  const profile = data as Record<string, unknown>
  if (['email', 'first_name', 'last_name'].some(field => typeof profile[field] !== 'string')) {
    throw new Error('The server returned an invalid Google profile.')
  }
  return { email: profile.email as string, firstName: profile.first_name as string, lastName: profile.last_name as string }
}
