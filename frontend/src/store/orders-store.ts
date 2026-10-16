import { create } from 'zustand'
import { DEMO_ORDERS } from '@/data/orders'
import type { Order } from '@/types'


interface OrdersState {
  orders: Order[]
  addOrder: (order: Order) => void
}

export const useOrdersStore = create<OrdersState>((set) => ({
  orders: DEMO_ORDERS,
  addOrder: (order) => set((state) => ({ orders: [order, ...state.orders] })),
}))
