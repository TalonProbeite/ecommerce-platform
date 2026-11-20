import { Button } from '@/components/ui/button'

export function isAccessDeniedMessage(message: string): boolean {
  return /access|permission|forbidden/i.test(message)
}

export function OrderErrorPanel({
  title,
  error,
  onRetry,
}: {
  title: string
  error: string
  onRetry?: () => void
}) {
  const accessDenied = isAccessDeniedMessage(error)

  return (
    <div role="alert" className="rounded-3xl border bg-card p-6">
      <p className="font-semibold">{accessDenied ? 'No access' : title}</p>
      <p className="mt-1 text-sm text-muted-foreground">
        {accessDenied ? 'You do not have access to this page.' : error}
      </p>
      {onRetry ? (
        <Button type="button" variant="outline" className="mt-4 rounded-full" onClick={onRetry}>
          Try again
        </Button>
      ) : null}
    </div>
  )
}
