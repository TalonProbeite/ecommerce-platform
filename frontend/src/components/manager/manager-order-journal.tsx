import { formatDateTime } from '@/lib/rental'
import type { RentalOrderEvent } from '@/types/rental-order'

function statusLabel(value: string): string {
  return value.replace(/_/g, ' ')
}

export function ManagerOrderJournal({ events }: { events: RentalOrderEvent[] }) {
  return (
    <section className="mt-4 rounded-3xl border bg-card p-6" aria-labelledby="manager-journal-heading">
      <h2 id="manager-journal-heading" className="text-lg font-bold">Journal</h2>
      <p className="mt-1 text-sm text-muted-foreground">Append-only status history for this order.</p>
      {events.length === 0 ? (
        <p className="mt-4 rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
          No events have been recorded for this order yet.
        </p>
      ) : (
        <ol className="mt-4 space-y-3 border-l pl-4">
          {events.map((event) => (
            <li key={event.id} className="text-sm">
              <p className="font-semibold">
                {event.fromStatus ? `${statusLabel(event.fromStatus)} → ${statusLabel(event.toStatus)}` : statusLabel(event.toStatus)}
              </p>
              <p className="text-muted-foreground">
                {formatDateTime(event.occurredAt)} · {statusLabel(event.actorRole)}
              </p>
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}
