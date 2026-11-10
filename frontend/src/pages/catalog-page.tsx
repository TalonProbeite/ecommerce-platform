import { AlertCircle, SearchX } from 'lucide-react'
import { useEffect, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CategoryIcon } from '@/components/common/category-icon'
import { EmptyState } from '@/components/common/empty-state'
import { EquipmentCard } from '@/components/common/equipment-card'
import { PageHeader } from '@/components/common/page-header'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { CATEGORIES, EQUIPMENT } from '@/data/equipment'
import { LIVE_CATALOG, useCatalogProducts, useCatalogStores, useStoreCategories } from '@/hooks/use-catalog-data'
import {
  CATALOG_PAGE_SIZES,
  CATALOG_SORT_OPTIONS,
  parseCatalogFilters,
  toProductListParams,
  type CatalogFilterState,
} from '@/lib/catalog/filters'
import { flattenCategoryTree, storeFor, toEquipment } from '@/lib/catalog-mapping'
import { cn } from '@/lib/utils'
import type { CategoryId } from '@/types'

const LIVE_SORT_ITEMS = CATALOG_SORT_OPTIONS.map((option) => ({ value: option.value, label: option.label }))
const MOCK_SORT_ITEMS = [
  { value: 'popular', label: 'Most popular' },
  { value: 'price_asc', label: 'Price: low to high' },
  { value: 'price_desc', label: 'Price: high to low' },
]

function chipClass(active: boolean) {
  return cn(
    'inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors',
    active ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:bg-accent',
  )
}

function MockCatalogPage() {
  const [params, setParams] = useSearchParams()
  const query = params.get('q')?.trim().toLowerCase() ?? ''
  const category = params.get('category') as CategoryId | null
  const rawSort = params.get('sort')
  const sort = MOCK_SORT_ITEMS.some((item) => item.value === rawSort) ? rawSort! : 'popular'
  const categoryItems = [{ value: 'all', label: 'All categories' }, ...CATEGORIES.map((item) => ({ value: item.id, label: item.label }))]

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
      if (sort === 'price_asc') return a.pricePerDay - b.pricePerDay
      if (sort === 'price_desc') return b.pricePerDay - a.pricePerDay
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
        <div className="flex items-center gap-2">
          <Select items={categoryItems} value={category ?? 'all'} onValueChange={(value) => updateParam('category', value === 'all' ? null : value)}>
            <SelectTrigger className="h-10 w-full shrink-0 rounded-full px-4 md:w-48" aria-label="Category">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {categoryItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
            </SelectContent>
          </Select>
          <Select items={MOCK_SORT_ITEMS} value={sort} onValueChange={(value) => updateParam('sort', value === 'popular' ? null : value)}>
            <SelectTrigger className="h-10 w-full shrink-0 rounded-full px-4 md:w-48" aria-label="Sort by">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {MOCK_SORT_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
      </div>

      <p className="mt-6 text-sm text-muted-foreground" aria-live="polite">
        {results.length} {results.length === 1 ? 'item' : 'items'} available
      </p>

      {results.length > 0 ? (
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {results.map((item) => <EquipmentCard key={item.id} item={item} />)}
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

function updateParam(params: URLSearchParams, key: string, value: string | null): URLSearchParams {
  const next = new URLSearchParams(params)
  if (value) next.set(key, value)
  else next.delete(key)
  if (key !== 'page') next.delete('page')
  return next
}

function FilterSelect({
  id,
  label,
  value,
  items,
  onChange,
  disabled,
}: {
  id: string
  label: string
  value: string
  items: Array<{ value: string; label: string }>
  onChange: (value: string) => void
  disabled?: boolean
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <Select items={items} value={value} onValueChange={(next) => { if (next) onChange(next) }} disabled={disabled}>
        <SelectTrigger id={id} className="h-10 w-full rounded-full px-4">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {items.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
        </SelectContent>
      </Select>
    </div>
  )
}

function FiltersBar({
  filters,
  stores,
  categories,
  update,
  updateStore,
}: {
  filters: CatalogFilterState
  stores: ReturnType<typeof useCatalogStores>
  categories: ReturnType<typeof useStoreCategories>
  update: (key: string, value: string | null) => void
  updateStore: (value: string | null) => void
}) {
  const storeItems = [
    { value: 'all', label: 'All stores' },
    ...(stores.state.status === 'success'
      ? stores.state.data.map((store) => ({ value: store.id, label: store.name }))
      : []),
  ]
  const categoryItems = [
    { value: 'all', label: 'All categories' },
    ...(categories.state.status === 'success'
      ? flattenCategoryTree(categories.state.data).map(({ category, depth }) => ({
          value: category.id,
          label: `${'\u2014 '.repeat(depth)}${category.name}`,
        }))
      : []),
  ]

  return (
    <div className="mt-8 rounded-3xl border bg-card p-4 sm:p-5">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <FilterSelect
          id="catalog-store"
          label="Store"
          value={filters.storeId || 'all'}
          items={storeItems}
          disabled={stores.state.status === 'loading'}
          onChange={(value) => updateStore(value === 'all' ? null : value)}
        />
        <FilterSelect
          id="catalog-category"
          label="Category"
          value={filters.categoryId || 'all'}
          items={categoryItems}
          disabled={!filters.storeId || categories.state.status === 'loading'}
          onChange={(value) => update('category', value === 'all' ? null : value)}
        />
        <FilterSelect
          id="catalog-sort"
          label="Sort by"
          value={filters.sort}
          items={LIVE_SORT_ITEMS}
          onChange={(value) => update('sort', value === 'newest' ? null : value)}
        />
        <FilterSelect
          id="catalog-per-page"
          label="Items per page"
          value={String(filters.perPage)}
          items={CATALOG_PAGE_SIZES.map((size) => ({ value: String(size), label: String(size) }))}
          onChange={(value) => update('per_page', value === '24' ? null : value)}
        />
      </div>

      <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-1.5">
          <Label htmlFor="catalog-min-price">Min price / hour</Label>
          <Input
            id="catalog-min-price"
            type="number"
            min={0}
            inputMode="numeric"
            placeholder="0"
            value={filters.minPrice ?? ''}
            onChange={(event) => update('min_price', event.target.value)}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="catalog-max-price">Max price / hour</Label>
          <Input
            id="catalog-max-price"
            type="number"
            min={0}
            inputMode="numeric"
            placeholder="Any"
            value={filters.maxPrice ?? ''}
            onChange={(event) => update('max_price', event.target.value)}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="catalog-max-deposit">Max deposit</Label>
          <Input
            id="catalog-max-deposit"
            type="number"
            min={0}
            inputMode="numeric"
            placeholder="Any"
            value={filters.maxDeposit ?? ''}
            onChange={(event) => update('max_deposit', event.target.value)}
          />
        </div>
        <div className="flex items-end gap-2 pb-1">
          <Checkbox
            id="catalog-available"
            checked={filters.availableOnly}
            onCheckedChange={(checked) => update('available', checked ? '1' : null)}
          />
          <Label htmlFor="catalog-available">Available only</Label>
        </div>
      </div>

      {!filters.storeId ? (
        <p className="mt-3 text-xs text-muted-foreground">Choose a store to filter by category.</p>
      ) : null}
      {stores.state.status === 'error' ? (
        <p role="alert" className="mt-3 text-sm text-destructive">{stores.state.error}</p>
      ) : null}
      {categories.state.status === 'error' ? (
        <p role="alert" className="mt-3 text-sm text-destructive">{categories.state.error}</p>
      ) : null}
    </div>
  )
}

function LiveCatalogPage() {
  const [params, setParams] = useSearchParams()
  const search = params.toString()
  const query = params.get('q')?.trim().toLowerCase() ?? ''
  const filters = parseCatalogFilters(params)
  const stores = useCatalogStores()
  const categories = useStoreCategories(filters.storeId || undefined)
  const products = useCatalogProducts(toProductListParams(filters))

  useEffect(() => {
    const current = new URLSearchParams(search)
    const invalidStore = current.get('store') && !filters.storeId
    const invalidCategory = current.get('category') && !filters.categoryId
    if (!invalidStore && !invalidCategory) return
    const next = new URLSearchParams(current)
    if (invalidStore) {
      next.delete('store')
      next.delete('category')
    } else {
      next.delete('category')
    }
    setParams(next, { replace: true })
  }, [search, filters.storeId, filters.categoryId, setParams])

  function update(key: string, value: string | null) {
    setParams(updateParam(params, key, value), { replace: true })
  }

  function updateStore(value: string | null) {
    const next = new URLSearchParams(params)
    if (value) next.set('store', value)
    else next.delete('store')
    next.delete('category')
    next.delete('page')
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
  const clear = () => setParams(new URLSearchParams(), { replace: true })

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <PageHeader
        title={query ? `Results for "${params.get('q')}"` : 'Browse equipment'}
        description="Live catalog from the catalog service."
      />

      <FiltersBar filters={filters} stores={stores} categories={categories} update={update} updateStore={updateStore} />

      {products.state.status === 'loading' ? (
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
                description="Try a different store, category or clear your filters."
                action={<Button onClick={clear}>Clear filters</Button>}
              />
            </div>
          )}
          {meta && meta.last_page > 1 ? (
            <nav aria-label="Catalog pagination" className="mt-8 flex items-center justify-center gap-3">
              <Button variant="outline" className="rounded-full" disabled={meta.current_page <= 1} onClick={() => update('page', String(meta.current_page - 1))}>
                Previous
              </Button>
              <span className="text-sm text-muted-foreground">Page {meta.current_page} of {meta.last_page}</span>
              <Button variant="outline" className="rounded-full" disabled={meta.current_page >= meta.last_page} onClick={() => update('page', String(meta.current_page + 1))}>
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
