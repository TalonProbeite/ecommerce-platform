import type { Role } from '@/types'

export type AccessFeature =
  | 'orders.read.all'
  | 'orders.read.store'
  | 'orders.read.own'
  | 'orders.manage.all'
  | 'orders.manage.store'
  | 'orders.events.all'
  | 'orders.events.store'
  | 'overdue.read'
  | 'catalog.manage.all'
  | 'catalog.manage.ownStore'

const ACCESS_BY_ROLE: Record<Role, ReadonlySet<AccessFeature>> = {
  customer: new Set<AccessFeature>(['orders.read.own']),
  store_manager: new Set<AccessFeature>([
    'orders.read.store',
    'orders.manage.store',
    'orders.events.store',
    'catalog.manage.ownStore',
  ]),
  analyst: new Set<AccessFeature>(['orders.read.all', 'orders.events.all', 'overdue.read']),
  admin: new Set<AccessFeature>([
    'orders.read.all',
    'orders.manage.all',
    'orders.events.all',
    'overdue.read',
    'catalog.manage.all',
  ]),
}

export function can(role: Role | undefined | null, feature: AccessFeature): boolean {
  return role ? ACCESS_BY_ROLE[role].has(feature) : false
}
