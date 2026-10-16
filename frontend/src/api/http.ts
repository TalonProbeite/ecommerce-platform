/** Shared request/response boundary for the fetch transport and local fixtures. */
export interface RequestConfig {
  url?: string
  method?: string
  data?: any
  params?: URLSearchParams | Record<string, string>
  signal?: AbortSignal
  credentials?: RequestCredentials
  _retry?: boolean
  _refreshVersion?: number
}

export interface ApiResponse<T = any> {
  data: T
  status: number
  headers?: unknown
  config?: RequestConfig
}
export type Adapter = (config: RequestConfig) => Promise<ApiResponse>

export class HttpError extends Error {
  readonly isHttpError = true
  code?: string
  config?: RequestConfig
  response?: ApiResponse
  constructor(message: string, code?: string, config?: RequestConfig, response?: ApiResponse) {
    super(message)
    this.name = 'HttpError'
    this.code = code
    this.config = config
    this.response = response
  }
}

export class CanceledError extends HttpError {
  constructor(message = 'Request cancelled', config?: RequestConfig) {
    super(message, 'ERR_CANCELED', config)
    this.name = 'AbortError'
  }
}

export function isHttpError(error: unknown): error is HttpError {
  return error instanceof Error && 'isHttpError' in error && error.isHttpError === true
}

export async function fetchAdapter(config: RequestConfig, baseURL: string): Promise<ApiResponse> {
  if (config.signal?.aborted) throw new CanceledError(undefined, config)
  const url = new URL(config.url ?? '', baseURL)
  const params = new URLSearchParams(config.params)
  params.forEach((value, key) => url.searchParams.append(key, value))
  let response: Response
  try {
    response = await fetch(url, {
      method: (config.method ?? 'get').toUpperCase(),
      credentials: 'include',
      headers: config.data === undefined ? undefined : { 'Content-Type': 'application/json' },
      body: config.data,
      signal: config.signal,
    })
    const body = await response.text()
    let data: unknown = body
    if (body) {
      try { data = JSON.parse(body) } catch { /* Preserve non-JSON errors for the fallback message. */ }
    }
    const result = { data, status: response.status, headers: response.headers, config }
    if (!response.ok) throw new HttpError(`HTTP ${response.status}`, String(response.status), config, result)
    return result
  } catch (error) {
    if (isHttpError(error)) throw error
    if (config.signal?.aborted || (error instanceof Error && error.name === 'AbortError')) throw new CanceledError(undefined, config)
    throw new HttpError(error instanceof Error ? error.message : 'Network request failed', 'ERR_NETWORK', config)
  }
}
