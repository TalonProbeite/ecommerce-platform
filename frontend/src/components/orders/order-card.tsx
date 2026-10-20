import { Badge } from '@/components/ui/badge'
import { getEquipmentById } from '@/data/equipment'
import { formatCurrency, formatDate } from '@/lib/pricing'
import { cn } from '@/lib/utils'
import type { Order, OrderStatus } from '@/types'

const STATUS_STYLES: Record<OrderStatus, string> = {
  confirmed: 'bg-secondary text-secondary-foreground',
  active: 'bg-primary text-primary-foreground',
  returned: 'bg-success/15 text-success',
  cancelled: 'bg-destructive/10 text-destructive',
}

export function OrderCard({ order }: { order: Order }) {
  return (
    <li className="rounded-3xl border bg-card p-5 sm:p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="font-bold">{order.id}</p>
          <p className="text-sm text-muted-foreground">
            Placed {formatDate(order.createdAt)} &middot; {order.fulfillment === 'pickup' ? 'Pickup' : 'Delivery'}
          </p>
        </div>
        <Badge className={cn('rounded-full px-3 capitalize', STATUS_STYLES[order.status])}>{order.status}</Badge>
      </div>

      <ul className="mt-4 divide-y">
        {order.lines.map((line) => {
          const equipment = getEquipmentById(line.equipmentId)
          return (
            <li key={line.equipmentId} className="flex items-center gap-4 py-3">
              {equipment ? (
                <img src={equipment.image} alt="" className="size-14 shrink-0 rounded-xl object-cover" />
              ) : null}
              <div className="min-w-0 flex-1">
                <p className="truncate font-semibold">{equipment?.name ?? 'Removed listing'}</p>
                <p className="text-sm text-muted-foreground">
                  {formatDate(line.startDate)} &ndash; {formatDate(line.endDate)}
                </p>
              </div>
              <p className="font-semibold">{formatCurrency(line.days * line.pricePerDay)}</p>
            </li>
          )
        })}
      </ul>

      <p className="mt-3 flex justify-between border-t pt-4 font-bold">
        <span>Total</span>
        <span>{formatCurrency(order.total)}</span>
      </p>
    </li>
  )
}
