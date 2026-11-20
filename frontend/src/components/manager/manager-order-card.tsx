import { Link } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { StatusBadge } from '@/components/rental/status-badge'
import { formatEurCents } from '@/lib/money'
import { formatDateTime } from '@/lib/rental'
import { ORDER_STATUS_LABELS } from '@/lib/order-status'
import type { RentalOrder } from '@/types/rental-order'

export function ManagerOrderCard({ order, orderHref }: { order: RentalOrder; orderHref?: string }) {
  const itemCount = order.items.reduce((total, item) => total + item.quantity, 0)
  const firstStart = order.items[0]?.rentalStartAt
  const lastEnd = order.items.reduce(
    (latest, item) => (item.rentalEndAt > latest ? item.rentalEndAt : latest),
    order.items[0]?.rentalEndAt ?? '',
  )
  const href = orderHref ?? ROUTES.manageOrder(order.id)

  return (
    <li className="rounded-3xl border bg-card p-5 sm:p-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <Link to={href} className="break-all font-bold underline-offset-4 hover:underline">
            {order.id}
          </Link>
          <p className="mt-1 text-sm text-muted-foreground">
            {formatDateTime(order.createdAt)} · {itemCount} {itemCount === 1 ? 'item' : 'items'}
          </p>
        </div>
        <StatusBadge status={order.status} label={ORDER_STATUS_LABELS[order.status]} />
      </div>

      <div className="mt-4 flex flex-wrap items-end justify-between gap-3 border-t pt-4">
        <p className="text-sm text-muted-foreground">
          {firstStart && lastEnd ? `${formatDateTime(firstStart)} – ${formatDateTime(lastEnd)}` : 'Rental dates unavailable'}
        </p>
        <p className="font-bold">Total {formatEurCents(order.totalAmount)}</p>
      </div>
    </li>
  )
}
