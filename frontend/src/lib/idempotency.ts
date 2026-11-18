export interface IdempotencyCartLine {
  productId: string
  quantity: number
  rentalStartAt: string
  rentalEndAt: string
}

function canonicalLines(lines: readonly IdempotencyCartLine[]): string {
  const entries = lines.map((line) => JSON.stringify({
    productId: line.productId,
    quantity: line.quantity,
    rentalStartAt: line.rentalStartAt,
    rentalEndAt: line.rentalEndAt,
  }))
  return `[${entries.sort().join(',')}]`
}

/**
 * Stable UUID-shaped key derived from the cart contents and customer id.
 * The server only needs a visible-ASCII idempotency key; the UUID form keeps
 * retries easy to correlate and avoids random regeneration on network retries.
 */
export async function cartIdempotencyKey(customerId: string, lines: readonly IdempotencyCartLine[]): Promise<string> {
  if (!customerId) throw new Error('A customer id is required for checkout.')
  if (lines.length === 0) throw new Error('The cart is empty.')
  const subtle = globalThis.crypto?.subtle
  if (!subtle) throw new Error('This browser cannot create a secure idempotency key.')
  const input = new TextEncoder().encode(`${customerId}\n${canonicalLines(lines)}`)
  const digest = new Uint8Array(await subtle.digest('SHA-256', input))
  const bytes = digest.slice(0, 16)
  bytes[6] = (bytes[6] & 0x0f) | 0x50
  bytes[8] = (bytes[8] & 0x3f) | 0x80
  const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('')
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}
