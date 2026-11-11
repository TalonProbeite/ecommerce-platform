import { notifySessionExpired, refreshAuthSession } from '@/api/client'
import { CATALOG_API } from '@/api/endpoints'

export class CatalogError extends Error {
  status: number
  body: unknown

  constructor(status: number, body: unknown) {
    super(`Catalog request failed with status ${status}`)
    this.name = 'CatalogError'
    this.status = status
    this.body = body
  }
}

type CatalogQuery = Record<string, string | number | boolean | null | undefined>

function buildUrl(path: string, query?: CatalogQuery): string {
  const origin = typeof window === 'undefined' ? 'http://localhost' : window.location.origin
  const url = new URL(`${CATALOG_API}${path}`, origin)
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== undefined && value !== null) url.searchParams.set(key, String(value))
    }
  }
  return url.toString()
}

async function readBody(response: Response): Promise<unknown> {
  if (response.status === 204) return null
  const text = await response.text()
  if (!text) return null
  try {
    return JSON.parse(text)
  } catch {
    return text
  }
}

export async function catalogGet<T>(
  path: string,
  query?: CatalogQuery,
  signal?: AbortSignal,
): Promise<T> {
  const response = await fetch(buildUrl(path, query), {
    headers: { Accept: 'application/json' },
    credentials: 'include',
    signal,
  })
  if (!response.ok) throw new CatalogError(response.status, await readBody(response))
  return readBody(response) as Promise<T>
}

export async function catalogAdmin<T>(
  path: string,
  init: RequestInit = {},
  query?: CatalogQuery,
  retried = false,
): Promise<T> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  // Only JSON string bodies get an explicit Content-Type. FormData must let
  // the browser set its multipart boundary.
  if (typeof init.body === 'string' && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const response = await fetch(buildUrl(path, query), {
    ...init,
    method: init.method ? String(init.method).toUpperCase() : init.method,
    headers,
    credentials: 'include',
  })

  if (response.status === 401 && !retried) {
    try {
      await refreshAuthSession()
    } catch {
      throw new CatalogError(401, await readBody(response))
    }
    return catalogAdmin<T>(path, init, query, true)
  }

  if (!response.ok) {
    if (response.status === 401) notifySessionExpired()
    throw new CatalogError(response.status, await readBody(response))
  }
  return readBody(response) as Promise<T>
}

export function catalogErrorMessage(error: unknown): string {
  if (error instanceof CatalogError) {
    const body = error.body as
      | { message?: string; error?: string; errors?: Record<string, string[]> }
      | null
    if (body?.errors) {
      const messages = Object.values(body.errors).flat().filter(Boolean)
      if (messages.length) return messages.join(' ')
    }
    if (typeof body?.message === 'string' && body.message.trim()) return body.message
    if (typeof body?.error === 'string' && body.error.trim()) return body.error
    if (error.status === 401) return 'Your session expired. Please sign in again.'
    if (error.status === 403) return 'You do not have access to this store.'
    if (error.status === 404) return 'The requested item was not found.'
    if (error.status === 422) return 'Please check the form fields and try again.'
  }
  return error instanceof Error ? error.message : 'Something went wrong. Please try again.'
}
