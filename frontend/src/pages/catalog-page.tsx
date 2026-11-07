import { AlertCircle, SearchX } from 'lucide-react'
import { useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CategoryIcon } from '@/components/common/category-icon'
import { EmptyState } from '@/components/common/empty-state'
import { EquipmentCard } from '@/components/common/equipment-card'
import { PageHeader } from '@/components/common/page-header'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { CATEGORIES, EQUIPMENT } from '@/data/equipment'
import { LIVE_CATALOG, useCatalogProducts, useCatalogStores } from '@/hooks/use-catalog-data'
import { toEquipment, storeFor } from '@/lib/catalog-mapping'
import { cn } from '@/lib/utils'
import type { CategoryId } from '@/types'
import type { ProductListParams, SortDirection } from '@/types/catalog'

const SORT_ITEMS = [
  { value: 'popular', label: 'Most popular' },
  { value: 'price-asc', label: 'Price: low to high' },
  { value: 'price-desc', label: 'Price: high to low' },
  { value: 'rating', label: 'Top rated' },
]

const PER_PAGE = 24

function chipClass(active: boolean) {
  return cn(
    'inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors',
    active ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:bg-accent',
  )
}

function sortParams(sort: string): Pick<ProductListParams, 'sort' | 'sort_direction'> {
  if (sort === 'price-asc') return { sort: 'price_per_hour', sort_direction: 'asc' }
  if (sort === 'price-desc') return { sort: 'price_per_hour', sort_direction: 'desc' }
  // The backend supports only price_per_hour and created_at; popularity and
  // rating fall back to newest first.
  return { sort: 'created_at', sort_direction: 'desc' as SortDirection }
}

function MockCatalogPage() {
  const [params, setParams] = useSearchParams()
  const query = params.get('q')?.trim().toLowerCase() ?? ''
  const category = params.get('category') as CategoryId | null
  const sort = params.get('sort') ?? 'popular'

  function updateParam(key: string, value: string | null) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    setParams(next, { replace: true })
  }

  const results = useMemo(() => {
    const filtered = EQUIPMENT.filter((item) => {
      if (item.status !== 'active') return false
      if (category && item.category !== category) return false
      if (!query) return true
      return [item.name, item.description, item.provider, item.location].some((field) => field.toLowerCase().includes(query))
    })
    return [...filtered].sort((a, b) => {
      if (sort === 'price-asc') return a.pricePerDay - b.pricePerDay
      if (sort === 'price-desc') return b.pricePerDay - a.pricePerDay
      if (sort === 'rating') return b.rating - a.rating
      return b.totalRentals - a.totalRentals
    })
  }, [query, category, sort])

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <PageHeader
        title={query ? `Results for "${params.get('q')}"` : 'Browse equipment'}
        description="Filter by category, compare prices and book the gear you need."
      />

      <div className="mt-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div className="-mx-4 flex min-w-0 gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-1 md:px-0" role="group" aria-label="Categories">
          <button type="button" className={chipClass(!category)} onClick={() => updateParam('category', null)} aria-pressed={!category}>
            All
          </button>
          {CATEGORIES.map((item) => (
            <button
              key={item.id}
              type="button"
              className={chipClass(category === item.id)}
              onClick={() => updateParam('category', item.id)}
              aria-pressed={category === item.id}
            >
              <CategoryIcon category={item.id} className="size-4" />
              {item.label}
            </button>
          ))}
        </div>
        <Select items={SORT_ITEMS} value={sort} onValueChange={(value) => updateParam('sort', value === 'popular' ? null : value)}>
          <SelectTrigger className="h-10 w-full shrink-0 rounded-full px-4 md:w-52" aria-label="Sort by">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {SORT_ITEMS.map((item) => (
              <SelectItem key={item.value} value={item.value}>
                {item.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <p className="mt-6 text-sm text-muted-foreground" aria-live="polite">
        {results.length} {results.length === 1 ? 'item' : 'items'} available
      </p>

      {results.length > 0 ? (
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {results.map((item) => (
            <EquipmentCard key={item.id} item={item} />
          ))}
        </div>
      ) : (
        <div className="mt-4">
          <EmptyState
            icon={SearchX}
            title="No equipment found"
            description="Try a different search term or clear your filters."
            action={<Button onClick={() => setParams({}, { replace: true })}>Clear filters</Button>}
          />
        </div>
      )}
    </div>
  )
}

function LiveCatalogPage() {
  const [params, setParams] = useSearchParams()
  const query = params.get('q')?.trim().toLowerCase() ?? ''
  const storeId = params.get('store') ?? ''
  const sort = params.get('sort') ?? 'popular'
  const page = Math.max(1, Number(params.get('page')) || 1)

  const stores = useCatalogStores()
  const products = useCatalogProducts({
    store_id: storeId || undefined,
    page,
    per_page: PER_PAGE,
    ...sortParams(sort),
  })

  function updateParam(key: string, value: string | null) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key !== 'page') next.delete('page')
    setParams(next, { replace: true })
  }

  const storeList = stores.state.status === 'success' ? stores.state.data : []
  const pageData = products.state.status === 'success' ? products.state.data : undefined
  const items = (pageData?.data ?? [])
    .map((product) => toEquipment(product, storeFor(product, storeList)))
    .filter((item) => {
      if (!query) return true
      return [item.name, item.description, item.provider, item.location].some((field) => field.toLowerCase().includes(query))
    })
  const meta = pageData?.meta

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <PageHeader title="Browse equipment" description="Live catalog from the catalog service." />

      <div className="mt-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div className="-mx-4 flex min-w-0 gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-1 md:px-0" role="group" aria-label="Stores">
          <button type="button" className={chipClass(!storeId)} onClick={() => updateParam('store', null)} aria-pressed={!storeId}>
            All stores
          </button>
          {storeList.map((store) => (
            <button
              key={store.id}
              type="button"
              className={chipClass(storeId === store.id)}
              onClick={() => updateParam('store', store.id)}
              aria-pressed={storeId === store.id}
            >
              {store.name}
            </button>
          ))}
        </div>
        <Select items={SORT_ITEMS} value={sort} onValueChange={(value) => updateParam('sort', value === 'popular' ? null : value)}>
          <SelectTrigger className="h-10 w-full shrink-0 rounded-full px-4 md:w-52" aria-label="Sort by">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {SORT_ITEMS.map((item) => (
              <SelectItem key={item.value} value={item.value}>
                {item.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {products.state.status === 'loading' || stores.state.status === 'loading' ? (
        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" role="status" aria-live="polite">
          <span className="sr-only">Loading equipment...</span>
          {Array.from({ length: 8 }, (_, index) => <Skeleton key={index} className="h-80 rounded-3xl" />)}
        </div>
      ) : products.state.status === 'error' ? (
        <div role="alert" className="mt-6 rounded-3xl border bg-card p-6">
          <AlertCircle className="size-6 text-destructive" aria-hidden="true" />
          <p className="mt-3 font-semibold">Unable to load the catalog</p>
          <p className="mt-1 text-sm text-muted-foreground">{products.state.error}</p>
          <Button variant="outline" className="mt-4 rounded-full" onClick={() => products.reload()}>Try again</Button>
        </div>
      ) : (
        <>
          <p className="mt-6 text-sm text-muted-foreground" aria-live="polite">
            {meta ? `${meta.total} ${meta.total === 1 ? 'item' : 'items'} available` : `${items.length} items available`}
          </p>
          {items.length > 0 ? (
            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {items.map((item) => <EquipmentCard key={item.id} item={item} />)}
            </div>
          ) : (
            <div className="mt-4">
              <EmptyState
                icon={SearchX}
                title="No equipment found"
                description="Try a different store or clear your filters."
                action={<Button onClick={() => setParams({}, { replace: true })}>Clear filters</Button>}
              />
            </div>
          )}
          {meta && meta.last_page > 1 ? (
            <nav aria-label="Catalog pagination" className="mt-8 flex items-center justify-center gap-3">
              <Button variant="outline" className="rounded-full" disabled={meta.current_page <= 1} onClick={() => updateParam('page', String(meta.current_page - 1))}>
                Previous
              </Button>
              <span className="text-sm text-muted-foreground">Page {meta.current_page} of {meta.last_page}</span>
              <Button variant="outline" className="rounded-full" disabled={meta.current_page >= meta.last_page} onClick={() => updateParam('page', String(meta.current_page + 1))}>
                Next
              </Button>
            </nav>
          ) : null}
        </>
      )}
    </div>
  )
}

export function CatalogPage() {
  return LIVE_CATALOG ? <LiveCatalogPage /> : <MockCatalogPage />
}
