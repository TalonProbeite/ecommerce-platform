import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import path from 'node:path'

function source(relative: string): string {
  return readFileSync(path.resolve(process.cwd(), relative), 'utf8')
}

const paymentFiles = [
  'src/components/rental/stripe-payment-form.tsx',
  'src/pages/order-payment-page.tsx',
  'src/lib/stripe.ts',
]

test('the payment UI never persists the client secret', () => {
  for (const file of paymentFiles) {
    const contents = source(file)
    assert.doesNotMatch(contents, /localStorage|sessionStorage/, file)
    assert.doesNotMatch(contents, /indexedDB/, file)
  }
})

test('the client secret is not placed into the URL', () => {
  for (const file of paymentFiles) {
    const contents = source(file)
    assert.doesNotMatch(contents, /URLSearchParams\([^)]*clientSecret/i, file)
    assert.doesNotMatch(contents, /clientSecret[^;\n]*(location\.(href|search)|history\.(push|replace)State)/i, file)
  }
})

test('the payment UI does not log secrets', () => {
  for (const file of paymentFiles) {
    const contents = source(file)
    assert.doesNotMatch(contents, /console\.(log|debug|info|warn|error)/, file)
  }
})
