import { createServer } from 'vite'

// Load TypeScript through Vite so these fixtures also work on Node without
// built-in type stripping. No env files, sockets, or real API calls are needed.
export async function loadHttpErrors() {
  const server = await createServer({
    configFile: false, envDir: false,
    optimizeDeps: { noDiscovery: true, include: [] },
    server: { middlewareMode: true, watch: null, ws: false }, appType: 'custom',
  })
  try {
    return await server.ssrLoadModule('/src/api/http.ts')
  } finally {
    await server.close()
  }
}
