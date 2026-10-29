import { ROUTES } from '@/app/routes'
import type { Role, User } from '@/types'

export interface NavItem {
  to: string
  label: string
}

export const NAV_BY_ROLE: Record<Role, NavItem[]> = {
  customer: [
    { to: ROUTES.catalog, label: 'Catalog' },
  ],
  analyst: [{ to: ROUTES.notifications, label: 'Notifications' }],
  admin: [
    { to: ROUTES.admin, label: 'Admin' },
    { to: ROUTES.notifications, label: 'Notifications' },
  ],
  store_manager: [
    { to: ROUTES.catalog, label: 'Catalog' },
  ],
}

export function roleHome(role?: Role): string {
  return role ? NAV_BY_ROLE[role][0].to : ROUTES.home
}

export function loginDestination(role: Role | undefined, from?: string): string {
  if (!role) return from === ROUTES.profile ? ROUTES.profile : ROUTES.home
  if (!from || !from.startsWith('/') || from.startsWith('//') || from.includes('\\')) return roleHome(role)
  const pathname = from.split(/[?#]/)[0]
  const allowed = pathname === ROUTES.profile || (role === 'customer'
    ? [ROUTES.catalog, ROUTES.profile, ROUTES.orders, ROUTES.checkout, ROUTES.checkoutCart, ROUTES.checkoutDelivery, `${ROUTES.checkout}/success`].includes(pathname)
      || /^\/equipment\/[^/]+$/.test(pathname)
    : pathname === ROUTES.notifications || pathname === ROUTES.analytics || (role === 'admin' && pathname === ROUTES.admin))
  return allowed ? from : roleHome(role)
}

export function fullName(user: Pick<User, 'firstName' | 'lastName'>): string {
  return [user.firstName, user.lastName].filter(Boolean).join(' ')
}

export function initials(user: Pick<User, 'firstName' | 'lastName'>): string {
  return [user.firstName, user.lastName]
    .filter(Boolean)
    .map((part) => part![0])
    .join('')
    .toUpperCase()
}
