import { useCallback, useEffect, useRef, useState } from 'react'
import { getRentalOrder, listRentalOrders, orderErrorMessage } from '@/api/order-client'
import { ORDER_POLL_INTERVAL_MS, shouldPollOrder } from '@/lib/polling'
import type { RentalOrder } from '@/types/rental-order'

export type RentalOrderState =
  | { status: 'loading' }
  | { status: 'error'; error: string }
  | { status: 'success'; order: RentalOrder }

export function useRentalOrder(orderId: string | undefined, poll = false) {
  const [state, setState] = useState<RentalOrderState>({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)
  const reload = useCallback(() => setAttempt((value) => value + 1), [])
  const startedAt = useRef(0)

  useEffect(() => {
    if (!orderId) return
    const controller = new AbortController()
    let timer: ReturnType<typeof setTimeout> | undefined
    startedAt.current = Date.now()

    const load = () => {
      getRentalOrder(orderId, controller.signal).then(
        (order) => {
          if (controller.signal.aborted) return
          setState({ status: 'success', order })
          if (poll && shouldPollOrder(order.status, startedAt.current)) {
            timer = setTimeout(load, ORDER_POLL_INTERVAL_MS)
          }
        },
        (error: unknown) => {
          if (!controller.signal.aborted) setState({ status: 'error', error: orderErrorMessage(error) })
        },
      )
    }

    setState({ status: 'loading' })
    load()
    return () => {
      controller.abort()
      if (timer) clearTimeout(timer)
    }
  }, [orderId, poll, attempt])

  return { state, reload }
}

export type RentalOrderListState =
  | { status: 'loading' }
  | { status: 'error'; error: string }
  | { status: 'success'; orders: RentalOrder[]; nextCursor: string | null }

export function useRentalOrders(status?: string, options: { storeId?: string } = {}) {
  const [state, setState] = useState<RentalOrderListState>({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)
  const [loadingMore, setLoadingMore] = useState(false)
  const reload = useCallback(() => setAttempt((value) => value + 1), [])
  const filter = status?.trim() || undefined
  const storeId = options.storeId?.trim() || undefined

  useEffect(() => {
    const controller = new AbortController()
    setState({ status: 'loading' })
    listRentalOrders({ limit: 20, status: filter, storeId }, controller.signal).then(
      (page) => {
        if (!controller.signal.aborted) setState({ status: 'success', orders: page.orders, nextCursor: page.nextCursor })
      },
      (error: unknown) => {
        if (!controller.signal.aborted) setState({ status: 'error', error: orderErrorMessage(error) })
      },
    )
    return () => controller.abort()
  }, [attempt, filter, storeId])

  const loadMore = useCallback(async () => {
    if (state.status !== 'success' || !state.nextCursor || loadingMore) return
    setLoadingMore(true)
    try {
      const page = await listRentalOrders({ cursor: state.nextCursor, limit: 20, status: filter, storeId })
      setState({
        status: 'success',
        orders: [...state.orders, ...page.orders],
        nextCursor: page.nextCursor,
      })
    } catch (error) {
      setState({ status: 'error', error: orderErrorMessage(error) })
    } finally {
      setLoadingMore(false)
    }
  }, [state, loadingMore, filter, storeId])

  return { state, reload, loadMore, loadingMore }
}
