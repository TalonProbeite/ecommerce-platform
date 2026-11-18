const EUR = new Intl.NumberFormat('en-IE', {
  style: 'currency',
  currency: 'EUR',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

/** Format integer EUR minor units. Never pass a formatted string back into maths. */
export function formatEurCents(cents: number): string {
  if (!Number.isSafeInteger(cents) || cents < 0) throw new Error('Money must be a non-negative integer number of EUR cents.')
  return EUR.format(cents / 100)
}

export function assertNonNegativeCents(value: number, field = 'amount'): number {
  if (!Number.isSafeInteger(value) || value < 0) {
    throw new Error(`${field} must be a non-negative integer number of EUR cents.`)
  }
  return value
}
