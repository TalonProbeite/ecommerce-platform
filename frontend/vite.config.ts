import path from 'node:path'
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { resolveApiConfig } from './src/api/config'

export default defineConfig(({ mode }) => {
  // Validate before serving the app, rather than failing on a blank page.
  resolveApiConfig(loadEnv(mode, process.cwd(), 'VITE_'))
  return {
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
