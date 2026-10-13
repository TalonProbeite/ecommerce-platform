import axios, { isAxiosError, type InternalAxiosRequestConfig } from 'axios'
import { mockAdapter } from './mock-adapter'
import { resolveApiConfig } from './config'
import { messageResponse } from './response'

const { apiUrl, isMockApi } = resolveApiConfig({
  VITE_API_MODE: import.meta.env.VITE_API_MODE,
  VITE_API_URL: import.meta.env.VITE_API_URL,
})

export { isMockApi }

export const api = axios.create({
  baseURL: apiUrl,
  withCredentials: true,
  adapter: isMockApi ? mockAdapter : undefined,
})

const REFRESH_PATH = '/auth/api/refresh'
const MOCK_PROTECTED_PATHS = ['/auth/me', '/auth/verify-email', '/auth/send-verification']

type RetriableConfig = InternalAxiosRequestConfig & { _retry?: boolean; _refreshVersion?: number }

let refreshPromise: Promise<unknown> | null = null
let sessionExpiredHandler: (() => void) | null = null
let refreshVersion = 0

api.interceptors.request.use((config: RetriableConfig) => {
  config._refreshVersion ??= refreshVersion
  return config
})

export function onSessionExpired(handler: () => void) {
  sessionExpiredHandler = handler
}

api.interceptors.response.use(
  (response) => response,
  async (error: unknown) => {
    if (!isAxiosError(error)) throw error
    const original = error.config as RetriableConfig | undefined
    const shouldRefresh =
      error.response?.status === 401 &&
      original &&
      !original._retry &&
      (/^\/(auth|notification)\/api\/(private|admin)\//.test(original.url ?? '') ||
        (isMockApi && MOCK_PROTECTED_PATHS.includes(original.url ?? '')))

    if (!shouldRefresh) throw error

    original._retry = true
    try {
      // A slow 401 may arrive after a parallel request has already refreshed.
      if (original._refreshVersion === refreshVersion) {
        refreshPromise ??= api.post(REFRESH_PATH).then(response => {
          if (!isMockApi) messageResponse(response.data)
          refreshVersion += 1
        }).finally(() => {
          refreshPromise = null
        })
        await refreshPromise
      }
    } catch (refreshError) {
      if (isAxiosError(refreshError) && refreshError.response?.status === 401) sessionExpiredHandler?.()
      throw refreshError
    }
    try {
      return await api(original)
    } catch (retryError) {
      if (isAxiosError(retryError) && retryError.response?.status === 401) sessionExpiredHandler?.()
      throw retryError
    }
  },
)

export const GOOGLE_OAUTH_URL = new URL('/auth/api/oauth/google', isMockApi ? 'https://localhost' : apiUrl).href

export function getErrorCode(error: unknown): string | undefined {
  if (!isAxiosError(error)) return undefined
  return (error.response?.data as { code?: string } | undefined)?.code
}

export function getErrorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (isAxiosError(error)) {
    const data = error.response?.data as { error?: string; message?: string } | undefined
    if (typeof data?.error === 'string' && data.error.trim()) return data.error
    if (typeof data?.message === 'string' && data.message.trim()) return data.message
    return fallback
  }
  return error instanceof Error ? error.message : fallback
}
