import { IS_ORDER_API_ENABLED, ORDER_API } from '@/api/endpoints'
import { serviceRequest } from '@/api/catalog-client'
import { placeMockOrder, reviewCart, validateCheckoutDetails } from '@/services/checkout'
import { loadRentalHistory } from '@/services/rental-history'
import { useAuthStore } from '@/store/auth-store'
import { useCartStore } from '@/store/cart-store'
import { useOrdersStore } from '@/store/orders-store'
import { todayISO } from '@/lib/pricing'
import type { CheckoutDetails, Order, OrderStatus } from '@/types'

// DRAFT: the Order Service does not exist yet. Every route and payload below
// is an assumption to confirm with the Order Service team.
export interface DraftOrderItem {
  product_id: string
  quantity: number
  rental_start_at: string
  rental_end_at: string
}

export interface DraftOrderPayload {
  items: DraftOrderItem[]
}

export interface OrderListParams {
  page?: number
  per_page?: number
  status?: string
}

export type OrderApiStatus =
  | 'created'
  | 'stock_reserved'
  | 'payment_pending'
  | 'paid'
  | 'confirmed'
  | 'shipped'
  | 'completed'
  | 'cancelled'
  | 'payment_failed'

interface DraftOrderResponse {
  id?: string
  status?: string
  [key: string]: unknown
}

function requireOrderApi(): string {
  if (!IS_ORDER_API_ENABLED || !ORDER_API) {
    throw new Error('Order Service is not configured. Set NEXT_PUBLIC_ORDER_BASE_URL to enable it.')
  }
  return ORDER_API
}

function toIsoDate(value: string): string {
  return new Date(`${value}T00:00:00.000Z`).toISOString()
}

// The draft response is not confirmed, so keep the local UI status while
// retaining the raw API status for diagnostics.
// DRAFT: status mapping to confirm with Order Service.
function mapOrderStatus(status: string | undefined): OrderStatus {
  if (status === 'cancelled' || status === 'payment_failed') return 'cancelled'
  if (status === 'completed') return 'returned'
  if (status === 'shipped') return 'active'
  return 'confirmed'
}

export function generateIdempotencyKey(): string {
  return crypto.randomUUID()
}

// DRAFT: contract not confirmed by Order Service
export async function createOrder(details: CheckoutDetails, idempotencyKey = generateIdempotencyKey()): Promise<Order> {
  if (!IS_ORDER_API_ENABLED) return placeMockOrder(details)

  const baseURL = requireOrderApi()
  const user = useAuthStore.getState().user
  if (!user?.id) throw new Error('Your session expired. Please sign in again.')
  if (user.role !== 'customer' || user.isBanned) {
    throw new Error('Only active customer accounts can rent equipment.')
  }
  const validation = validateCheckoutDetails(details)
  if (Object.keys(validation).length > 0) throw new Error('Check your delivery details before confirming.')
  const items = useCartStore.getState().items.map((item) => ({ ...item }))
  const review = reviewCart(items)
  if (!items.length) throw new Error('Your cart is empty.')
  if (review.errors.length) throw new Error(review.errors.join(' '))

  const payload: DraftOrderPayload = {
    items: items.map((item) => ({
      product_id: item.equipmentId,
      quantity: 1,
      rental_start_at: toIsoDate(item.startDate),
      rental_end_at: toIsoDate(item.endDate),
    })),
  }

  const { data } = await serviceRequest<DraftOrderResponse>(baseURL, {
    url: '/orders',
    method: 'post',
    data: payload,
    headers: { 'Idempotency-Key': idempotencyKey },
  })

  if (!data || typeof data.id !== 'string' || !data.id) {
    throw new Error('The Order Service response does not match the draft contract.')
  }

  const normalized = Object.fromEntries(
    Object.entries(details).map(([key, value]) => [key, value.trim()]),
  ) as unknown as CheckoutDetails
  if (normalized.fulfillment === 'pickup') Object.assign(normalized, { address: '', city: '', postalCode: '' })

  const order: Order = {
    id: data.id,
    userId: user.id,
    lines: review.lines,
    subtotal: review.subtotal,
    serviceFee: review.serviceFee,
    total: review.total,
    status: mapOrderStatus(data.status),
    apiStatus: data.status,
    fulfillment: normalized.fulfillment,
    checkoutDetails: normalized,
    createdAt: todayISO(),
  }
  useOrdersStore.getState().addOrder(order)
  useCartStore.getState().clear()
  return order
}

// DRAFT: contract not confirmed by Order Service
export async function listOrders(userId: string, params: OrderListParams = {}, signal?: AbortSignal): Promise<Order[]> {
  if (!IS_ORDER_API_ENABLED) return loadRentalHistory(userId)

  const baseURL = requireOrderApi()
  const { data } = await serviceRequest<{ data?: Order[] } | Order[]>(baseURL, {
    url: '/orders',
    method: 'get',
    params: {
      ...(params.page === undefined ? {} : { page: String(params.page) }),
      ...(params.per_page === undefined ? {} : { per_page: String(params.per_page) }),
      ...(params.status === undefined ? {} : { status: params.status }),
    },
    signal,
  })
  const records = Array.isArray(data) ? data : data.data
  if (!Array.isArray(records)) {
    throw new Error('The Order Service list response does not match the draft contract.')
  }
  return records
}

// DRAFT: contract not confirmed by Order Service
export async function getOrder(orderId: string, signal?: AbortSignal): Promise<Order | undefined> {
  if (!IS_ORDER_API_ENABLED) {
    return useOrdersStore.getState().orders.find((order) => order.id === orderId)
  }

  const baseURL = requireOrderApi()
  const { data } = await serviceRequest<{ data?: Order } | Order>(baseURL, {
    url: `/orders/${encodeURIComponent(orderId)}`,
    method: 'get',
    signal,
  })
  const record = 'data' in data && data.data ? data.data : (data as Order)
  return record
}

// DRAFT: contract not confirmed by Order Service
export async function cancelOrder(orderId: string, signal?: AbortSignal): Promise<void> {
  if (!IS_ORDER_API_ENABLED) {
    const order = useOrdersStore.getState().orders.find((entry) => entry.id === orderId)
    if (!order) throw new Error('Order not found.')
    if (!['created', 'stock_reserved', 'payment_pending', 'paid', 'confirmed'].includes(order.apiStatus ?? order.status)) {
      throw new Error('This order can no longer be cancelled.')
    }
    useOrdersStore.getState().updateOrder(orderId, { status: 'cancelled', apiStatus: 'cancelled' })
    return
  }

  const baseURL = requireOrderApi()
  await serviceRequest(baseURL, {
    url: `/orders/${encodeURIComponent(orderId)}/cancel`,
    method: 'post',
    signal,
  })
}
