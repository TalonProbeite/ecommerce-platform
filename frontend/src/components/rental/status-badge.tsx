import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { orderStatusTone } from '@/lib/order-status'
import type { RentalOrderStatus } from '@/types/rental-order'

const TONE_CLASSES = {
  neutral: 'bg-secondary text-secondary-foreground',
  progress: 'bg-primary text-primary-foreground',
  success: 'bg-success/15 text-success',
  danger: 'bg-destructive/10 text-destructive',
} as const

export function StatusBadge({ status, label }: { status: RentalOrderStatus; label: string }) {
  return <Badge className={cn('rounded-full px-3', TONE_CLASSES[orderStatusTone(status)])}>{label}</Badge>
}
