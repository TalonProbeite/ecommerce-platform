import { Package } from 'lucide-react'
import { useState } from 'react'
import { EmptyState } from '@/components/common/empty-state'
import { PageHeader } from '@/components/common/page-header'
import { ManagerOrderCard } from '@/components/manager/manager-order-card'
import { OrderErrorPanel } from '@/components/orders/order-error-panel'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdminStores } from '@/hooks/use-admin-catalog'
import { LIVE_CATALOG } from '@/hooks/use-catalog-data'
import { useRentalOrders } from '@/hooks/use-rental-order'
import { ORDER_STATUS_LABELS } from '@/lib/order-status'
import { RENTAL_ORDER_STATUSES } from '@/types/rental-order'

const STATUS_ITEMS = [
  { value: 'all', label: 'All statuses' },
  ...RENTAL_ORDER_STATUSES.map((value) => ({ value, label: ORDER_STATUS_LABELS[value] })),
]

export function StaffOrdersPage({
  title,
  description,
  orderPath,
  canFilterByStore = false,
}: {
  title: string
  description: string
  orderPath: (id: string) => string
  canFilterByStore?: boolean
}) {
  const [status, setStatus] = useState('all')
  const [storeId, setStoreId] = useState('all')
  const stores = useAdminStores(canFilterByStore && LIVE_CATALOG)
  const { state, reload, loadMore, loadingMore } = useRentalOrders(
    status === 'all' ? undefined : status,
    { storeId: storeId === 'all' ? undefined : storeId },
  )

  const storeItems = [
    { value: 'all', label: 'All stores' },
    ...(stores.state.status === 'success'
      ? stores.state.data.data.map((store) => ({ value: store.id, label: store.name }))
      : []),
  ]

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <PageHeader title={title} description={description} />
      <div className="mt-6 flex flex-col gap-3 sm:flex-row">
        <Select items={STATUS_ITEMS} value={status} onValueChange={(value) => setStatus(value ?? 'all')}>
          <SelectTrigger className="h-10 w-full rounded-full px-4 sm:w-56" aria-label="Filter by status">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {STATUS_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
          </SelectContent>
        </Select>
        {canFilterByStore ? (
          <Select items={storeItems} value={storeId} onValueChange={(value) => setStoreId(value ?? 'all')}>
            <SelectTrigger className="h-10 w-full rounded-full px-4 sm:w-64" aria-label="Filter by store">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {storeItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
            </SelectContent>
          </Select>
        ) : null}
      </div>
      <div className="mt-8">
        {state.status === 'loading' ? (
          <div role="status" aria-live="polite" className="space-y-4">
            <span className="sr-only">Loading orders...</span>
            <Skeleton className="h-40 rounded-3xl" />
            <Skeleton className="h-40 rounded-3xl" />
          </div>
        ) : state.status === 'error' ? (
          <OrderErrorPanel title="Unable to load orders" error={state.error} onRetry={reload} />
        ) : state.orders.length === 0 ? (
          <EmptyState
            icon={Package}
            title="No orders found"
            description="No orders match these filters."
          />
        ) : (
          <>
            <ul className="space-y-4">
              {state.orders.map((order) => (
                <ManagerOrderCard key={order.id} order={order} orderHref={orderPath(order.id)} />
              ))}
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
