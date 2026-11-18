export type ApiMode = 'global' | 'local'

interface EndpointEnvironment {
  NEXT_PUBLIC_API_MODE?: string
  NEXT_PUBLIC_CATALOG_BASE_URL?: string
  NEXT_PUBLIC_ORDER_BASE_URL?: string
  NEXT_PUBLIC_ENABLE_REAL_CHECKOUT?: string
  VITE_API_MODE?: string
  VITE_API_URL?: string
  VITE_ENABLE_REAL_CHECKOUT?: string
}

// The global nginx gateway strips the /catalog prefix and terminates TLS.
const DEFAULT_GLOBAL_ORIGIN = 'https://localhost'

// The local catalog service exposes its nginx directly on this port.
const LOCAL_CATALOG_BASE = 'https://localhost:8444'

const API_PREFIX = '/api/v1'

function trimTrailingSlash(value: string): string {
  return value.replace(/\/+$/, '')
}

export function normalizeBaseUrl(value: string | undefined): string | undefined {
  const trimmed = value?.trim()
  if (!trimmed) return undefined
  let url: URL
  try {
    url = new URL(trimmed)
  } catch {
    throw new Error('API base URL must be an absolute HTTP or HTTPS URL.')
  }
  if (url.protocol !== 'http:' && url.protocol !== 'https:') {
    throw new Error('API base URL must use HTTP or HTTPS.')
  }
  return trimTrailingSlash(trimmed)
}

export function resolveApiMode(env: EndpointEnvironment): ApiMode {
  const explicit = env.NEXT_PUBLIC_API_MODE?.trim()
  const viteMode = env.VITE_API_MODE?.trim()
  const raw = explicit || (viteMode === 'global' || viteMode === 'local' ? viteMode : '')
  if (!raw) return 'global'
  if (raw !== 'global' && raw !== 'local') {
    throw new Error('NEXT_PUBLIC_API_MODE must be "global" or "local".')
  }
  return raw
}

export interface ApiEndpoints {
  mode: ApiMode
  catalogBase: string
  orderBase?: string
  catalogApi: string
  orderApi?: string
  orderApiEnabled: boolean
}

function resolveGlobalBase(env: EndpointEnvironment, prefix: string): string {
  const apiOrigin = normalizeBaseUrl(env.VITE_API_URL)

  if (apiOrigin) return `${apiOrigin}${prefix}`

  if (env.VITE_API_MODE?.trim() === 'real') {
    throw new Error(`REAL API mode requires VITE_API_URL so the ${prefix} gateway origin can be resolved.`)
  }

  return `${DEFAULT_GLOBAL_ORIGIN}${prefix}`
}

export function resolveApiEndpoints(env: EndpointEnvironment): ApiEndpoints {
  const mode = resolveApiMode(env)
  const catalogOverride = normalizeBaseUrl(env.NEXT_PUBLIC_CATALOG_BASE_URL)
  const orderOverride = normalizeBaseUrl(env.NEXT_PUBLIC_ORDER_BASE_URL)

  const catalogBase = catalogOverride ?? (mode === 'global' ? resolveGlobalBase(env, '/catalog') : LOCAL_CATALOG_BASE)
  // The global gateway exposes the Order service under /order. A local order
  // service has no stable default port, so it must be configured explicitly.
  const orderBase = orderOverride ?? (mode === 'global' ? resolveGlobalBase(env, '/order') : undefined)

  return {
    mode,
    catalogBase,
    orderBase,
    catalogApi: `${catalogBase}${API_PREFIX}`,
    orderApi: orderBase ? `${orderBase}${API_PREFIX}` : undefined,
    orderApiEnabled: orderBase !== undefined,
  }
}

const endpoints = resolveApiEndpoints(import.meta.env as unknown as EndpointEnvironment)

export const API_MODE = endpoints.mode
export const CATALOG_BASE_URL = endpoints.catalogBase
export const CATALOG_API = endpoints.catalogApi
export const ORDER_BASE_URL = endpoints.orderBase
export const ORDER_API = endpoints.orderApi
export const IS_ORDER_API_ENABLED = endpoints.orderApiEnabled

// Live catalog data is used for REAL auth mode or whenever a new-style API
// mode override is present. Plain `pnpm dev` keeps the existing mock fixtures.
export const IS_CATALOG_LIVE =
  import.meta.env.VITE_API_MODE === 'real' || Boolean(import.meta.env.NEXT_PUBLIC_API_MODE?.trim())

// Real checkout follows the real API transport unless it is explicitly
// disabled. Mock mode still uses the local fixtures.
const RAW_API_MODE = import.meta.env.VITE_API_MODE?.trim()
const MOCK_MODE =
  RAW_API_MODE === 'mock' ||
  (RAW_API_MODE === undefined && !import.meta.env.VITE_API_URL)

export const IS_ORDER_LIVE = !MOCK_MODE && IS_ORDER_API_ENABLED

export const ENABLE_REAL_CHECKOUT =
  IS_ORDER_LIVE &&
  import.meta.env.VITE_ENABLE_REAL_CHECKOUT !== 'false' &&
  import.meta.env.NEXT_PUBLIC_ENABLE_REAL_CHECKOUT !== 'false'

export const STRIPE_PUBLISHABLE_KEY =
  import.meta.env.NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY?.trim() ?? ''
