import { useCallback, useState } from 'react'
import { Bell } from 'lucide-react'
import { PageHeader } from '@/components/common/page-header'
import { EmptyState } from '@/components/common/empty-state'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { NotificationDetails } from '@/components/notifications/notification-details'
import { NotificationFilterControls } from '@/components/notifications/notification-filters'
import { ResourceFeedback } from '@/components/notifications/resource-feedback'
import { buildNotificationQuery, changeNotificationFilters, listNotifications } from '@/services/notifications'
import { useNotificationResource } from '@/hooks/use-notification-resource'
import type { NotificationFilters } from '@/types/notifications'

const initialFilters: NotificationFilters = { limit: 20, offset: 0 }
const formatter = new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })

export function NotificationsPage() {
  const [filters, setFilters] = useState(initialFilters)
  const [retry, setRetry] = useState(0)
  const [selected, setSelected] = useState<string | null>(null)
  let validationError: string | undefined
  try { buildNotificationQuery(filters) } catch (error) { validationError = (error as Error).message }
  const load = useCallback((signal: AbortSignal) => listNotifications(filters, signal), [filters])
  const resource = useNotificationResource(load, retry)
  const change = (changes: NotificationFilters) => setFilters(current => changeNotificationFilters(current, changes))
  const limit = filters.limit ?? 20, offset = filters.offset ?? 0
  return <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
    <PageHeader title="Notifications" description="Delivery history and notification journal." />
    <NotificationFilterControls filters={filters} change={change} error={validationError} />
    <div className="my-4 flex flex-wrap items-center justify-between gap-3">
      <Button variant="outline" onClick={() => setFilters(initialFilters)}>Reset filters</Button>
      <label className="flex items-center gap-2 text-sm">Per page<select className="h-10 rounded-lg border bg-background px-3 focus-visible:outline-2 focus-visible:outline-ring" value={limit} onChange={event => change({ limit: Number(event.target.value) })}>{[20, 50, 100].map(size => <option key={size}>{size}</option>)}</select></label>
    </div>
    {validationError ? <p role="alert" className="my-5 text-sm text-destructive">{validationError}</p> : <>
      <ResourceFeedback resource={resource} retry={() => setRetry(value => value + 1)} />
      {resource.state === 'success' ? <>
        {resource.data.items.length === 0 ? <EmptyState icon={Bell} title="No notifications found" description="Try changing or clearing your filters." /> : <div className="overflow-hidden rounded-2xl border bg-card">
          <Table>
            <caption className="sr-only">Notification delivery history. Open a record to view details.</caption>
            <TableHeader><TableRow><TableHead className="pl-5">ID / notification</TableHead><TableHead>Status</TableHead><TableHead>Channel</TableHead><TableHead>Recipient</TableHead><TableHead>Date</TableHead></TableRow></TableHeader>
            <TableBody>{resource.data.items.map(item => <TableRow key={item.id} className="cursor-pointer" onClick={() => setSelected(item.id)}>
              <TableCell className="pl-5"><button className="rounded text-left text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring" onClick={() => setSelected(item.id)} aria-label={`View notification ${item.id}`}><span className="block font-semibold">{item.title}</span><span className="text-xs text-muted-foreground">{item.id}</span></button></TableCell>
              <TableCell><Badge variant="outline" className={item.status === 'failed' ? 'text-destructive' : item.status === 'sent' ? 'text-success' : 'text-muted-foreground'}>{item.status}</Badge></TableCell>
              <TableCell className="uppercase">{item.channel}</TableCell><TableCell>{item.recipient}</TableCell><TableCell><time dateTime={item.createdAt}>{formatter.format(new Date(item.createdAt))}</time></TableCell>
            </TableRow>)}</TableBody>
          </Table>
        </div>}
        <nav aria-label="Notification pagination" className="mt-5 flex flex-wrap items-center justify-between gap-4">
          <p className="text-sm text-muted-foreground" role="status">{resource.data.total === 0 ? '0 results' : `${Math.min(offset + 1, resource.data.total)}–${Math.min(offset + limit, resource.data.total)} of ${resource.data.total}`} · Page {Math.floor(offset / limit) + 1}</p>
          <div className="flex gap-2"><Button variant="outline" disabled={offset === 0} onClick={() => setFilters(current => ({ ...current, offset: Math.max(0, offset - limit) }))}>Previous</Button><Button variant="outline" disabled={offset + limit >= resource.data.total} onClick={() => setFilters(current => ({ ...current, offset: offset + limit }))}>Next</Button></div>
        </nav>
      </> : null}
    </>}
    <NotificationDetails id={selected} close={() => setSelected(null)} />
  </div>
}
