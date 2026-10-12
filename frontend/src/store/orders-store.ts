import { create } from 'zustand'
import { addDaysISO, todayISO } from '@/lib/pricing'
import type { Order } from '@/types'

const today = todayISO()

const SEED_ORDERS: Order[] = [
  {
    id: 'RNT-10482',
    userId: 'u-user',
    lines: [{ equipmentId: 'sony-a7-iv', startDate: addDaysISO(today, 3), endDate: addDaysISO(today, 5), days: 3, pricePerDay: 65 }],
    subtotal: 195,
    serviceFee: 15.6,
    total: 210.6,
    status: 'confirmed',
    fulfillment: 'pickup',
    createdAt: addDaysISO(today, -1),
  },
  {
    id: 'RNT-10377',
    userId: 'u-user',
    lines: [{ equipmentId: 'urban-ebike', startDate: addDaysISO(today, -12), endDate: addDaysISO(today, -10), days: 3, pricePerDay: 35 }],
    subtotal: 105,
    serviceFee: 8.4,
    total: 113.4,
    status: 'returned',
    fulfillment: 'delivery',
    createdAt: addDaysISO(today, -15),
  },
  {
    id: 'RNT-10201',
    userId: 'u-user',
    lines: [{ equipmentId: 'family-tent', startDate: addDaysISO(today, -40), endDate: addDaysISO(today, -37), days: 4, pricePerDay: 22 }],
    subtotal: 88,
    serviceFee: 7.04,
    total: 95.04,
    status: 'returned',
    fulfillment: 'pickup',
    createdAt: addDaysISO(today, -45),
  },
]

interface OrdersState {
  orders: Order[]
  addOrder: (order: Order) => void
}

export const useOrdersStore = create<OrdersState>((set) => ({
  orders: SEED_ORDERS,
  addOrder: (order) => set((state) => ({ orders: [order, ...state.orders] })),
}))
