import { Bike, Box, Camera, MonitorSpeaker, Tent, Wrench, type LucideIcon } from 'lucide-react'
import type { CategoryId } from '@/types'

const ICONS: Record<string, LucideIcon> = {
  cameras: Camera,
  'audio-video': MonitorSpeaker,
  tools: Wrench,
  outdoor: Tent,
  mobility: Bike,
}

// Keep the original icon set for fixture categories and fall back to a neutral
// icon for live backend categories (which use server-side UUIDs).
export function CategoryIcon({ category, className }: { category: CategoryId; className?: string }) {
  const Icon = ICONS[category] ?? Box
  return <Icon className={className} aria-hidden="true" />
}
