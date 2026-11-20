import { useCallback, useEffect, useState } from 'react'
import { listRentalOrderEvents } from '@/api/order-client'
import { managerActionErrorMessage } from '@/lib/order-error'
import type { RentalOrderEvent } from '@/types/rental-order'

const EVENTS_PAGE_SIZE = 50

export type OrderEventsState =
  | { status: 'loading' }
  | { status: 'error'; error: string }
  | { status: 'success'; events: RentalOrderEvent[]; nextCursor: string | null }

export function useOrderEvents(orderId: string | undefined) {
  const [state, setState] = useState<OrderEventsState>({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)
  const [loadingMore, setLoadingMore] = useState(false)
  const reload = useCallback(() => setAttempt((value) => value + 1), [])

  useEffect(() => {
    if (!orderId) return
    const controller = new AbortController()
    setState({ status: 'loading' })
    listRentalOrderEvents(orderId, { limit: EVENTS_PAGE_SIZE }, controller.signal).then(
      (page) => {
        if (!controller.signal.aborted) setState({ status: 'success', events: page.events, nextCursor: page.nextCursor })
      },
      (error: unknown) => {
        if (!controller.signal.aborted) setState({ status: 'error', error: managerActionErrorMessage(error) })
      },
    )
    return () => controller.abort()
  }, [orderId, attempt])

  const loadMore = useCallback(async () => {
    if (state.status !== 'success' || !state.nextCursor || loadingMore) return
    setLoadingMore(true)
    try {
      const page = await listRentalOrderEvents(orderId ?? '', { cursor: state.nextCursor, limit: EVENTS_PAGE_SIZE })
      setState({
        status: 'success',
        events: [...state.events, ...page.events],
        nextCursor: page.nextCursor,
      })
    } catch (error) {
      setState({ status: 'error', error: managerActionErrorMessage(error) })
    } finally {
      setLoadingMore(false)
    }
  }, [state, loadingMore, orderId])

  return { state, reload, loadMore, loadingMore }
}
