import { useCallback, useEffect, useState } from 'react'
import { listOverdueRentals } from '@/api/order-client'
import { orderErrorMessage } from '@/lib/order-error'
import type { OverdueRental } from '@/types/rental-order'

const PAGE_SIZE = 20

export type OverdueRentalListState =
  | { status: 'loading' }
  | { status: 'error'; error: string }
  | { status: 'success'; items: OverdueRental[]; nextCursor: string | null }

export function useOverdueRentals(options: { storeId?: string } = {}) {
  const [state, setState] = useState<OverdueRentalListState>({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)
  const [loadingMore, setLoadingMore] = useState(false)
  const reload = useCallback(() => setAttempt((value) => value + 1), [])
  const storeId = options.storeId?.trim() || undefined

  useEffect(() => {
    const controller = new AbortController()
    setState({ status: 'loading' })
    listOverdueRentals({ limit: PAGE_SIZE, storeId }, controller.signal).then(
      (page) => {
        if (!controller.signal.aborted) setState({ status: 'success', items: page.items, nextCursor: page.nextCursor })
      },
      (error: unknown) => {
        if (!controller.signal.aborted) setState({ status: 'error', error: orderErrorMessage(error) })
      },
    )
    return () => controller.abort()
  }, [attempt, storeId])

  const loadMore = useCallback(async () => {
    if (state.status !== 'success' || !state.nextCursor || loadingMore) return
    setLoadingMore(true)
    try {
      const page = await listOverdueRentals({ cursor: state.nextCursor, limit: PAGE_SIZE, storeId })
      setState({
        status: 'success',
        items: [...state.items, ...page.items],
        nextCursor: page.nextCursor,
      })
    } catch (error) {
      setState({ status: 'error', error: orderErrorMessage(error) })
    } finally {
      setLoadingMore(false)
    }
  }, [state, loadingMore, storeId])

  return { state, reload, loadMore, loadingMore }
}
