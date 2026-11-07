import { create } from 'zustand'
import { DEMO_ORDERS } from '@/data/orders'
import type { Order } from '@/types'


interface OrdersState {
  orders: Order[]
  addOrder: (order: Order) => void
  updateOrder: (id: string, changes: Partial<Order>) => void
}

export const useOrdersStore = create<OrdersState>((set) => ({
  orders: DEMO_ORDERS,
  addOrder: (order) => set((state) => ({ orders: [order, ...state.orders] })),
  updateOrder: (id, changes) => set((state) => ({
    orders: state.orders.map((order) => (order.id === id ? { ...order, ...changes } : order)),
  })),
}))
