import { create } from 'zustand'
import type { CartItem } from '@/types'

interface CartState {
  items: CartItem[]
  addItem: (item: CartItem) => void
  removeItem: (equipmentId: string) => void
  clear: () => void
}

export const useCartStore = create<CartState>((set) => ({
  items: [],
  addItem: (item) =>
    set((state) => ({
      items: [...state.items.filter((existing) => existing.equipmentId !== item.equipmentId), item],
    })),
  removeItem: (equipmentId) =>
    set((state) => ({ items: state.items.filter((item) => item.equipmentId !== equipmentId) })),
  clear: () => set({ items: [] }),
}))
