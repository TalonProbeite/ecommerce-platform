import { Package } from 'lucide-react'
import { useState } from 'react'
import { EmptyState } from '@/components/common/empty-state'
import { PageHeader } from '@/components/common/page-header'
import { UnderDevelopment } from '@/components/common/under-development'
import { ManagerOrderCard } from '@/components/manager/manager-order-card'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useRentalOrders } from '@/hooks/use-rental-order'
import { ORDER_STATUS_LABELS } from '@/lib/order-status'
import { useAuthStore } from '@/store/auth-store'
import { RENTAL_ORDER_STATUSES } from '@/types/rental-order'

const FILTER_ITEMS = [
  { value: 'all', label: 'All statuses' },
  ...RENTAL_ORDER_STATUSES.map((value) => ({ value, label: ORDER_STATUS_LABELS[value] })),
]

function ManagerOrdersPageContent() {
  const [status, setStatus] = useState('all')
  const { state, reload, loadMore, loadingMore } = useRentalOrders(status === 'all' ? undefined : status)

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <PageHeader title="Store orders" description="Review and process the rental orders for your store." />
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
            <span className="sr-only">Loading orders...</span>
            <Skeleton className="h-40 rounded-3xl" />
            <Skeleton className="h-40 rounded-3xl" />
          </div>
        ) : state.status === 'error' ? (
          <div role="alert" className="rounded-3xl border bg-card p-6">
            <p className="font-semibold">Unable to load orders</p>
            <p className="mt-1 text-sm text-muted-foreground">{state.error}</p>
            <Button type="button" variant="outline" className="mt-4 rounded-full" onClick={reload}>Try again</Button>
          </div>
        ) : state.orders.length === 0 ? (
          <EmptyState
            icon={Package}
            title="No orders yet"
            description="Orders placed for your store will show up here."
          />
        ) : (
          <>
            <ul className="space-y-4">
              {state.orders.map((order) => <ManagerOrderCard key={order.id} order={order} />)}
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

export function ManageOrdersPage() {
  const role = useAuthStore((state) => state.user?.role)
  if (role !== 'store_manager') {
    return <UnderDevelopment title="Store orders" description="The store order workspace is available to store manager accounts." />
  }
  return <ManagerOrdersPageContent />
}
