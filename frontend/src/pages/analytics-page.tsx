import { PageHeader } from '@/components/common/page-header'
import { StatCard } from '@/components/common/stat-card'
import { CATEGORIES, EQUIPMENT } from '@/data/equipment'
import { formatCurrency } from '@/lib/pricing'
import { isMockApi } from '@/api/client'

const MONTHLY_REVENUE = [
  { month: 'Jan', value: 4200 },
  { month: 'Feb', value: 3800 },
  { month: 'Mar', value: 5100 },
  { month: 'Apr', value: 6400 },
  { month: 'May', value: 7200 },
  { month: 'Jun', value: 8600 },
]

interface BarRow {
  label: string
  value: number
  display: string
}

function HorizontalBars({ rows }: { rows: BarRow[] }) {
  const max = Math.max(...rows.map((row) => row.value), 1)
  return (
    <ul className="space-y-4">
      {rows.map((row) => (
        <li key={row.label}>
          <div className="flex justify-between text-sm">
            <span className="font-semibold">{row.label}</span>
            <span className="text-muted-foreground">{row.display}</span>
          </div>
          <div className="mt-1.5 h-3 overflow-hidden rounded-full bg-secondary">
            <div className="h-full rounded-full bg-primary" style={{ width: `${(row.value / max) * 100}%` }} />
          </div>
        </li>
      ))}
    </ul>
  )
}

export function AnalyticsPage() {
  if (!isMockApi) return <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6"><PageHeader title="Analytics unavailable" description="Live marketplace analytics are not connected yet." /></div>
  const revenueByCategory = CATEGORIES.map((category) => {
    const value = EQUIPMENT.filter((item) => item.category === category.id).reduce(
      (sum, item) => sum + item.totalRentals * item.pricePerDay * 2,
      0,
    )
    return { label: category.label, value, display: formatCurrency(value) }
  }).sort((a, b) => b.value - a.value)

  const topEquipment = [...EQUIPMENT]
    .sort((a, b) => b.totalRentals - a.totalRentals)
    .slice(0, 5)
    .map((item) => ({ label: item.name, value: item.totalRentals, display: `${item.totalRentals} rentals` }))

  const totalRevenue = revenueByCategory.reduce((sum, row) => sum + row.value, 0)
  const totalRentals = EQUIPMENT.reduce((sum, item) => sum + item.totalRentals, 0)
  const avgRating = EQUIPMENT.reduce((sum, item) => sum + item.rating, 0) / EQUIPMENT.length
  const maxMonth = Math.max(...MONTHLY_REVENUE.map((entry) => entry.value))

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <PageHeader title="Analytics" description="Performance overview across the whole marketplace." />

      <div className="mt-8 grid gap-4 sm:grid-cols-3">
        <StatCard label="Estimated revenue" value={formatCurrency(totalRevenue)} hint="Lifetime, all categories" />
        <StatCard label="Total rentals" value={totalRentals.toLocaleString('en-US')} />
        <StatCard label="Average rating" value={avgRating.toFixed(2)} hint="Across all listings" />
      </div>

      <section className="mt-6 rounded-3xl border bg-card p-6" aria-labelledby="monthly-heading">
        <h2 id="monthly-heading" className="text-lg font-bold">Monthly revenue</h2>
        <ul className="mt-6 flex h-56 items-end gap-3 sm:gap-6">
          {MONTHLY_REVENUE.map((entry) => (
            <li key={entry.month} className="flex h-full flex-1 flex-col items-center justify-end gap-2">
              <span className="text-xs font-semibold text-muted-foreground">{formatCurrency(entry.value)}</span>
              <div className="w-full max-w-16 rounded-t-xl bg-primary" style={{ height: `${(entry.value / maxMonth) * 80}%` }} />
              <span className="text-sm font-semibold">{entry.month}</span>
            </li>
          ))}
        </ul>
      </section>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <section className="rounded-3xl border bg-card p-6" aria-labelledby="category-heading">
          <h2 id="category-heading" className="mb-5 text-lg font-bold">Revenue by category</h2>
          <HorizontalBars rows={revenueByCategory} />
        </section>
        <section className="rounded-3xl border bg-card p-6" aria-labelledby="top-heading">
          <h2 id="top-heading" className="mb-5 text-lg font-bold">Top equipment</h2>
          <HorizontalBars rows={topEquipment} />
        </section>
      </div>
    </div>
  )
}
