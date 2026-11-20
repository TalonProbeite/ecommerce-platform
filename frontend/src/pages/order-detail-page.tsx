import { ArrowLeft, CreditCard, Package } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { ROUTES } from '@/app/routes'
import { cancelRentalOrder, orderErrorMessage } from '@/api/order-client'
import { EmptyState } from '@/components/common/empty-state'
import { LinkButton } from '@/components/common/link-button'
import { PageHeader } from '@/components/common/page-header'
import { OrderMetadata } from '@/components/orders/order-metadata'
import { StatusBadge } from '@/components/rental/status-badge'
import { UnderDevelopment } from '@/components/common/under-development'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useRentalOrder } from '@/hooks/use-rental-order'
import { useAuthStore } from '@/store/auth-store'
import { formatEurCents } from '@/lib/money'
import { formatDateTime } from '@/lib/rental'
import { cancelReasonMessage, canCancel, canPay, ITEM_STATUS_LABELS, ORDER_STATUS_LABELS } from '@/lib/order-status'

function CustomerOrderDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { state, reload } = useRentalOrder(id, true)
  const [cancelling, setCancelling] = useState(false)
  const [cancelError, setCancelError] = useState('')

  if (state.status === 'loading') {
    return <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6" role="status" aria-live="polite"><span className="sr-only">Loading order...</span><Skeleton className="h-64 rounded-3xl" /></div>
  }

  if (state.status === 'error') {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <div role="alert" className="rounded-3xl border bg-card p-6">
          <p className="font-semibold">Unable to load this order</p>
          <p className="mt-1 text-sm text-muted-foreground">{state.error}</p>
          <Button type="button" variant="outline" className="mt-4 rounded-full" onClick={reload}>Try again</Button>
        </div>
      </div>
    )
  }

  const order = state.order
  const cancellationMessage = cancelReasonMessage(order.cancelReason)
  const waiting = order.status === 'created'

  async function handleCancel() {
    if (cancelling || !canCancel(order.status)) return
    if (!window.confirm('Cancel this order? This cannot be undone.')) return
    setCancelling(true)
    setCancelError('')
    try {
      await cancelRentalOrder(order.id)
      toast.success('Order cancelled.')
      reload()
      navigate(ROUTES.order(order.id), { replace: true })
    } catch (error) {
      setCancelError(orderErrorMessage(error, 'Could not cancel this order.'))
    } finally {
      setCancelling(false)
    }
  }

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <Link to={ROUTES.orders} className="inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" /> Back to my rentals
      </Link>
      <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
        <PageHeader title="Order details" description={order.id} />
        <StatusBadge status={order.status} label={ORDER_STATUS_LABELS[order.status]} />
      </div>

      {waiting ? (
        <p role="status" className="mt-6 rounded-2xl border bg-card p-4 text-sm text-muted-foreground">
          We are reserving the equipment. This page refreshes automatically.
        </p>
      ) : null}

      {order.status === 'cancelled' ? (
        <p role="alert" className="mt-6 rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">
          {cancellationMessage ?? 'This order was cancelled.'}
        </p>
      ) : null}

      {order.status === 'paid' ? (
        <p role="status" className="mt-6 rounded-2xl border bg-card p-4 text-sm text-muted-foreground">
          Payment received. The provider will prepare your equipment.
        </p>
      ) : null}

      <OrderMetadata order={order} />

      <section className="mt-4 rounded-3xl border bg-card p-6" aria-labelledby="order-items-heading">
        <h2 id="order-items-heading" className="text-lg font-bold">Items</h2>
        <ul className="mt-4 divide-y">
          {order.items.map((item) => (
            <li key={item.id} className="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0 last:pb-0">
              <div className="min-w-0">
                <Link to={ROUTES.equipment(item.productId)} className="break-words font-semibold underline-offset-4 hover:underline">
                  {item.productName} × {item.quantity}
                </Link>
                <p className="mt-1 text-sm text-muted-foreground">
                  {formatDateTime(item.rentalStartAt)} – {formatDateTime(item.rentalEndAt)} · {item.billedHours} {item.billedHours === 1 ? 'hour' : 'hours'}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {formatEurCents(item.pricePerHourCents)} / hour · deposit {formatEurCents(item.depositPerUnitCents)} / unit ({formatEurCents(item.depositTotalCents)} total)
                </p>
                <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {ITEM_STATUS_LABELS[item.status]}
                  {item.issuedAt ? ` · issued ${formatDateTime(item.issuedAt)}` : ''}
                  {item.returnedAt ? ` · returned ${formatDateTime(item.returnedAt)}` : ''}
                </p>
              </div>
              <p className="font-semibold">{formatEurCents(item.lineTotalCents)}</p>
            </li>
          ))}
        </ul>
      </section>

      <section className="mt-4 rounded-3xl border bg-card p-6" aria-labelledby="order-total-heading">
        <h2 id="order-total-heading" className="text-lg font-bold">Total</h2>
        <dl className="mt-3 space-y-2 text-sm">
          <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Rental subtotal</dt><dd>{formatEurCents(order.subtotalAmount)}</dd></div>
          <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Refundable deposit</dt><dd>{formatEurCents(order.depositAmount)}</dd></div>
          <div className="flex justify-between gap-3 border-t pt-3 text-base font-bold"><dt>Total</dt><dd>{formatEurCents(order.totalAmount)}</dd></div>
        </dl>
      </section>

      {order.status === 'paid' && canCancel(order.status) ? (
        <p className="mt-4 rounded-2xl border bg-card p-4 text-sm text-muted-foreground">
          This order is already paid. Cancelling it will start a refund to the original payment method.
        </p>
      ) : null}

      {cancelError ? <p role="alert" className="mt-4 rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">{cancelError}</p> : null}

      <div className="mt-6 flex flex-col gap-3 sm:flex-row">
        {canPay(order.status) ? (
          <LinkButton to={ROUTES.orderPayment(order.id)} className="gap-2"><CreditCard className="size-4" /> Pay {formatEurCents(order.totalAmount)}</LinkButton>
        ) : null}
        {canCancel(order.status) ? (
          <Button type="button" variant="outline" disabled={cancelling} onClick={handleCancel}>
            {cancelling ? 'Cancelling…' : 'Cancel order'}
          </Button>
        ) : null}
        <LinkButton to={ROUTES.orders} variant="outline">Back to my rentals</LinkButton>
      </div>
    </div>
  )
}

export function OrderDetailPage() {
  const role = useAuthStore((state) => state.user?.role)
  if (role !== 'customer') {
    return <UnderDevelopment title="Order details" description="Order details are available to customer accounts in this release." />
  }
  return <CustomerOrderDetailPage />
}
