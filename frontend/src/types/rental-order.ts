export const RENTAL_ORDER_STATUSES = [
  'created',
  'stock_reserved',
  'payment_pending',
  'paid',
  'in_rental',
  'completed',
  'cancelled',
] as const

export type RentalOrderStatus = (typeof RENTAL_ORDER_STATUSES)[number]

export type RentalItemStatus = 'reserved' | 'issued' | 'returned' | 'cancelled'

export type CancelReason =
  | 'stock_unavailable'
  | 'payment_timeout'
  | 'customer_request'
  | 'admin_request'

export interface RentalOrderItem {
  id: string
  productId: string
  productName: string
  quantity: number
  rentalStartAt: string
  rentalEndAt: string
  billedHours: number
  pricePerHourCents: number
  depositPerUnitCents: number
  lineTotalCents: number
  depositTotalCents: number
  status: RentalItemStatus
  issuedAt: string | null
  issuedBy: string | null
  returnedAt: string | null
  returnedBy: string | null
}

export interface RentalOrder {
  id: string
  customerId: string
  storeId: string
  status: RentalOrderStatus
  cancelReason: CancelReason | null
  currency: 'EUR'
  subtotalAmount: number
  depositAmount: number
  totalAmount: number
  version: number
  items: RentalOrderItem[]
  createdAt: string
  updatedAt: string
  cancelledAt: string | null
  completedAt: string | null
}

export interface CreateRentalOrderItem {
  product_id: string
  quantity: number
  rental_start_at: string
  rental_end_at: string
}

export interface PaymentIntent {
  clientSecret: string
  status: string
  amountTotal: number
  currency: 'EUR'
}

export interface OrderPage {
  orders: RentalOrder[]
  nextCursor: string | null
}

export interface RentalOrderEvent {
  id: number
  fromStatus: string | null
  toStatus: string
  orderItemId: string | null
  actorRole: string
  occurredAt: string
}

export interface OrderEventPage {
  events: RentalOrderEvent[]
  nextCursor: string | null
}

export interface ItemOperationResult {
  order: RentalOrder
  skippedItemIds: string[]
}

export interface OverdueRental {
  orderId: string
  itemId: string
  productId: string
  productName: string
  storeId: string
  customerId: string
  quantity: number
  rentalEndAt: string
  overdueHours: number
}

export interface OverdueRentalPage {
  items: OverdueRental[]
  nextCursor: string | null
}
