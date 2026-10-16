import { fetchAdapter, isHttpError, CanceledError, type RequestConfig, type Adapter, type ApiResponse } from './http'
import { mockAdapter } from './mock-adapter'
import { resolveApiConfig } from './config'
import { messageResponse } from './response'

const { apiUrl, isMockApi } = resolveApiConfig({
  VITE_API_MODE: import.meta.env.VITE_API_MODE,
  VITE_API_URL: import.meta.env.VITE_API_URL,
})

export { isMockApi }

// The adapter is shared by REAL fetch requests and explicit MOCK fixtures.
const defaults = {
  baseURL: apiUrl,
  credentials: 'include' as const,
  adapter: (isMockApi ? mockAdapter : (config => fetchAdapter(config, apiUrl))) as Adapter,
}

async function request<T = any>(config: RequestConfig): Promise<ApiResponse<T>> {
  if (config.signal?.aborted) throw new CanceledError(undefined, config)
  config._refreshVersion ??= refreshVersion
  config.credentials = 'include'
  if (config.data !== undefined && typeof config.data !== 'string') config.data = JSON.stringify(config.data)
  try {
    return await defaults.adapter(config)
  } catch (error) {
    return handleError(error)
  }
}

export const api = {
  defaults,
  get: <T = any>(url: string, options: RequestConfig = {}) => request<T>({ ...options, url, method: 'get' }),
  post: <T = any>(url: string, data?: unknown, options: RequestConfig = {}) => request<T>({ ...options, url, data, method: 'post' }),
  put: <T = any>(url: string, data?: unknown, options: RequestConfig = {}) => request<T>({ ...options, url, data, method: 'put' }),
  patch: <T = any>(url: string, data?: unknown, options: RequestConfig = {}) => request<T>({ ...options, url, data, method: 'patch' }),
}

const REFRESH_PATH = '/auth/api/refresh'
const MOCK_PROTECTED_PATHS = ['/auth/me', '/auth/verify-email', '/auth/send-verification']

type RetriableConfig = RequestConfig & { _retry?: boolean; _refreshVersion?: number }

let refreshPromise: Promise<unknown> | null = null
let sessionExpiredHandler: (() => void) | null = null
let refreshVersion = 0

export function onSessionExpired(handler: () => void) {
  sessionExpiredHandler = handler
}

async function handleError(error: unknown): Promise<ApiResponse> {
  if (!isHttpError(error)) throw error
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
    if (isHttpError(refreshError) && refreshError.response?.status === 401) sessionExpiredHandler?.()
    throw refreshError
  }
  try {
    return await request(original)
  } catch (retryError) {
    if (isHttpError(retryError) && retryError.response?.status === 401) sessionExpiredHandler?.()
    throw retryError
  }
}

export const GOOGLE_OAUTH_URL = new URL('/auth/api/oauth/google', isMockApi ? 'https://localhost' : apiUrl).href

export function getErrorCode(error: unknown): string | undefined {
  if (!isHttpError(error)) return undefined
  return (error.response?.data as { code?: string } | undefined)?.code
}

export function getErrorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (isHttpError(error)) {
    const data = error.response?.data as { error?: string; message?: string } | undefined
    if (typeof data?.error === 'string' && data.error.trim()) return data.error
    if (typeof data?.message === 'string' && data.message.trim()) return data.message
    return fallback
  }
  return error instanceof Error ? error.message : fallback
}
