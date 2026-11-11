import { useAsyncResource, LIVE_CATALOG } from '@/hooks/use-catalog-data'
import {
  adminGetProduct,
  adminListProducts,
  listAllStores,
  listStoreCategories,
  getStore,
} from '@/services/catalog'
import type { Category, PaginatedResponse, Product, ProductListParams, Store } from '@/types/catalog'

export function useAdminStores(enabled = LIVE_CATALOG) {
  return useAsyncResource<PaginatedResponse<Store>>(
    (signal) => listAllStores(signal),
    [],
    enabled,
  )
}

export function useAdminStore(storeId: string | undefined, enabled = LIVE_CATALOG) {
  return useAsyncResource<Store>(
    (signal) => getStore(storeId ?? '', signal),
    [storeId],
    enabled && Boolean(storeId),
  )
}

export function useAdminCategories(storeId: string | undefined, enabled = LIVE_CATALOG) {
  return useAsyncResource<Category[]>(
    (signal) => listStoreCategories(storeId ?? '', signal),
    [storeId],
    enabled && Boolean(storeId),
  )
}

export function useAdminProducts(
  storeId: string | undefined,
  params: Omit<ProductListParams, 'store_id'>,
  enabled = LIVE_CATALOG,
) {
  return useAsyncResource<PaginatedResponse<Product>>(
    (signal) => adminListProducts(storeId ?? '', params, signal),
    [storeId, JSON.stringify(params)],
    enabled && Boolean(storeId),
  )
}

export function useAdminProduct(
  storeId: string | undefined,
  productId: string | undefined,
  enabled = LIVE_CATALOG,
) {
  return useAsyncResource<Product>(
    (signal) => adminGetProduct(storeId ?? '', productId ?? '', signal),
    [storeId, productId],
    enabled && Boolean(storeId) && Boolean(productId),
  )
}
