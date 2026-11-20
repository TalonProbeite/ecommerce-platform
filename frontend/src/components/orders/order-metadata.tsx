import { formatDateTime } from '@/lib/rental'
import type { RentalOrder } from '@/types/rental-order'

export function OrderMetadata({
  order,
  showInternalIds = false,
}: {
  order: RentalOrder
  showInternalIds?: boolean
}) {
  return (
    <section className="mt-4 rounded-3xl border bg-card p-6" aria-labelledby="order-info-heading">
      <h2 id="order-info-heading" className="text-lg font-bold">Order info</h2>
      <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-muted-foreground">Placed</dt>
          <dd>{formatDateTime(order.createdAt)}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Last updated</dt>
          <dd>{formatDateTime(order.updatedAt)}</dd>
        </div>
        {order.cancelledAt ? (
          <div>
            <dt className="text-muted-foreground">Cancelled</dt>
            <dd>{formatDateTime(order.cancelledAt)}</dd>
          </div>
        ) : null}
        {order.completedAt ? (
          <div>
            <dt className="text-muted-foreground">Completed</dt>
            <dd>{formatDateTime(order.completedAt)}</dd>
          </div>
        ) : null}
        {showInternalIds ? (
          <>
            <div className="sm:col-span-2">
              <dt className="text-muted-foreground">Customer ID</dt>
              <dd className="break-all font-mono text-xs">{order.customerId}</dd>
            </div>
            <div className="sm:col-span-2">
              <dt className="text-muted-foreground">Store ID</dt>
              <dd className="break-all font-mono text-xs">{order.storeId}</dd>
            </div>
          </>
        ) : null}
      </dl>
    </section>
  )
}
