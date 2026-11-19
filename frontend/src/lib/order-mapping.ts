import { RENTAL_ORDER_STATUSES, type CancelReason, type ItemOperationResult, type OrderEventPage, type OrderPage, type OverdueRentalPage, type PaymentIntent, type RentalItemStatus, type RentalOrder, type RentalOrderEvent, type RentalOrderItem, type RentalOrderStatus } from '@/types/rental-order'

const ITEM_STATUSES: readonly RentalItemStatus[] = ['reserved', 'issued', 'returned', 'cancelled']
const CANCEL_REASONS: readonly CancelReason[] = ['stock_unavailable', 'payment_timeout', 'customer_request', 'admin_request']

function record(value: unknown, label: string): Record<string, unknown> {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error(`${label} is not an object.`)
  return value as Record<string, unknown>
}

function text(value: unknown, field: string): string {
  if (typeof value !== 'string' || !value) throw new Error(`${field} must be a non-empty string.`)
  return value
}

function nullableText(value: unknown, field: string): string | null {
  if (value === null || value === undefined) return null
  if (typeof value !== 'string') throw new Error(`${field} must be a string or null.`)
  return value
}

function integer(value: unknown, field: string, minimum = 0): number {
  if (!Number.isSafeInteger(value) || (value as number) < minimum) {
    throw new Error(`${field} must be an integer of at least ${minimum}.`)
  }
  return value as number
}

function dateTime(value: unknown, field: string): string {
  const result = text(value, field)
  if (!Number.isFinite(Date.parse(result))) throw new Error(`${field} must be an ISO date-time.`)
  return result
}

function nullableDateTime(value: unknown, field: string): string | null {
  const result = nullableText(value, field)
  if (result !== null && !Number.isFinite(Date.parse(result))) throw new Error(`${field} must be an ISO date-time or null.`)
  return result
}

function status(value: unknown): RentalOrderStatus {
  if (typeof value !== 'string' || !(RENTAL_ORDER_STATUSES as readonly string[]).includes(value)) {
    throw new Error('Order status is unknown.')
  }
  return value as RentalOrderStatus
}

function itemStatus(value: unknown): RentalItemStatus {
  if (typeof value !== 'string' || !(ITEM_STATUSES as readonly string[]).includes(value)) {
    throw new Error('Order item status is unknown.')
  }
  return value as RentalItemStatus
}

function cancelReason(value: unknown): CancelReason | null {
  if (value === null || value === undefined) return null
  if (typeof value !== 'string' || !(CANCEL_REASONS as readonly string[]).includes(value)) {
    throw new Error('Order cancel reason is unknown.')
  }
  return value as CancelReason
}

export function parseOrderItem(value: unknown): RentalOrderItem {
  const item = record(value, 'Order item')
  return {
    id: text(item.id, 'items[].id'),
    productId: text(item.product_id, 'items[].product_id'),
    productName: text(item.product_name, 'items[].product_name'),
    quantity: integer(item.quantity, 'items[].quantity', 1),
    rentalStartAt: dateTime(item.rental_start_at, 'items[].rental_start_at'),
    rentalEndAt: dateTime(item.rental_end_at, 'items[].rental_end_at'),
    billedHours: integer(item.billed_hours, 'items[].billed_hours', 1),
    pricePerHourCents: integer(item.price_per_hour, 'items[].price_per_hour'),
    depositPerUnitCents: integer(item.deposit_per_unit, 'items[].deposit_per_unit'),
    lineTotalCents: integer(item.line_total, 'items[].line_total'),
    depositTotalCents: integer(item.deposit_total, 'items[].deposit_total'),
    status: itemStatus(item.status),
    issuedAt: nullableDateTime(item.issued_at, 'items[].issued_at'),
    returnedAt: nullableDateTime(item.returned_at, 'items[].returned_at'),
  }
}

export function parseRentalOrder(value: unknown): RentalOrder {
  const order = record(value, 'Order')
  if (order.currency !== 'EUR') throw new Error('The Order Service returned an unsupported currency.')
  if (!Array.isArray(order.items)) throw new Error('Order items must be an array.')
  return {
    id: text(order.id, 'id'),
    customerId: text(order.customer_id, 'customer_id'),
    storeId: text(order.store_id, 'store_id'),
    status: status(order.status),
    cancelReason: cancelReason(order.cancel_reason),
    currency: 'EUR',
    subtotalAmount: integer(order.subtotal_amount, 'subtotal_amount'),
    depositAmount: integer(order.deposit_amount, 'deposit_amount'),
    totalAmount: integer(order.total_amount, 'total_amount'),
    version: integer(order.version, 'version', 1),
    items: order.items.map(parseOrderItem),
    createdAt: dateTime(order.created_at, 'created_at'),
    updatedAt: dateTime(order.updated_at, 'updated_at'),
    cancelledAt: nullableDateTime(order.cancelled_at, 'cancelled_at'),
    completedAt: nullableDateTime(order.completed_at, 'completed_at'),
  }
}

export function parseOrderResponse(value: unknown): RentalOrder {
  const body = record(value, 'Order response')
  return parseRentalOrder(body.data)
}

export function parseOrderPage(value: unknown): OrderPage {
  const body = record(value, 'Order list response')
  if (!Array.isArray(body.data)) throw new Error('Order list data must be an array.')
  const meta = record(body.meta, 'Order list meta')
  const nextCursor = meta.next_cursor
  if (nextCursor !== null && nextCursor !== undefined && typeof nextCursor !== 'string') {
    throw new Error('Order list cursor is invalid.')
  }
  return {
    orders: body.data.map(parseRentalOrder),
    nextCursor: typeof nextCursor === 'string' && nextCursor !== '' ? nextCursor : null,
  }
}

export function parsePaymentIntent(value: unknown): PaymentIntent {
  const body = record(value, 'Payment intent response')
  if (body.currency !== 'EUR') throw new Error('The payment intent currency is not EUR.')
  return {
    clientSecret: text(body.client_secret, 'client_secret'),
    status: text(body.status, 'status'),
    amountTotal: integer(body.amount_total, 'amount_total'),
    currency: 'EUR',
  }
}

export function parseItemOperationResponse(value: unknown): ItemOperationResult {
  const order = parseOrderResponse(value)
  const body = record(value, 'Item operation response')
  const skipped = body.skipped_item_ids
  if (skipped !== undefined && (!Array.isArray(skipped) || skipped.some((id) => typeof id !== 'string'))) {
    throw new Error('skipped_item_ids must be an array of strings.')
  }
  return {
    order,
    skippedItemIds: (skipped ?? []) as string[],
  }
}

export function parseRentalOrderEvent(value: unknown): RentalOrderEvent {
  const event = record(value, 'Order event')
  if (!Number.isSafeInteger(event.id)) throw new Error('Order event id must be an integer.')
  return {
    id: event.id as number,
    fromStatus: nullableText(event.from_status, 'events[].from_status'),
    toStatus: text(event.to_status, 'events[].to_status'),
    orderItemId: nullableText(event.order_item_id, 'events[].order_item_id'),
    actorRole: text(event.actor_role, 'events[].actor_role'),
    occurredAt: dateTime(event.occurred_at, 'events[].occurred_at'),
  }
}

export function parseOrderEventPage(value: unknown): OrderEventPage {
  const body = record(value, 'Order event list response')
  if (!Array.isArray(body.data)) throw new Error('Order event list data must be an array.')
  const meta = record(body.meta, 'Order event list meta')
  const nextCursor = meta.next_cursor
  if (nextCursor !== null && nextCursor !== undefined && typeof nextCursor !== 'string') {
    throw new Error('Order event cursor is invalid.')
  }
  return {
    events: body.data.map(parseRentalOrderEvent),
    nextCursor: typeof nextCursor === 'string' && nextCursor !== '' ? nextCursor : null,
  }
}

export function parseOverdueRentalPage(value: unknown): OverdueRentalPage {
  const body = record(value, 'Overdue rental list response')
  if (!Array.isArray(body.data)) throw new Error('Overdue rental list data must be an array.')
  const meta = record(body.meta, 'Overdue rental list meta')
  const nextCursor = meta.next_cursor
  if (nextCursor !== null && nextCursor !== undefined && typeof nextCursor !== 'string') {
    throw new Error('Overdue rental cursor is invalid.')
  }
  return {
    items: body.data.map((entry) => {
      const item = record(entry, 'Overdue rental')
      return {
        orderId: text(item.order_id, 'data[].order_id'),
        itemId: text(item.item_id, 'data[].item_id'),
        productId: text(item.product_id, 'data[].product_id'),
        productName: text(item.product_name, 'data[].product_name'),
        storeId: text(item.store_id, 'data[].store_id'),
        customerId: text(item.customer_id, 'data[].customer_id'),
        quantity: integer(item.quantity, 'data[].quantity', 1),
        rentalEndAt: dateTime(item.rental_end_at, 'data[].rental_end_at'),
        overdueHours: integer(item.overdue_hours, 'data[].overdue_hours'),
      }
    }),
    nextCursor: typeof nextCursor === 'string' && nextCursor !== '' ? nextCursor : null,
  }
}
