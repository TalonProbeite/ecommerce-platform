import { ManagerActionPanel } from '@/components/manager/manager-action-panel'
import { Button } from '@/components/ui/button'
import { canCancelOrder } from '@/lib/manager-orders'
import type { RentalOrder, RentalOrderItem } from '@/types/rental-order'

export function StaffOrderActions({
  order,
  canManage,
  busy,
  pendingAction,
  issueSelection,
  returnSelection,
  skippedIssueItems,
  skippedReturnItems,
  onToggleIssue,
  onToggleReturn,
  onSubmitIssue,
  onSubmitReturn,
  onCancel,
}: {
  order: RentalOrder
  canManage: boolean
  busy: boolean
  pendingAction: 'issue' | 'return' | 'cancel' | null
  issueSelection: ReadonlySet<string>
  returnSelection: ReadonlySet<string>
  skippedIssueItems: RentalOrderItem[]
  skippedReturnItems: RentalOrderItem[]
  onToggleIssue: (itemId: string) => void
  onToggleReturn: (itemId: string) => void
  onSubmitIssue: () => void
  onSubmitReturn: () => void
  onCancel: () => void
}) {
  if (!canManage) return null

  return (
    <>
      {canCancelOrder(order) ? (
        <section className="mt-4 rounded-3xl border bg-card p-6" aria-labelledby="staff-cancel-heading">
          <h2 id="staff-cancel-heading" className="text-lg font-bold">Cancel order</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {order.status === 'paid'
              ? 'This order is already paid. Cancelling it will start a refund to the customer.'
              : 'Cancelling is possible until the first item is issued.'}
          </p>
          <div className="mt-4">
            <Button type="button" variant="destructive" disabled={busy} onClick={onCancel}>
              {pendingAction === 'cancel' ? 'Cancelling…' : 'Cancel order'}
            </Button>
          </div>
        </section>
      ) : null}

      <ManagerActionPanel
        mode="issue"
        order={order}
        selectedIds={issueSelection}
        skippedItems={skippedIssueItems}
        onToggle={onToggleIssue}
        onSubmit={onSubmitIssue}
        submitting={pendingAction === 'issue'}
      />

      <ManagerActionPanel
        mode="return"
        order={order}
        selectedIds={returnSelection}
        skippedItems={skippedReturnItems}
        onToggle={onToggleReturn}
        onSubmit={onSubmitReturn}
        submitting={pendingAction === 'return'}
      />
    </>
  )
}
