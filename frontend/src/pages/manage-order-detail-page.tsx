import { ArrowLeft, Package } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { ROUTES } from '@/app/routes'
import { cancelRentalOrder, issueRentalOrderItems, returnRentalOrderItems } from '@/api/order-client'
import { EmptyState } from '@/components/common/empty-state'
import { PageHeader } from '@/components/common/page-header'
import { UnderDevelopment } from '@/components/common/under-development'
import { ManagerActionPanel } from '@/components/manager/manager-action-panel'
import { ManagerOrderJournal } from '@/components/manager/manager-order-journal'
import { StatusBadge } from '@/components/rental/status-badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useOrderEvents } from '@/hooks/use-order-events'
import { useRentalOrder } from '@/hooks/use-rental-order'
import { ITEM_STATUS_LABELS, ORDER_STATUS_LABELS } from '@/lib/order-status'
import { managerActionErrorMessage } from '@/lib/order-error'
import { canCancelOrder, issuableItems, returnableItems, selectedEligibleIds, toggleSelection } from '@/lib/manager-orders'
import { formatEurCents } from '@/lib/money'
import { formatDateTime } from '@/lib/rental'
import { useAuthStore } from '@/store/auth-store'
import type { RentalOrderItem } from '@/types/rental-order'

function ManagerOrderDetailPageContent() {
  const { id } = useParams()
  const { state, reload } = useRentalOrder(id)
  const events = useOrderEvents(id)
  const [issueSelection, setIssueSelection] = useState<ReadonlySet<string>>(new Set())
  const [returnSelection, setReturnSelection] = useState<ReadonlySet<string>>(new Set())
  const [skippedIssueIds, setSkippedIssueIds] = useState<string[]>([])
  const [skippedReturnIds, setSkippedReturnIds] = useState<string[]>([])
  const [pendingAction, setPendingAction] = useState<'issue' | 'return' | 'cancel' | null>(null)
  const [actionError, setActionError] = useState('')

  if (state.status === 'loading') {
    return (
      <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6" role="status" aria-live="polite">
        <span className="sr-only">Loading order...</span>
        <Skeleton className="h-64 rounded-3xl" />
      </div>
    )
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
  const busy = pendingAction !== null

  function resolveItems(ids: string[]): RentalOrderItem[] {
    const byId = new Map(order.items.map((item) => [item.id, item]))
    return ids.map((itemId) => byId.get(itemId)).filter((item): item is RentalOrderItem => item !== undefined)
  }

  async function runAction(action: 'issue' | 'return' | 'cancel') {
    if (busy) return
    setActionError('')
    setPendingAction(action)
    try {
      if (action === 'issue') {
        const itemIds = selectedEligibleIds(issueSelection, issuableItems(order))
        if (itemIds.length === 0) return
        const result = await issueRentalOrderItems(order.id, itemIds)
        setSkippedIssueIds(result.skippedItemIds)
        setIssueSelection(new Set())
        toast.success('Items issued.')
      } else if (action === 'return') {
        const itemIds = selectedEligibleIds(returnSelection, returnableItems(order))
        if (itemIds.length === 0) return
        const result = await returnRentalOrderItems(order.id, itemIds)
        setSkippedReturnIds(result.skippedItemIds)
        setReturnSelection(new Set())
        toast.success('Items returned.')
      } else {
        if (order.status === 'paid' && !window.confirm('This order is already paid. Cancelling it will refund the customer. Continue?')) return
        else if (order.status !== 'paid' && !window.confirm('Cancel this order? This cannot be undone.')) return
        await cancelRentalOrder(order.id)
        toast.success('Order cancelled.')
      }
      reload()
      events.reload()
    } catch (error) {
      setActionError(managerActionErrorMessage(error))
    } finally {
      setPendingAction(null)
    }
  }

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <Link to={ROUTES.manageOrders} className="inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" /> Back to store orders
      </Link>
      <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
        <PageHeader title="Order details" description={order.id} />
        <StatusBadge status={order.status} label={ORDER_STATUS_LABELS[order.status]} />
      </div>

      <section className="mt-8 rounded-3xl border bg-card p-6" aria-labelledby="manager-items-heading">
        <h2 id="manager-items-heading" className="text-lg font-bold">Items</h2>
        <ul className="mt-4 divide-y">
          {order.items.map((item) => (
            <li key={item.id} className="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0 last:pb-0">
              <div className="min-w-0">
                <p className="break-words font-semibold">{item.productName} × {item.quantity}</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {formatDateTime(item.rentalStartAt)} – {formatDateTime(item.rentalEndAt)} · {item.billedHours} {item.billedHours === 1 ? 'hour' : 'hours'}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {formatEurCents(item.pricePerHourCents)} / hour · deposit {formatEurCents(item.depositTotalCents)}
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

      <section className="mt-4 rounded-3xl border bg-card p-6" aria-labelledby="manager-total-heading">
        <h2 id="manager-total-heading" className="text-lg font-bold">Total</h2>
        <dl className="mt-3 space-y-2 text-sm">
          <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Rental subtotal</dt><dd>{formatEurCents(order.subtotalAmount)}</dd></div>
          <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Refundable deposit</dt><dd>{formatEurCents(order.depositAmount)}</dd></div>
          <div className="flex justify-between gap-3 border-t pt-3 text-base font-bold"><dt>Total</dt><dd>{formatEurCents(order.totalAmount)}</dd></div>
        </dl>
      </section>

      {actionError ? <p role="alert" className="mt-4 rounded-2xl bg-destructive/10 p-4 text-sm text-destructive">{actionError}</p> : null}

      {canCancelOrder(order) ? (
        <section className="mt-4 rounded-3xl border bg-card p-6" aria-labelledby="manager-cancel-heading">
          <h2 id="manager-cancel-heading" className="text-lg font-bold">Cancel order</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {order.status === 'paid'
              ? 'This order is already paid. Cancelling it will start a refund to the customer.'
              : 'Cancelling is possible until the first item is issued.'}
          </p>
          <div className="mt-4">
            <Button type="button" variant="destructive" disabled={busy} onClick={() => runAction('cancel')}>
              {pendingAction === 'cancel' ? 'Cancelling…' : 'Cancel order'}
            </Button>
          </div>
        </section>
      ) : null}

      <ManagerActionPanel
        mode="issue"
        order={order}
        selectedIds={issueSelection}
        skippedItems={resolveItems(skippedIssueIds)}
        onToggle={(itemId) => setIssueSelection((current) => toggleSelection(current, itemId))}
        onSubmit={() => runAction('issue')}
        submitting={pendingAction === 'issue'}
      />

      <ManagerActionPanel
        mode="return"
        order={order}
        selectedIds={returnSelection}
        skippedItems={resolveItems(skippedReturnIds)}
        onToggle={(itemId) => setReturnSelection((current) => toggleSelection(current, itemId))}
        onSubmit={() => runAction('return')}
        submitting={pendingAction === 'return'}
      />

      {events.state.status === 'loading' ? (
        <div className="mt-4" role="status" aria-live="polite"><span className="sr-only">Loading journal...</span><Skeleton className="h-40 rounded-3xl" /></div>
      ) : events.state.status === 'error' ? (
        <div className="mt-4 rounded-3xl border bg-card p-6" role="alert">
          <p className="font-semibold">Unable to load the journal</p>
          <p className="mt-1 text-sm text-muted-foreground">{events.state.error}</p>
          <Button type="button" variant="outline" className="mt-4 rounded-full" onClick={events.reload}>Try again</Button>
        </div>
      ) : (
        <>
          <ManagerOrderJournal events={events.state.events} />
          {events.state.nextCursor ? (
            <div className="mt-4 flex justify-center">
              <Button type="button" variant="outline" className="rounded-full" disabled={events.loadingMore} onClick={events.loadMore}>
                {events.loadingMore ? 'Loading…' : 'Load more events'}
              </Button>
            </div>
          ) : null}
        </>
      )}

      {order.items.length === 0 ? (
        <div className="mt-4">
          <EmptyState icon={Package} title="No items" description="This order has no items." />
        </div>
      ) : null}
    </div>
  )
}

export function ManageOrderDetailPage() {
  const role = useAuthStore((state) => state.user?.role)
  if (role !== 'store_manager') {
    return <UnderDevelopment title="Order details" description="The store order workspace is available to store manager accounts." />
  }
  return <ManagerOrderDetailPageContent />
}
