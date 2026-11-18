import type { RentalOrderStatus } from '@/types/rental-order'

export const ORDER_POLL_INTERVAL_MS = 2_000
export const ORDER_POLL_CEILING_MS = 60_000

const POLLABLE_STATUSES: ReadonlySet<RentalOrderStatus> = new Set([
  'created',
  'stock_reserved',
  'payment_pending',
])

export function isPollableStatus(status: RentalOrderStatus): boolean {
  return POLLABLE_STATUSES.has(status)
}

export function shouldPollOrder(
  status: RentalOrderStatus,
  startedAt: number,
  now = Date.now(),
): boolean {
  if (!isPollableStatus(status)) return false
  return now - startedAt < ORDER_POLL_CEILING_MS
}
