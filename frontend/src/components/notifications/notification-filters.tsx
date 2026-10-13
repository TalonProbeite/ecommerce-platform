import { FormField } from '@/components/auth/form-field'
import { Label } from '@/components/ui/label'
import { NOTIFICATION_CHANNELS, NOTIFICATION_STATUSES, type NotificationFilters } from '@/types/notifications'

export function NotificationFilterControls({ filters, change, error }: { filters: NotificationFilters; change: (changes: NotificationFilters) => void; error?: string }) {
  return <div className="mt-8 rounded-2xl border bg-card p-5">
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
      {(['status', 'channel'] as const).map(key => <div key={key} className="space-y-1.5">
        <Label htmlFor={`notification-${key}`} className="capitalize">{key}</Label>
        <select id={`notification-${key}`} value={filters[key] ?? ''} onChange={event => change({ [key]: event.target.value })} className="h-11 w-full rounded-lg border bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring">
          <option value="">All {key === 'status' ? 'statuses' : 'channels'}</option>
          {(key === 'status' ? NOTIFICATION_STATUSES : NOTIFICATION_CHANNELS).map(value => <option key={value} value={value}>{value}</option>)}
        </select>
      </div>)}
      <FormField id="notification-recipient" label="Recipient" value={filters.recipient ?? ''} onChange={event => change({ recipient: event.target.value })} placeholder="Exact email address" />
      <FormField id="notification-start" label="Start date" type="datetime-local" value={filters.start_date ?? ''} onChange={event => change({ start_date: event.target.value })} error={error} />
      <FormField id="notification-end" label="End date" type="datetime-local" value={filters.end_date ?? ''} onChange={event => change({ end_date: event.target.value })} error={error} />
    </div>
    <p className="mt-3 text-xs text-muted-foreground">Dates use your local time zone.</p>
  </div>
}
