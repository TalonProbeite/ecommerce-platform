import { Link } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { PageHeader } from '@/components/common/page-header'
import { OrderErrorPanel } from '@/components/orders/order-error-panel'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useOverdueRentals } from '@/hooks/use-overdue-rentals'
import { formatDateTime } from '@/lib/rental'

export function AnalyticsOverduePage() {
  const { state, reload, loadMore, loadingMore } = useOverdueRentals()

  return (
    <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
      <PageHeader title="Overdue rentals" description="Issued items whose rental period has ended." />
      <div className="mt-8">
        {state.status === 'loading' ? (
          <div role="status" aria-live="polite" className="space-y-4">
            <span className="sr-only">Loading overdue rentals...</span>
            <Skeleton className="h-40 rounded-3xl" />
          </div>
        ) : state.status === 'error' ? (
          <OrderErrorPanel title="Unable to load overdue rentals" error={state.error} onRetry={reload} />
        ) : state.items.length === 0 ? (
          <p className="rounded-3xl border border-dashed bg-card p-6 text-sm text-muted-foreground">
            No rentals are overdue.
          </p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-3xl border bg-card">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="pl-5">Order</TableHead>
                    <TableHead>Product</TableHead>
                    <TableHead>Store</TableHead>
                    <TableHead>Customer</TableHead>
                    <TableHead>Ended</TableHead>
                    <TableHead className="pr-5 text-right">Overdue</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {state.items.map((item) => (
                    <TableRow key={item.itemId}>
                      <TableCell className="pl-5">
                        <Link to={ROUTES.analyticsOrder(item.orderId)} className="break-all font-semibold underline-offset-4 hover:underline">
                          {item.orderId}
                        </Link>
                      </TableCell>
                      <TableCell>{item.productName} × {item.quantity}</TableCell>
                      <TableCell><span className="break-all font-mono text-xs">{item.storeId}</span></TableCell>
                      <TableCell><span className="break-all font-mono text-xs">{item.customerId}</span></TableCell>
                      <TableCell>{formatDateTime(item.rentalEndAt)}</TableCell>
                      <TableCell className="pr-5 text-right font-semibold">
                        {item.overdueHours} {item.overdueHours === 1 ? 'hour' : 'hours'}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
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
