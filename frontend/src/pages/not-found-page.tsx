import { Compass } from 'lucide-react'
import { ROUTES } from '@/app/routes'
import { EmptyState } from '@/components/common/empty-state'
import { LinkButton } from '@/components/common/link-button'

export function NotFoundPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-16">
      <EmptyState
        icon={Compass}
        title="Page not found"
        description="The page you are looking for does not exist or has moved."
        action={<LinkButton to={ROUTES.home}>Back to home</LinkButton>}
      />
    </div>
  )
}
