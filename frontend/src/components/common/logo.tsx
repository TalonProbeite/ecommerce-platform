import { Link } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { cn } from '@/lib/utils'

export function Logo({ className }: { className?: string }) {
  return (
    <Link to={ROUTES.home} className={cn('inline-flex items-center gap-2 font-extrabold tracking-tight', className)}>
      <span className="bg-brand-gradient grid size-8 place-items-center rounded-xl text-sm text-primary-foreground">
        R
      </span>
      <span className="text-lg">Rentify</span>
    </Link>
  )
}
