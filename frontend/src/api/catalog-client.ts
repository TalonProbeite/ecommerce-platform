import { CATALOG_API } from './endpoints'
import { CanceledError, HttpError, isHttpError, type ApiResponse, type RequestConfig } from './http'

export interface CatalogRequestConfig extends RequestConfig {
  headers?: Record<string, string>
  formData?: FormData
}

function buildQuery(params?: RequestConfig['params']): string {
  if (!params) return ''
  const search = new URLSearchParams()
  if (params instanceof URLSearchParams) {
    params.forEach((value, key) => search.append(key, value))
  } else {
    for (const [key, value] of Object.entries(params)) {
      if (value === undefined || value === null || value === '') continue
      search.append(key, String(value))
    }
  }
  const query = search.toString()
  return query ? `?${query}` : ''
}

async function parseBody(response: Response): Promise<unknown> {
  const text = await response.text()
  if (!text) return undefined
  try {
    return JSON.parse(text)
  } catch {
    return text
  }
}

/**
 * Credentialed fetch transport for a service with its own base URL. It shares
 * error types with the main API client but supports bearer tokens plus
 * multipart uploads.
 */
export async function serviceRequest<T = unknown>(
  baseURL: string,
  config: CatalogRequestConfig,
  accessToken?: string,
): Promise<ApiResponse<T>> {
  if (config.signal?.aborted) throw new CanceledError(undefined, config)

  const url = `${baseURL}${config.url ?? ''}${buildQuery(config.params)}`
  const headers: Record<string, string> = { ...config.headers }
  if (accessToken) headers.Authorization = `Bearer ${accessToken}`

  let body: BodyInit | undefined
  if (config.formData) {
    // The browser sets multipart/form-data with the correct boundary.
    body = config.formData
  } else if (config.data !== undefined) {
    headers['Content-Type'] = 'application/json'
    body = typeof config.data === 'string' ? config.data : JSON.stringify(config.data)
  }

  let response: Response
  try {
    response = await fetch(url, {
      method: (config.method ?? 'get').toUpperCase(),
      credentials: 'include',
      headers: Object.keys(headers).length > 0 ? headers : undefined,
      body,
      signal: config.signal,
    })
    const data = (await parseBody(response)) as T
    const result: ApiResponse<T> = { data, status: response.status, headers: response.headers, config }
    if (!response.ok) {
      const message = response.statusText || `HTTP ${response.status}`
      throw new HttpError(message, String(response.status), config, result)
    }
    return result
  } catch (error) {
    if (isHttpError(error)) throw error
    if (config.signal?.aborted || (error instanceof Error && error.name === 'AbortError')) {
      throw new CanceledError(undefined, config)
    }
    throw new HttpError(error instanceof Error ? error.message : 'Network request failed', 'ERR_NETWORK', config)
  }
}

export function catalogRequest<T = unknown>(
  config: CatalogRequestConfig,
  accessToken?: string,
): Promise<ApiResponse<T>> {
  return serviceRequest<T>(CATALOG_API, config, accessToken)
}
