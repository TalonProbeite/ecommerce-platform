import { execFileSync, spawnSync } from 'node:child_process'
import { mkdirSync, rmSync, writeFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const out = path.join(root, '.test-dist')

rmSync(out, { recursive: true, force: true })
execFileSync(path.join(root, 'node_modules', '.bin', 'tsc'), ['-p', 'tsconfig.test.json'], {
  cwd: root,
  stdio: 'inherit',
})
mkdirSync(out, { recursive: true })
writeFileSync(path.join(out, 'package.json'), '{"type":"commonjs"}\n')

const result = spawnSync(process.execPath, [
  '-r',
  path.join(root, 'tests', 'setup.cjs'),
  '--test',
  path.join(out, 'tests', 'unit'),
], { cwd: root, stdio: 'inherit' })

process.exit(result.status ?? 1)
