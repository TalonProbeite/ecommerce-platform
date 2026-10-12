import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/store/auth-store'

export function SessionFeedback() {
  const message = useAuthStore(state => state.sessionError)
  const reloadProfile = useAuthStore(state => state.reloadProfile)
  return <div className="mx-auto max-w-3xl px-4 py-16" role="alert">
    <h1 className="text-3xl font-bold">Account unavailable</h1>
    <p className="mt-3 text-muted-foreground">{message ?? 'Could not load your account. Please try again.'}</p>
    <Button className="mt-5" onClick={() => void reloadProfile()}>Try again</Button>
  </div>
}
