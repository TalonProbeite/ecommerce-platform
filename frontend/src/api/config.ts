interface ApiEnvironment {
  VITE_API_MODE?: string
  VITE_API_URL?: string
}

export function resolveApiConfig(env: ApiEnvironment) {
  const mode = env.VITE_API_MODE
  const apiUrl = env.VITE_API_URL?.trim() ?? ''

  if (mode !== undefined && mode !== 'mock' && mode !== 'real') {
    throw new Error('VITE_API_MODE must be "mock" or "real".')
  }

  // Preserve the existing selection for plain `pnpm dev` and default builds.
  const isMockApi = mode === undefined ? !apiUrl : mode === 'mock'
  if (!isMockApi) {
    let url: URL
    try {
      url = new URL(apiUrl)
    } catch {
      throw new Error('REAL API mode requires a valid VITE_API_URL. Set it in .env.real.local.')
    }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
      throw new Error('VITE_API_URL must use HTTP or HTTPS.')
    }
    if (url.pathname !== '/' || url.search || url.hash || url.username || url.password) {
      throw new Error('VITE_API_URL must be a backend origin without paths, query parameters, fragments or credentials.')
    }
  }

  return { apiUrl, isMockApi }
}
