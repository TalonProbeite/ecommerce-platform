import { useEffect, useState } from 'react'
import { Package } from 'lucide-react'
import { isMockApi } from '@/api/client'
import { DEMO_ORDERS } from '@/data/orders'
import { ROUTES } from '@/app/routes'
import { EmptyState } from '@/components/common/empty-state'
import { LinkButton } from '@/components/common/link-button'
import { OrderCard } from '@/components/orders/order-card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { loadRentalHistory } from '@/services/rental-history'
import { useOrdersStore } from '@/store/orders-store'
import type { Order } from '@/types'

type RentalHistoryState =
  | { status: 'loading' }
  | { status: 'error' }
  | { status: 'success'; orders: Order[] }

export function RecentRentalsContent({ state, onRetry }: { state: RentalHistoryState; onRetry: () => void }) {
  if (state.status === 'loading') {
    return (
      <div role="status" aria-live="polite" className="space-y-4">
        <span className="sr-only">Loading rentals...</span>
        <Skeleton className="h-48 rounded-3xl" />
        <Skeleton className="h-48 rounded-3xl" />
      </div>
    )
  }
  if (state.status === 'error') {
    return (
      <div role="alert" className="rounded-3xl border bg-card p-6">
        <p className="font-semibold">Unable to load your rentals</p>
        <p className="mt-1 text-sm text-muted-foreground">Please try again to load your rental history.</p>
        <Button type="button" variant="outline" className="mt-4 rounded-full" onClick={onRetry}>Try again</Button>
      </div>
    )
  }
  if (state.orders.length === 0) {
    return (
      <EmptyState icon={Package} title="No rentals yet"
        description="When you book equipment it will show up here."
        action={<LinkButton to={ROUTES.catalog}>Browse equipment</LinkButton>} />
    )
  }
  return <ul className="space-y-4">{state.orders.map((order) => <OrderCard key={order.id} order={order} />)}</ul>
}

export function RecentRentals({ userId, showOrdersLink = true }: { userId?: string; showOrdersLink?: boolean }) {
  const isDemo = !isMockApi || !userId
  const orders = useOrdersStore((state) => state.orders)
  const [attempt, setAttempt] = useState(0)
  const [result, setResult] = useState<{ userId: string; orders: Order[]; attempt: number; state: RentalHistoryState } | null>(null)

  useEffect(() => {
    if (isDemo || !userId) return
    let cancelled = false
    loadRentalHistory(userId).then(
      (rentals) => {
        if (!cancelled) setResult({ userId, orders, attempt, state: { status: 'success', orders: rentals } })
      },
      () => {
        if (!cancelled) setResult({ userId, orders, attempt, state: { status: 'error' } })
      },
    )
    return () => { cancelled = true }
  }, [userId, orders, attempt, isDemo])

  // Never show another user's history or stale results while a new request is pending.
  const state: RentalHistoryState = isDemo ? { status: 'success', orders: DEMO_ORDERS } : result?.userId === userId && result.orders === orders && result.attempt === attempt
    ? result.state : { status: 'loading' }

  return (
    <section aria-labelledby="recent-rentals-heading" className="mt-8">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="recent-rentals-heading" className="text-xl font-bold">Recent Rentals</h2>
        {showOrdersLink && !isDemo ? <LinkButton to={ROUTES.orders} variant="outline" size="sm" className="rounded-full">My rentals</LinkButton> : null}
      </div>
      <p className="mb-4 text-sm text-muted-foreground">{isDemo ? 'Demo orders — sample data. Your real orders will appear here once the service is connected.' : 'Rental history uses mock data while the orders service is being developed.'}</p>
      <RecentRentalsContent state={state} onRetry={() => setAttempt((value) => value + 1)} />
    </section>
  )
}
