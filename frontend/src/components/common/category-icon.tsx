import { Bike, Camera, MonitorSpeaker, Tent, Wrench, type LucideIcon } from 'lucide-react'
import type { CategoryId } from '@/types'

const ICONS: Record<CategoryId, LucideIcon> = {
  cameras: Camera,
  'audio-video': MonitorSpeaker,
  tools: Wrench,
  outdoor: Tent,
  mobility: Bike,
}

export function CategoryIcon({ category, className }: { category: CategoryId; className?: string }) {
  const Icon = ICONS[category]
  return <Icon className={className} aria-hidden="true" />
}
