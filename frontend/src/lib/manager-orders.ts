import { canCancel } from '@/lib/order-status'
import type { RentalOrder, RentalOrderItem } from '@/types/rental-order'

export type ManagerItemAction = 'issue' | 'return'

/** Items a manager can hand out: reserved, and only while the order is paid or in rental. */
export function issuableItems(order: RentalOrder): RentalOrderItem[] {
  if (!canIssueOrder(order)) return []
  return order.items.filter((item) => item.status === 'reserved')
}

/** Items a manager can accept back: issued, in rental, and after the rental has started. */
export function returnableItems(order: RentalOrder, now: Date = new Date()): RentalOrderItem[] {
  if (order.status !== 'in_rental') return []
  return order.items.filter((item) => item.status === 'issued' && rentalHasStarted(item, now))
}

export function canIssueOrder(order: RentalOrder): boolean {
  return (order.status === 'paid' || order.status === 'in_rental')
    && order.items.some((item) => item.status === 'reserved')
}

export function canReturnOrder(order: RentalOrder, now: Date = new Date()): boolean {
  return returnableItems(order, now).length > 0
}

/** Returns are accepted only after the rental period has started. */
export function rentalHasStarted(item: RentalOrderItem, now: Date = new Date()): boolean {
  const start = Date.parse(item.rentalStartAt)
  return Number.isFinite(start) && start <= now.getTime()
}

export function canCancelOrder(order: RentalOrder): boolean {
  return canCancel(order.status)
}

/** Deposit that is refunded when the selected items are returned. */
export function returnedDepositCents(items: RentalOrderItem[]): number {
  return items.reduce((total, item) => total + item.depositTotalCents, 0)
}

export function toggleSelection(selected: ReadonlySet<string>, itemId: string): Set<string> {
  const next = new Set(selected)
  if (next.has(itemId)) next.delete(itemId)
  else next.add(itemId)
  return next
}

/** Keeps only ids that are currently eligible, so stale selections never submit. */
export function selectedEligibleIds(
  selected: ReadonlySet<string>,
  eligible: RentalOrderItem[],
): string[] {
  const allowed = new Set(eligible.map((item) => item.id))
  return [...selected].filter((id) => allowed.has(id))
}

export function itemStatusLabel(status: RentalOrderItem['status']): string {
  return status.charAt(0).toUpperCase() + status.slice(1)
}
