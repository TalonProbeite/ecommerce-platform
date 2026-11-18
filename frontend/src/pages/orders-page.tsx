import { Package } from 'lucide-react'
import { useState } from 'react'
import { ROUTES } from '@/app/routes'
import { EmptyState } from '@/components/common/empty-state'
import { LinkButton } from '@/components/common/link-button'
import { PageHeader } from '@/components/common/page-header'
import { UnderDevelopment } from '@/components/common/under-development'
import { RentalOrderCard } from '@/components/rental/rental-order-card'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { IS_ORDER_LIVE } from '@/api/endpoints'
import { useRentalOrders } from '@/hooks/use-rental-order'
import { OrderCard } from '@/components/orders/order-card'
import { useAuthStore } from '@/store/auth-store'
import { useOrdersStore } from '@/store/orders-store'
import { isMockApi } from '@/api/client'
import { ORDER_STATUS_LABELS } from '@/lib/order-status'
import { RENTAL_ORDER_STATUSES } from '@/types/rental-order'

const FILTER_ITEMS = [
  { value: 'all', label: 'All statuses' },
  ...RENTAL_ORDER_STATUSES.map((value) => ({ value, label: ORDER_STATUS_LABELS[value] })),
]

function LiveOrdersPage() {
  const [status, setStatus] = useState('all')
  const { state, reload, loadMore, loadingMore } = useRentalOrders(status === 'all' ? undefined : status)

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <PageHeader title="My rentals" description="Track current and past equipment rentals." />
      <div className="mt-6">
        <Select items={FILTER_ITEMS} value={status} onValueChange={(value) => setStatus(value ?? 'all')}>
          <SelectTrigger className="h-10 w-full rounded-full px-4 sm:w-56" aria-label="Filter by status">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {FILTER_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
          </SelectContent>
        </Select>
      </div>
      <div className="mt-8">
        {state.status === 'loading' ? (
          <div role="status" aria-live="polite" className="space-y-4">
            <span className="sr-only">Loading rentals...</span>
            <Skeleton className="h-48 rounded-3xl" />
            <Skeleton className="h-48 rounded-3xl" />
          </div>
        ) : state.status === 'error' ? (
          <div role="alert" className="rounded-3xl border bg-card p-6">
            <p className="font-semibold">Unable to load your rentals</p>
            <p className="mt-1 text-sm text-muted-foreground">{state.error}</p>
            <Button type="button" variant="outline" className="mt-4 rounded-full" onClick={reload}>Try again</Button>
          </div>
        ) : state.orders.length === 0 ? (
          <EmptyState
            icon={Package}
            title="No rentals yet"
            description="When you book equipment it will show up here."
            action={<LinkButton to={ROUTES.catalog}>Browse equipment</LinkButton>}
          />
        ) : (
          <>
            <ul className="space-y-4">
              {state.orders.map((order) => <RentalOrderCard key={order.id} order={order} />)}
            </ul>
            {state.nextCursor ? (
              <div className="mt-6 flex justify-center">
                <Button type="button" variant="outline" className="rounded-full" disabled={loadingMore} onClick={loadMore}>
                  {loadingMore ? 'Loading…' : 'Load more'}
                </Button>
              </div>
            ) : null}
          </>
        )}
      </div>
    </div>
  )
}

function MockOrdersPage() {
  const user = useAuthStore((state) => state.user)
  const allOrders = useOrdersStore((state) => state.orders)
  const orders = allOrders.filter((order) => order.userId === user?.id)

  if (!isMockApi) return <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6"><PageHeader title="My rentals" description="Rental history unavailable. Live orders are not connected yet." /></div>

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <PageHeader title="My rentals" description="Track upcoming and past rentals." />

      <div className="mt-8">
        {orders.length === 0 ? (
          <EmptyState
            icon={Package}
            title="No rentals yet"
            description="When you book equipment it will show up here."
            action={<LinkButton to={ROUTES.catalog}>Browse equipment</LinkButton>}
          />
        ) : (
          <ul className="space-y-4">
            {orders.map((order) => <OrderCard key={order.id} order={order} />)}
          </ul>
        )}
      </div>
    </div>
  )
}

export function OrdersPage() {
  const role = useAuthStore((state) => state.user?.role)
  if (role !== 'customer') {
    return <UnderDevelopment title="My rentals" description="The rental workspace for staff roles will arrive with the operations releases." />
  }
  return IS_ORDER_LIVE ? <LiveOrdersPage /> : <MockOrdersPage />
}
