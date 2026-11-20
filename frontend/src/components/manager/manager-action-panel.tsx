import { Checkbox } from '@/components/ui/checkbox'
import { Button } from '@/components/ui/button'
import { formatEurCents } from '@/lib/money'
import { formatDateTime } from '@/lib/rental'
import {
  canIssueOrder,
  canReturnOrder,
  issuableItems,
  itemStatusLabel,
  rentalHasStarted,
  returnableItems,
  returnedDepositCents,
} from '@/lib/manager-orders'
import type { RentalOrder, RentalOrderItem } from '@/types/rental-order'

interface ManagerActionPanelProps {
  mode: 'issue' | 'return'
  order: RentalOrder
  selectedIds: ReadonlySet<string>
  skippedItems?: RentalOrderItem[]
  onToggle?: (itemId: string) => void
  onSubmit?: () => void
  submitting?: boolean
  now?: Date
}

export function ManagerActionPanel({
  mode,
  order,
  selectedIds,
  skippedItems = [],
  onToggle,
  onSubmit,
  submitting = false,
  now,
}: ManagerActionPanelProps) {
  const currentTime = now ?? new Date()
  const eligible = mode === 'issue' ? issuableItems(order) : returnableItems(order, currentTime)
  const canAct = mode === 'issue' ? canIssueOrder(order) : canReturnOrder(order, currentTime)
  const selectedItems = eligible.filter((item) => selectedIds.has(item.id))
  const submitDisabled = !canAct || selectedItems.length === 0 || submitting
  const isIssue = mode === 'issue'
  const waitingForStart = !isIssue
    ? order.items.filter((item) => item.status === 'issued' && !rentalHasStarted(item, currentTime))
    : []
  const heading = isIssue ? 'Issue items' : 'Accept returns'
  const submitLabel = isIssue ? 'Issue selected items' : 'Return selected items'
  const busyLabel = isIssue ? 'Issuing…' : 'Returning…'
  const skippedStatus = isIssue ? 'Issued' : 'Returned'
  const skippedHeading = isIssue ? 'Already issued' : 'Already returned'

  return (
    <section className="mt-4 rounded-3xl border bg-card p-6" aria-labelledby={`manager-${mode}-heading`}>
      <h2 id={`manager-${mode}-heading`} className="text-lg font-bold">{heading}</h2>
      <p className="mt-1 text-sm text-muted-foreground">
        {isIssue
          ? 'Select the reserved items you are handing to the customer.'
          : 'Select the issued items the customer is returning.'}
      </p>

      {eligible.length === 0 ? (
        <p className="mt-4 rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
          {isIssue
            ? 'No reserved items are ready to be issued.'
            : waitingForStart.length > 0
              ? 'Returns open when the rental period starts.'
              : 'No issued items are ready to be returned.'}
        </p>
      ) : (
        <ul className="mt-4 divide-y">
          {eligible.map((item) => (
            <li key={item.id} className="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0 last:pb-0">
              <div className="flex min-w-0 items-start gap-3">
                <Checkbox
                  id={`manager-${mode}-item-${item.id}`}
                  checked={selectedIds.has(item.id)}
                  disabled={!canAct || submitting}
                  onCheckedChange={() => onToggle?.(item.id)}
                  aria-label={`Select ${item.productName}`}
                />
                <div className="min-w-0">
                  <label htmlFor={`manager-${mode}-item-${item.id}`} className="break-words font-semibold">
                    {item.productName} × {item.quantity}
                  </label>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {formatDateTime(item.rentalStartAt)} – {formatDateTime(item.rentalEndAt)}
                  </p>
                  <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {itemStatusLabel(item.status)} · deposit {formatEurCents(item.depositTotalCents)}
                  </p>
                </div>
              </div>
              <p className="font-semibold">{formatEurCents(item.lineTotalCents)}</p>
            </li>
          ))}
        </ul>
      )}

      {waitingForStart.length > 0 ? (
        <div role="status" className="mt-4 rounded-2xl border bg-muted/40 p-4 text-sm">
          <p className="font-semibold">Waiting for the rental to start</p>
          <p className="mt-1 text-muted-foreground">
            These items can be returned from their rental start date.
          </p>
          <ul className="mt-2 space-y-1">
            {waitingForStart.map((item) => (
              <li key={item.id} className="flex flex-wrap justify-between gap-2">
                <span className="break-words">{item.productName} × {item.quantity}</span>
                <span className="font-semibold">{formatDateTime(item.rentalStartAt)}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {skippedItems.length > 0 ? (
        <div role="status" className="mt-4 rounded-2xl border bg-muted/40 p-4 text-sm">
          <p className="font-semibold">{skippedHeading}</p>
          <p className="mt-1 text-muted-foreground">
            These items were skipped because they were already {skippedStatus.toLowerCase()}:
          </p>
          <ul className="mt-2 space-y-1">
            {skippedItems.map((item) => (
              <li key={item.id} className="flex flex-wrap justify-between gap-2">
                <span className="break-words">{item.productName} × {item.quantity}</span>
                <span className="font-semibold">{skippedStatus}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {!isIssue && selectedItems.length > 0 ? (
        <p className="mt-4 rounded-2xl border bg-muted/40 p-4 text-sm">
          Deposit to refund:{' '}
          <span className="font-semibold">{formatEurCents(returnedDepositCents(selectedItems))}</span>
        </p>
      ) : null}

      <div className="mt-5">
        <Button type="button" disabled={submitDisabled} onClick={onSubmit}>
          {submitting ? busyLabel : submitLabel}
        </Button>
      </div>
    </section>
  )
}
