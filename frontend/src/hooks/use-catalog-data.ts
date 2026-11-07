import { useCallback, useEffect, useState } from 'react'
import { IS_CATALOG_LIVE } from '@/api/endpoints'
import { getProduct, listProducts, listStores } from '@/services/catalog'
import type { Product, ProductListParams, Store } from '@/types/catalog'

export const LIVE_CATALOG = IS_CATALOG_LIVE

export type AsyncState<T> =
  | { status: 'loading' }
  | { status: 'error'; error: string }
  | { status: 'success'; data: T }

function useAsync<T>(loader: (signal: AbortSignal) => Promise<T>, deps: unknown[], enabled = true) {
  const [state, setState] = useState<AsyncState<T>>({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)
  const reload = useCallback(() => setAttempt((value) => value + 1), [])

  useEffect(() => {
    if (!enabled) return
    const controller = new AbortController()
    setState({ status: 'loading' })
    loader(controller.signal).then(
      (data) => {
        if (!controller.signal.aborted) setState({ status: 'success', data })
      },
      (error: unknown) => {
        if (!controller.signal.aborted) {
          setState({ status: 'error', error: error instanceof Error ? error.message : 'Request failed.' })
        }
      },
    )
    return () => controller.abort()
  }, [...deps, attempt, enabled])

  return { state, reload }
}

export function useCatalogStores(enabled = LIVE_CATALOG) {
  return useAsync(
    (signal) => listStores({ active: true, per_page: 100 }, signal).then((page) => page.data),
    [],
    enabled,
  )
}

export function useCatalogProducts(params: ProductListParams, enabled = LIVE_CATALOG) {
  return useAsync(
    (signal) => listProducts(params, signal),
    [JSON.stringify(params)],
    enabled,
  )
}

export function useCatalogProduct(productId: string, enabled = LIVE_CATALOG) {
  return useAsync<Product>(
    (signal) => getProduct(productId, signal),
    [productId],
    enabled && Boolean(productId),
  )
}
