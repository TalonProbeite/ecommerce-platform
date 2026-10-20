import { useOrdersStore } from '@/store/orders-store'
import { isMockApi } from '@/api/client'
import type { Order } from '@/types'

export async function loadRentalHistory(userId: string): Promise<Order[]> {
  if (!isMockApi) throw new Error('Rental history is unavailable for live accounts. Please try again when the service is available.')
  await new Promise((resolve) => setTimeout(resolve, 350))
  return useOrdersStore.getState().orders
    .filter((order) => order.userId === userId)
    .sort((a, b) => b.createdAt.localeCompare(a.createdAt))
}
