import type { RentalItemStatus, RentalOrderStatus } from '@/types/rental-order'

export const ORDER_STATUS_LABELS: Record<RentalOrderStatus, string> = {
  created: 'Reserving stock',
  stock_reserved: 'Ready to pay',
  payment_pending: 'Payment in progress',
  paid: 'Paid',
  in_rental: 'In rental',
  completed: 'Completed',
  cancelled: 'Cancelled',
}

export const ITEM_STATUS_LABELS: Record<RentalItemStatus, string> = {
  reserved: 'Reserved',
  issued: 'Issued',
  returned: 'Returned',
  cancelled: 'Cancelled',
}

export function orderStatusTone(status: RentalOrderStatus): 'neutral' | 'progress' | 'success' | 'danger' {
  if (status === 'cancelled') return 'danger'
  if (status === 'completed' || status === 'paid') return 'success'
  if (status === 'in_rental') return 'progress'
  return 'neutral'
}

export function canPay(status: RentalOrderStatus): boolean {
  return status === 'stock_reserved' || status === 'payment_pending'
}

export function canCancel(status: RentalOrderStatus): boolean {
  return status === 'created' || status === 'stock_reserved' || status === 'payment_pending' || status === 'paid'
}

export function cancelReasonMessage(reason: string | null): string | null {
  if (reason === 'stock_unavailable') return 'The equipment is no longer available for these dates.'
  if (reason === 'payment_timeout') return 'The payment window expired and the order was cancelled.'
  if (reason === 'customer_request') return 'This order was cancelled.'
  if (reason === 'admin_request') return 'This order was cancelled by an administrator.'
  return null
}
