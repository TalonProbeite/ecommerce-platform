import { create } from 'zustand'
import { billedHours, hasMinimumRentalDuration } from '@/lib/rental'

export const MAX_CART_LINES = 100
export const MAX_ITEM_QUANTITY = 1000

export interface RentalCartItem {
  productId: string
  name: string
  image: string
  storeId: string
  storeName: string
  location: string
  pricePerHourCents: number
  depositPerUnitCents: number
  quantity: number
  rentalStartAt: string
  rentalEndAt: string
}

export type AddRentalItemResult = 'added' | 'merged' | 'replaced'

interface RentalCartState {
  items: RentalCartItem[]
  addItem: (item: RentalCartItem) => AddRentalItemResult
  updateQuantity: (key: string, quantity: number) => void
  removeItem: (key: string) => void
  clear: () => void
}

export function rentalItemKey(item: Pick<RentalCartItem, 'productId' | 'rentalStartAt' | 'rentalEndAt'>): string {
  return `${item.productId}|${item.rentalStartAt}|${item.rentalEndAt}`
}

function clampQuantity(quantity: number): number {
  if (!Number.isFinite(quantity)) return 1
  return Math.min(MAX_ITEM_QUANTITY, Math.max(1, Math.trunc(quantity)))
}

export function cartTotals(items: readonly RentalCartItem[]) {
  let subtotalCents = 0
  let depositCents = 0
  for (const item of items) {
    const hours = billedHours(item.rentalStartAt, item.rentalEndAt)
    subtotalCents += hours * item.pricePerHourCents * item.quantity
    depositCents += item.depositPerUnitCents * item.quantity
  }
  return { subtotalCents, depositCents, totalCents: subtotalCents + depositCents }
}

export function cartQuantity(items: readonly RentalCartItem[]): number {
  return items.reduce((total, item) => total + item.quantity, 0)
}

export const useRentalCartStore = create<RentalCartState>((set, get) => ({
  items: [],
  addItem: (item) => {
    if (!item.productId) throw new Error('A product is required.')
    if (!hasMinimumRentalDuration(item.rentalStartAt, item.rentalEndAt)) {
      throw new Error('A rental must be at least one hour long.')
    }
    const current = get().items
    const nextItem = { ...item, quantity: clampQuantity(item.quantity) }
    const sameStore = current.length === 0 || current[0].storeId === item.storeId
    if (!sameStore) {
      set({ items: [nextItem] })
      return 'replaced'
    }
    const key = rentalItemKey(nextItem)
    const existing = current.find((entry) => rentalItemKey(entry) === key)
    if (existing) {
      set({
        items: current.map((entry) =>
          rentalItemKey(entry) === key
            ? { ...entry, quantity: clampQuantity(entry.quantity + nextItem.quantity) }
            : entry,
        ),
      })
      return 'merged'
    }
    if (current.length >= MAX_CART_LINES) throw new Error(`The cart cannot contain more than ${MAX_CART_LINES} lines.`)
    set({ items: [...current, nextItem] })
    return 'added'
  },
  updateQuantity: (key, quantity) => set((state) => ({
    items: state.items.map((item) => (rentalItemKey(item) === key ? { ...item, quantity: clampQuantity(quantity) } : item)),
  })),
  removeItem: (key) => set((state) => ({ items: state.items.filter((item) => rentalItemKey(item) !== key) })),
  clear: () => set({ items: [] }),
}))
