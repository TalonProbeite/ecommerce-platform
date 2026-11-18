import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createServer } from 'vite'

async function context() {
  const server = await createServer({
    configFile: false, envDir: false,
    define: { 'import.meta.env.VITE_API_MODE': '"real"', 'import.meta.env.VITE_API_URL': '"https://example.invalid"' },
    resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
    server: { middlewareMode: true, watch: null, ws: false }, appType: 'custom',
  })
  const { submitRentalOrder } = await server.ssrLoadModule('/src/services/rental-checkout.ts')
  const { orderErrorMessage } = await server.ssrLoadModule('/src/lib/order-error.ts')
  return { server, submitRentalOrder, orderErrorMessage }
}

const item = {
  productId: '33333333-3333-4333-8333-333333333333',
  name: 'Drill', image: '/images/drill.png',
  storeId: '22222222-2222-4222-8222-222222222222', storeName: 'ToolShed', location: 'Chicago',
  pricePerHourCents: 500, depositPerUnitCents: 1000, quantity: 1,
  rentalStartAt: '2099-11-21T09:00:00.000Z', rentalEndAt: '2099-11-21T11:00:00.000Z',
}

const order = {
  id: '77777777-7777-4777-8777-777777777771',
  customer_id: '11111111-1111-4111-8111-111111111111',
  store_id: '22222222-2222-4222-8222-222222222222',
  status: 'created', cancel_reason: null, currency: 'EUR',
  subtotal_amount: 1000, deposit_amount: 1000, total_amount: 2000, version: 1,
  items: [{
    id: '01930000-0000-7000-8000-000000000001', product_id: item.productId, product_name: item.name,
    quantity: 1, rental_start_at: item.rentalStartAt, rental_end_at: item.rentalEndAt,
    billed_hours: 2, price_per_hour: 500, deposit_per_unit: 1000,
    line_total: 1000, deposit_total: 1000, status: 'reserved',
    issued_at: null, issued_by: null, returned_at: null, returned_by: null,
  }],
  created_at: '2099-11-20T10:00:00.000000+00:00', updated_at: '2099-11-20T10:00:00.000000+00:00',
  cancelled_at: null, completed_at: null,
}

async function submitWith(ctx, status, body) {
  const original = globalThis.fetch
  let capturedKey = ''
  globalThis.fetch = async (_input, init) => {
    capturedKey = new Headers(init?.headers).get('Idempotency-Key') ?? ''
    return new Response(JSON.stringify(body), {
      status,
      headers: { 'Content-Type': status >= 400 ? 'application/problem+json' : 'application/json' },
    })
  }
  try {
    return { result: await ctx.submitRentalOrder('11111111-1111-4111-8111-111111111111', [item]), capturedKey }
  } finally {
    globalThis.fetch = original
  }
}

test('checkout 201 leads to the order and sends a UUID Idempotency-Key', async () => {
  const ctx = await context()
  try {
    const { result, capturedKey } = await submitWith(ctx, 201, { data: order })
    assert.equal(result.id, order.id)
    assert.match(capturedKey, /^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/)
  } finally { await ctx.server.close() }
})

test('checkout 200 accepts an idempotent replay as the same order', async () => {
  const ctx = await context()
  try {
    const { result } = await submitWith(ctx, 200, { data: order })
    assert.equal(result.id, order.id)
  } finally { await ctx.server.close() }
})

test('checkout 409 reports an in-flight request', async () => {
  const ctx = await context()
  try {
    await assert.rejects(() => submitWith(ctx, 409, { title: 'Conflict', status: 409 }),
      error => /already being processed/i.test(ctx.orderErrorMessage(error)))
  } finally { await ctx.server.close() }
})

test('checkout 422 exposes the server reason', async () => {
  const ctx = await context()
  try {
    await assert.rejects(() => submitWith(ctx, 422, {
      title: 'Unprocessable Entity', status: 422, detail: 'Mixed-store order is not allowed.',
    }), error => ctx.orderErrorMessage(error) === 'Mixed-store order is not allowed.')
  } finally { await ctx.server.close() }
})

test('checkout 429 asks the customer to wait', async () => {
  const ctx = await context()
  try {
    await assert.rejects(() => submitWith(ctx, 429, { title: 'Too Many Requests', status: 429 }),
      error => /wait/i.test(ctx.orderErrorMessage(error)))
  } finally { await ctx.server.close() }
})
