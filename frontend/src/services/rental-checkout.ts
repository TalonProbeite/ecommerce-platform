import { createRentalOrder } from '@/api/order-client'
import { billedHours, isValidRentalRange } from '@/lib/rental'
import { cartTotals, MAX_CART_LINES, MAX_ITEM_QUANTITY, type RentalCartItem } from '@/store/rental-cart-store'
import type { CreateRentalOrderItem, RentalOrder } from '@/types/rental-order'

export function validateRentalCart(items: readonly RentalCartItem[], now = new Date()): string[] {
  const errors: string[] = []
  if (items.length === 0) errors.push('Your cart is empty.')
  if (items.length > MAX_CART_LINES) errors.push(`The cart cannot contain more than ${MAX_CART_LINES} lines.`)
  const stores = new Set(items.map((item) => item.storeId))
  if (stores.size > 1) errors.push('All items in one order must come from the same store.')
  for (const item of items) {
    if (item.quantity < 1 || item.quantity > MAX_ITEM_QUANTITY || !Number.isInteger(item.quantity)) {
      errors.push(`Choose a quantity between 1 and ${MAX_ITEM_QUANTITY} for ${item.name}.`)
    }
    if (!isValidRentalRange(item.rentalStartAt, item.rentalEndAt, now)) {
      errors.push(`Choose a valid future rental period for ${item.name}.`)
    }
    if (!Number.isSafeInteger(item.pricePerHourCents) || item.pricePerHourCents < 0) {
      errors.push(`The price for ${item.name} is invalid.`)
    }
    if (!Number.isSafeInteger(item.depositPerUnitCents) || item.depositPerUnitCents < 0) {
      errors.push(`The deposit for ${item.name} is invalid.`)
    }
  }
  return errors
}

export function toCreateOrderItems(items: readonly RentalCartItem[]): CreateRentalOrderItem[] {
  return items.map((item) => ({
    product_id: item.productId,
    quantity: item.quantity,
    rental_start_at: item.rentalStartAt,
    rental_end_at: item.rentalEndAt,
  }))
}

export async function submitRentalOrder(items: readonly RentalCartItem[], idempotencyKey: string): Promise<RentalOrder> {
  const errors = validateRentalCart(items)
  if (errors.length > 0) throw new Error(errors.join(' '))
  return createRentalOrder(toCreateOrderItems(items), idempotencyKey)
}

export function cartEstimate(items: readonly RentalCartItem[]) {
  return {
    ...cartTotals(items),
    hours: items.reduce((total, item) => total + billedHours(item.rentalStartAt, item.rentalEndAt), 0),
  }
}
