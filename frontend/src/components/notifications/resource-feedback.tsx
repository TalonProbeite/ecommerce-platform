import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { NotificationResource } from '@/hooks/use-notification-resource'

export function ResourceFeedback({ resource, retry }: { resource: NotificationResource<unknown>; retry: () => void }) {
  if (resource.state === 'loading') return <div className="flex items-center gap-3 py-8" role="status"><Loader2 className="size-5 animate-spin" aria-hidden="true" />Loading notifications…</div>
  if (resource.state === 'error') return <div className="space-y-4 rounded-xl border border-destructive/30 p-5" role="alert"><p>{resource.message}</p><Button variant="outline" onClick={retry}>Try again</Button></div>
  return null
}
