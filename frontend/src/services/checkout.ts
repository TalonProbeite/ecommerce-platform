import { getEquipmentById } from '@/data/equipment'
import { isMockApi } from '@/api/client'
import { calculateTotals, rentalDays, todayISO } from '@/lib/pricing'
import { hasErrors, validatePhone, type FieldErrors } from '@/lib/validation'
import { useAuthStore } from '@/store/auth-store'
import { useCartStore } from '@/store/cart-store'
import { useOrdersStore } from '@/store/orders-store'
import type { CartItem, CheckoutDetails, Order, OrderLine } from '@/types'

export function validateCheckoutDetails(details: CheckoutDetails): FieldErrors<CheckoutDetails> {
  const errors: FieldErrors<CheckoutDetails> = {}
  if (!['pickup', 'delivery'].includes(details.fulfillment)) errors.fulfillment = 'Choose pickup or delivery.'
  if (!details.contactName.trim()) errors.contactName = 'Contact name is required.'
  else if (details.contactName.trim().length > 100) errors.contactName = 'Use 100 characters or fewer.'
  const phoneError = validatePhone(details.phone)
  if (phoneError || !/^\+?[\d\s().-]+$/.test(details.phone.trim()) || details.phone.replace(/\D/g, '').length < 10) {
    errors.phone = phoneError ?? 'Enter a valid phone number with at least 10 digits.'
  }
  if (details.fulfillment === 'delivery') {
    for (const field of ['address', 'city', 'postalCode'] as const) {
      if (!details[field].trim()) errors[field] = 'This field is required for delivery.'
      else if (details[field].trim().length > 200) errors[field] = 'Use 200 characters or fewer.'
    }
  }
  return errors
}

function validDate(value: string) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
  const date = new Date(`${value}T00:00:00Z`)
  return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value
}

export function reviewCart(items: CartItem[]) {
  const lines: OrderLine[] = []
  const errors: string[] = []
  for (const item of items) {
    const equipment = getEquipmentById(item.equipmentId)
    if (!equipment || equipment.status !== 'active') {
      errors.push(`${equipment?.name ?? 'Removed listing'} is unavailable. Remove it to continue.`)
      continue
    }
    if (!validDate(item.startDate) || !validDate(item.endDate) || item.startDate < todayISO() || item.endDate < item.startDate) {
      errors.push(`Choose valid rental dates from today for ${equipment.name}. Remove and reserve it again.`)
      continue
    }
    lines.push({ ...item, days: rentalDays(item.startDate, item.endDate), pricePerDay: equipment.pricePerDay })
  }
  const totals = calculateTotals(lines.reduce((sum, line) => sum + line.days * line.pricePerDay, 0))
  return { lines, errors, ...totals }
}

let inFlight = false

// No HTTP route is assumed until the checkout contract exists.
export async function placeMockOrder(details: CheckoutDetails): Promise<Order> {
  if (!isMockApi) throw new Error('Rental checkout is unavailable until the backend order contract is confirmed. No order has been placed.')
  if (inFlight) throw new Error('An order is already in progress. Please wait.')
  const user = useAuthStore.getState().user
  if (!user) throw new Error('Your session expired. Please sign in again.')
  if (user.role !== 'customer' || user.isBanned) throw new Error('Only active customer accounts can rent equipment.')
  if (hasErrors(validateCheckoutDetails(details))) throw new Error('Check your delivery details before confirming.')
  const items = useCartStore.getState().items.map((item) => ({ ...item }))
  const review = reviewCart(items)
  if (!items.length) throw new Error('Your cart is empty.')
  if (review.errors.length) throw new Error(review.errors.join(' '))
  const normalized = Object.fromEntries(Object.entries(details).map(([key, value]) => [key, value.trim()])) as unknown as CheckoutDetails
  if (normalized.fulfillment === 'pickup') Object.assign(normalized, { address: '', city: '', postalCode: '' })
  inFlight = true
  try {
    await new Promise((resolve) => setTimeout(resolve, 600))
    const currentUser = useAuthStore.getState().user
    if (currentUser?.id !== user.id) throw new Error('Your session changed. Please sign in again.')
    if (currentUser.role !== 'customer' || currentUser.isBanned) throw new Error('Only active customer accounts can rent equipment.')
    if (JSON.stringify(useCartStore.getState().items) !== JSON.stringify(items)) throw new Error('Your cart changed. Review it and try again.')
    const latest = reviewCart(items)
    if (latest.errors.length) throw new Error(latest.errors.join(' '))
    const order: Order = {
      id: `RNT-${crypto.randomUUID()}`, userId: user.id, lines: latest.lines,
      subtotal: latest.subtotal, serviceFee: latest.serviceFee, total: latest.total,
      status: 'confirmed', fulfillment: normalized.fulfillment, checkoutDetails: normalized,
      createdAt: todayISO(),
    }
    useOrdersStore.getState().addOrder(order)
    useCartStore.getState().clear()
    return order
  } finally { inFlight = false }
}
