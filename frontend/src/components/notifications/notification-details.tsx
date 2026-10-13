import { useCallback, useState } from 'react'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { getNotification } from '@/services/notifications'
import { useNotificationResource } from '@/hooks/use-notification-resource'
import { ResourceFeedback } from './resource-feedback'

function DetailContent({ id }: { id: string }) {
  const [retry, setRetry] = useState(0)
  const load = useCallback((signal: AbortSignal) => getNotification(id, signal), [id])
  const resource = useNotificationResource(load, retry)
  return <>
    <ResourceFeedback resource={resource} retry={() => setRetry(value => value + 1)} />
    {resource.state === 'success' ? <div className="space-y-5">
      <h2 className="font-semibold">{resource.data.type}</h2>
      <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3">
        {Object.entries({ ID: resource.data.id, Attempts: resource.data.attempts, Recipient: resource.data.recipient, Status: resource.data.status, Channel: resource.data.channel, Date: new Date(resource.data.created_at).toLocaleString(), ...(resource.data.error ? { 'Delivery error': resource.data.error } : {}) }).map(([label, value]) => <div key={label} className="contents"><dt className="text-muted-foreground">{label}</dt><dd className="break-words">{value}</dd></div>)}
      </dl>
      <div><h3 className="mb-2 font-medium">Event payload</h3><pre className="whitespace-pre-wrap break-words rounded-xl bg-muted p-4 text-sm">{JSON.stringify(resource.data.payload, null, 2)}</pre></div>
    </div> : null}
  </>
}

export function NotificationDetails({ id, close }: { id: string | null; close: () => void }) {
  return <Dialog open={id !== null} onOpenChange={open => { if (!open) close() }}>
    <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-lg">
      <DialogHeader><DialogTitle>Notification details</DialogTitle><DialogDescription>Delivery journal record</DialogDescription></DialogHeader>
      {id ? <DetailContent key={id} id={id} /> : null}
    </DialogContent>
  </Dialog>
}
