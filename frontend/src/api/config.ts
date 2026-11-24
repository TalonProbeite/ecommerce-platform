interface ApiEnvironment {
  VITE_API_MODE?: string
  VITE_API_URL?: string
  VITE_USE_MOCKS?: string
}

export function resolveApiConfig(env: ApiEnvironment) {
  const mode = env.VITE_API_MODE
  const useMocks = env.VITE_USE_MOCKS?.trim()
  const apiUrl = env.VITE_API_URL?.trim() ?? ''

  if (mode !== undefined && mode !== 'mock' && mode !== 'real') {
    throw new Error('VITE_API_MODE must be "mock" or "real".')
  }
  if (useMocks !== undefined && useMocks !== 'true' && useMocks !== 'false') {
    throw new Error('VITE_USE_MOCKS must be "true" or "false".')
  }

  // The mock adapter is opt-in so production builds never ship it implicitly.
  const isMockApi = useMocks === 'true'

  if (apiUrl) {
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
  } else if (mode === 'real') {
    throw new Error('REAL API mode requires a valid VITE_API_URL. Set it in .env.real.local.')
  }

  return { apiUrl, isMockApi }
}
