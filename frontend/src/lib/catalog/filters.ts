import type { ProductListParams, ProductSortField, SortDirection } from '@/types/catalog'

export const CATALOG_PAGE_SIZE = 24
export const CATALOG_PAGE_SIZES = [12, 24, 48]

export const CATALOG_SORT_OPTIONS = [
  { value: 'newest', label: 'Newest first' },
  { value: 'price_asc', label: 'Price: low to high' },
  { value: 'price_desc', label: 'Price: high to low' },
] as const

export type CatalogSort = (typeof CATALOG_SORT_OPTIONS)[number]['value']

export interface CatalogFilterState {
  storeId: string
  categoryId: string
  minPrice: number | undefined
  maxPrice: number | undefined
  maxDeposit: number | undefined
  availableOnly: boolean
  sort: CatalogSort
  page: number
  perPage: number
}

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

function uuidOrEmpty(value: string | null): string {
  return value && UUID_RE.test(value) ? value : ''
}

function positiveInt(value: string | null): number | undefined {
  if (!value) return undefined
  const parsed = Number.parseInt(value, 10)
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : undefined
}

function sortToApi(sort: CatalogSort): { sort: ProductSortField; sort_direction: SortDirection } {
  if (sort === 'price_asc') return { sort: 'price_per_hour', sort_direction: 'asc' }
  if (sort === 'price_desc') return { sort: 'price_per_hour', sort_direction: 'desc' }
  return { sort: 'created_at', sort_direction: 'desc' }
}

export function parseCatalogFilters(params: URLSearchParams): CatalogFilterState {
  const rawSort = params.get('sort')
  const sort = CATALOG_SORT_OPTIONS.some((option) => option.value === rawSort)
    ? (rawSort as CatalogSort)
    : 'newest'
  const page = Math.max(1, positiveInt(params.get('page')) ?? 1)
  const perPage = Math.min(100, Math.max(1, positiveInt(params.get('per_page')) ?? CATALOG_PAGE_SIZE))

  return {
    storeId: uuidOrEmpty(params.get('store')),
    categoryId: uuidOrEmpty(params.get('category')),
    minPrice: positiveInt(params.get('min_price')),
    maxPrice: positiveInt(params.get('max_price')),
    maxDeposit: positiveInt(params.get('max_deposit')),
    availableOnly: params.get('available') === '1',
    sort,
    page,
    perPage,
  }
}

export function toProductListParams(filters: CatalogFilterState): ProductListParams {
  const { sort, sort_direction } = sortToApi(filters.sort)
  return {
    store_id: filters.storeId || undefined,
    category_id: filters.categoryId || undefined,
    min_price: filters.minPrice,
    max_price: filters.maxPrice,
    max_deposit: filters.maxDeposit,
    available_only: filters.availableOnly || undefined,
    sort,
    sort_direction,
    page: filters.page,
    per_page: filters.perPage,
  }
}
