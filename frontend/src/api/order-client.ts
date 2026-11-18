import { serviceRequest } from '@/api/catalog-client'
import { ORDER_API } from '@/api/endpoints'
import { HttpError } from '@/api/http'
import { orderErrorMessage, type ProblemDetails } from '@/lib/order-error'
import { parseOrderPage, parseOrderResponse, parsePaymentIntent } from '@/lib/order-mapping'
import type { CreateRentalOrderItem, PaymentIntent, RentalOrder } from '@/types/rental-order'

export function requireOrderApi(): string {
  if (!ORDER_API) throw new Error('The Order Service is not configured for this environment.')
  return ORDER_API
}

export async function createRentalOrder(
  items: CreateRentalOrderItem[],
  idempotencyKey: string,
  signal?: AbortSignal,
): Promise<RentalOrder> {
  const { data } = await serviceRequest<unknown>(requireOrderApi(), {
    url: '/orders',
    method: 'post',
    data: { items },
    headers: { 'Idempotency-Key': idempotencyKey },
    signal,
  })
  return parseOrderResponse(data)
}

export async function listRentalOrders(
  params: { cursor?: string; limit?: number; status?: string } = {},
  signal?: AbortSignal,
): Promise<ReturnType<typeof parseOrderPage>> {
  const query: Record<string, string> = {}
  if (params.cursor) query.cursor = params.cursor
  if (params.limit) query.limit = String(params.limit)
  if (params.status) query.status = params.status
  const { data } = await serviceRequest<unknown>(requireOrderApi(), {
    url: '/orders',
    method: 'get',
    params: query,
    signal,
  })
  return parseOrderPage(data)
}

export async function getRentalOrder(orderId: string, signal?: AbortSignal): Promise<RentalOrder> {
  const { data } = await serviceRequest<unknown>(requireOrderApi(), {
    url: `/orders/${encodeURIComponent(orderId)}`,
    method: 'get',
    signal,
  })
  return parseOrderResponse(data)
}

export async function createOrderPaymentIntent(orderId: string, signal?: AbortSignal): Promise<PaymentIntent> {
  const { data } = await serviceRequest<unknown>(requireOrderApi(), {
    url: `/orders/${encodeURIComponent(orderId)}/payment-intent`,
    method: 'post',
    signal,
  })
  return parsePaymentIntent(data)
}

export async function cancelRentalOrder(orderId: string, signal?: AbortSignal): Promise<RentalOrder> {
  const { data } = await serviceRequest<unknown>(requireOrderApi(), {
    url: `/orders/${encodeURIComponent(orderId)}/cancel`,
    method: 'post',
    signal,
  })
  return parseOrderResponse(data)
}

export { HttpError, orderErrorMessage, type ProblemDetails }
