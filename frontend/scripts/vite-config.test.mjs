import assert from 'node:assert/strict'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { loadConfigFromFile } from 'vite'

const configFile = fileURLToPath(new URL('../vite.config.ts', import.meta.url))

// Use an empty env directory so ignored local files cannot hide missing config.
async function loadConfig(mode, overrides = {}) {
  const directory = await mkdtemp(path.join(tmpdir(), 'rentify-vite-config-'))
  const previousDirectory = process.cwd()
  const previousEnv = Object.fromEntries(
    ['VITE_API_MODE', 'VITE_API_URL'].map(key => [key, process.env[key]])
  )
  try {
    process.chdir(directory)
    for (const key of Object.keys(previousEnv)) delete process.env[key]
    Object.assign(process.env, overrides)
    const result = await loadConfigFromFile({ mode, command: 'serve' }, configFile, directory, 'silent')
    assert.ok(result)
    return result.config
  } finally {
    process.chdir(previousDirectory)
    for (const [key, value] of Object.entries(previousEnv)) {
      if (value === undefined) delete process.env[key]
      else process.env[key] = value
    }
    await rm(directory, { recursive: true, force: true })
  }
}

test('real mode without env files rejects a missing API URL', async () => {
  await assert.rejects(loadConfig('real'), /VITE_API_URL/)
})

test('real mode publishes its default mode to the client bundle', async () => {
  const config = await loadConfig('real', { VITE_API_URL: 'https://api.example.test' })
  assert.equal(config.define['import.meta.env.VITE_API_MODE'], '"real"')
})

test('mock mode stays mock even with an API URL', async () => {
  const config = await loadConfig('mock', { VITE_API_URL: 'https://api.example.test' })
  assert.equal(config.define['import.meta.env.VITE_API_MODE'], '"mock"')
})

test('explicit mock override takes precedence over real Vite mode', async () => {
  const config = await loadConfig('real', { VITE_API_MODE: 'mock' })
  assert.equal(config.define['import.meta.env.VITE_API_MODE'], '"mock"')
})

test('explicit real override requires an API URL even in mock Vite mode', async () => {
  await assert.rejects(loadConfig('mock', { VITE_API_MODE: 'real' }), /VITE_API_URL/)
})

test('explicit invalid API mode is rejected rather than replaced', async () => {
  await assert.rejects(loadConfig('real', { VITE_API_MODE: 'typo' }), /VITE_API_MODE/)
})

test('plain development retains automatic adapter selection', async () => {
  for (const overrides of [{}, { VITE_API_URL: 'https://api.example.test' }]) {
    const config = await loadConfig('development', overrides)
    assert.equal(config.define?.['import.meta.env.VITE_API_MODE'], undefined)
  }
})
