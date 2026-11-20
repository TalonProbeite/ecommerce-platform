export interface IdempotencyCartLine {
  productId: string
  quantity: number
  rentalStartAt: string
  rentalEndAt: string
}

export interface CheckoutAttempt {
  fingerprint: string
  key: string | null
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

/** A stable signature used only to detect cart changes between attempts. */
export function cartFingerprint(lines: readonly IdempotencyCartLine[]): string {
  return canonicalLines(lines)
}

/**
 * Create a fresh checkout attempt with a random idempotency key. The key is
 * intentionally unrelated to the cart contents so that a later purchase of
 * the same cart receives a new key instead of replaying the previous order.
 */
export function beginCheckoutAttempt(lines: readonly IdempotencyCartLine[]): CheckoutAttempt {
  return { fingerprint: cartFingerprint(lines), key: lines.length === 0 ? null : createIdempotencyKey() }
}

/** Reset the key whenever the cart changes, keeping a retry key otherwise. */
export function syncCheckoutAttempt(attempt: CheckoutAttempt, lines: readonly IdempotencyCartLine[]): CheckoutAttempt {
  const fingerprint = cartFingerprint(lines)
  if (attempt.fingerprint === fingerprint) return attempt
  return { fingerprint, key: lines.length === 0 ? null : createIdempotencyKey() }
}

/** The order was created successfully; the next checkout needs a new key. */
export function completeCheckoutAttempt(attempt: CheckoutAttempt): CheckoutAttempt {
  return { ...attempt, key: null }
}

/**
 * Keep the key for retryable responses (conflict, validation, rate limit) and
 * for any other failure so a lost response cannot create a duplicate order.
 */
export function retryCheckoutAttempt(attempt: CheckoutAttempt, status?: number): CheckoutAttempt {
  if (status === 409 || status === 422 || status === 429) return attempt
  return attempt.key ? attempt : { ...attempt, key: createIdempotencyKey() }
}

/** Return the key to send with the next request, creating one if needed. */
export function checkoutKey(attempt: CheckoutAttempt): string {
  return attempt.key ?? createIdempotencyKey()
}

function createIdempotencyKey(): string {
  const cryptoApi = globalThis.crypto
  if (!cryptoApi?.randomUUID) throw new Error('This browser cannot create a secure idempotency key.')
  return cryptoApi.randomUUID()
}
