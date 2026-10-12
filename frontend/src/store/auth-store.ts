import { create } from 'zustand'
import { isAxiosError } from 'axios'
import { getErrorMessage, isMockApi, onSessionExpired } from '@/api/client'
import { changeAccountPassword, loadAccountProfile, loginAccount, logoutAccount, patchAccountProfile, registerAccount, sendAccountVerification, verifyAccountEmail } from '@/services/auth'
import type { ChangePasswordPayload, LoginPayload, ProfileChanges, RegisterPayload, User, VerifyEmailPayload } from '@/types'

type AuthStatus = 'idle' | 'loading' | 'authenticated' | 'unauthenticated' | 'error'
let generation = 0
let accountPending = false

interface AuthState {
  user: User | null
  status: AuthStatus
  sessionExpired: boolean
  sessionError: string | null
  initialize: () => Promise<void>
  reloadProfile: () => Promise<void>
  login: (payload: LoginPayload) => Promise<User>
  register: (payload: RegisterPayload) => Promise<User>
  verifyEmail: (payload: VerifyEmailPayload) => Promise<void>
  sendVerification: (email: string) => Promise<void>
  logout: () => Promise<void>
  updateProfile: (changes: ProfileChanges) => Promise<void>
  changePassword: (payload: ChangePasswordPayload) => Promise<void>
  dismissSessionExpired: () => void
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  status: 'idle',
  sessionExpired: false,
  sessionError: null,

  initialize: async () => {
    if (get().status !== 'idle') return
    await get().reloadProfile()
  },

  reloadProfile: async () => {
    const requestGeneration = ++generation
    set({ status: 'loading', sessionError: null })
    try {
      const user = await loadAccountProfile()
      if (requestGeneration === generation) set({ user, status: 'authenticated', sessionExpired: false, sessionError: null })
    } catch (error) {
      // Expiry may already have advanced the generation via the interceptor.
      if (requestGeneration !== generation) return
      if (isAxiosError(error) && error.response?.status === 401) {
        set({ user: null, status: 'unauthenticated', sessionError: null })
      } else {
        set({ user: null, status: 'error', sessionError: getErrorMessage(error, 'Could not load your account. Please try again.') })
      }
    }
  },

  login: async (payload) => {
    if (accountPending) throw new Error('An account request is already in progress.')
    accountPending = true
    const requestGeneration = ++generation
    let credentialsAccepted = false
    try {
      const user = await loginAccount(payload)
      credentialsAccepted = true
      if (requestGeneration !== generation) throw new Error('Your session changed. Please sign in again.')
      set({ user, status: 'authenticated', sessionExpired: false, sessionError: null })
      return user
    } catch (error) {
      if (requestGeneration === generation && (credentialsAccepted || !isAxiosError(error))) {
        set({ user: null, status: 'error', sessionError: getErrorMessage(error) })
      }
      throw error
    } finally {
      // A superseded guest probe cannot settle state after an unsuccessful auth request.
      if (requestGeneration === generation && get().status === 'loading' && !get().user) {
        set({ status: 'unauthenticated', sessionError: null })
      }
      accountPending = false
    }
  },

  register: async (payload) => {
    if (accountPending) throw new Error('An account request is already in progress.')
    accountPending = true
    const requestGeneration = ++generation
    try {
      const user = await registerAccount(payload)
      if (requestGeneration !== generation) throw new Error('Your session changed. Please sign in again.')
      set({ user, status: 'authenticated', sessionExpired: false, sessionError: null })
      return user
    } finally {
      // A superseded guest probe cannot settle state after an unsuccessful auth request.
      if (requestGeneration === generation && get().status === 'loading' && !get().user) {
        set({ status: 'unauthenticated', sessionError: null })
      }
      accountPending = false
    }
  },

  verifyEmail: async (payload) => {
    await verifyAccountEmail(payload)
    const user = get().user
    if (isMockApi && user?.email.toLowerCase() === payload.email.trim().toLowerCase()) {
      set({ user: { ...user, emailVerified: true } })
    } else if (!isMockApi && user) await get().reloadProfile()
  },

  sendVerification: async (email) => {
    await sendAccountVerification(email)
  },

  logout: async () => {
    ++generation
    try {
      await logoutAccount()
    } finally {
      ++generation
      set({ user: null, status: 'unauthenticated', sessionExpired: false, sessionError: null })
    }
  },

  updateProfile: async (changes) => {
    const user = get().user
    if (!user) throw new Error('Not authenticated.')
    const requestGeneration = generation
    const updated = await patchAccountProfile(changes, user)
    if (requestGeneration !== generation) return
    if (updated) set({ user: updated })
    else await get().reloadProfile()
  },

  dismissSessionExpired: () => set({ sessionExpired: false }),

  changePassword: async (payload) => {
    if (get().user?.isOAuth) throw new Error('Password is managed by your Google account.')
    await changeAccountPassword(payload)
    ++generation
    set({ user: null, status: 'unauthenticated', sessionExpired: false, sessionError: null })
  },
}))

onSessionExpired(() => {
  const state = useAuthStore.getState()
  const probingGuestSession = state.status === 'loading' && !state.user
  // An older guest probe must not cancel a login/registration that superseded it.
  if (probingGuestSession && accountPending) return
  ++generation
  useAuthStore.setState({ user: null, status: 'unauthenticated', sessionExpired: !probingGuestSession, sessionError: null })
})
