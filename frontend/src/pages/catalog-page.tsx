import { SearchX } from 'lucide-react'
import { useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CategoryIcon } from '@/components/common/category-icon'
import { EmptyState } from '@/components/common/empty-state'
import { EquipmentCard } from '@/components/common/equipment-card'
import { PageHeader } from '@/components/common/page-header'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CATEGORIES, EQUIPMENT } from '@/data/equipment'
import { cn } from '@/lib/utils'
import type { CategoryId } from '@/types'

const SORT_ITEMS = [
  { value: 'popular', label: 'Most popular' },
  { value: 'price-asc', label: 'Price: low to high' },
  { value: 'price-desc', label: 'Price: high to low' },
  { value: 'rating', label: 'Top rated' },
]

export function CatalogPage() {
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

  const chipClass = (active: boolean) =>
    cn(
      'inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors',
      active ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:bg-accent',
    )

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
