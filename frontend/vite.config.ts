import path from 'node:path'
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { resolveApiConfig } from './src/api/config'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_')
  const apiMode = env.VITE_API_MODE ?? (mode === 'mock' || mode === 'real' ? mode : undefined)
  // Validate before serving the app, rather than failing on a blank page.
  resolveApiConfig({ ...env, VITE_API_MODE: apiMode })
  return {
    // The client must use the same effective mode as startup validation.
    define: apiMode === undefined ? {} : { 'import.meta.env.VITE_API_MODE': JSON.stringify(apiMode) },
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
    },
    server: {
      host: '0.0.0.0',
      port: 3000,
      allowedHosts: ['localhost'],
    },
  }
})
