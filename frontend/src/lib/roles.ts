import { ROUTES } from '@/app/routes'
import { CATALOG_PATH } from '@/lib/catalog/routes'
import type { Role, User } from '@/types'

export interface NavItem {
  to: string
  label: string
}

export const NAV_BY_ROLE: Record<Role, NavItem[]> = {
  customer: [
    { to: ROUTES.catalog, label: 'Equipment' },
  ],
  analyst: [
    { to: ROUTES.notifications, label: 'Notifications' },
    { to: ROUTES.analyticsOrders, label: 'All orders' },
    { to: ROUTES.analyticsOverdue, label: 'Overdue' },
  ],
  admin: [
    { to: ROUTES.admin, label: 'Admin' },
    { to: ROUTES.adminOrders, label: 'All orders' },
    { to: ROUTES.analyticsOverdue, label: 'Overdue' },
    { to: ROUTES.notifications, label: 'Notifications' },
  ],
  store_manager: [
    { to: ROUTES.admin, label: 'Store admin' },
    { to: ROUTES.manageOrders, label: 'Store orders' },
    { to: ROUTES.catalog, label: 'Equipment' },
  ],
}

export function roleHome(role?: Role): string {
  return role ? NAV_BY_ROLE[role][0].to : ROUTES.home
}

export function loginDestination(role: Role | undefined, from?: string): string {
  if (!role) return from === ROUTES.profile ? ROUTES.profile : ROUTES.home
  if (!from || !from.startsWith('/') || from.startsWith('//') || from.includes('\\')) return roleHome(role)
  const pathname = from.split(/[?#]/)[0]
  const allowed = pathname === ROUTES.profile
    || pathname === CATALOG_PATH
    || /^\/equipment\/[^/]+$/.test(pathname)
    || (role === 'customer'
      ? [ROUTES.orders, ROUTES.checkout, ROUTES.checkoutCart, ROUTES.checkoutDelivery, `${ROUTES.checkout}/success`].includes(pathname)
      : pathname === ROUTES.notifications
        || (role === 'analyst' && (pathname === ROUTES.analytics || pathname.startsWith(`${ROUTES.analyticsOrders}`) || pathname === ROUTES.analyticsOverdue))
        || (role === 'admin' && ([ROUTES.admin, ROUTES.adminOrders, ROUTES.analytics, ROUTES.analyticsOrders, ROUTES.analyticsOverdue] as string[]).includes(pathname))
        || (role === 'admin' && (pathname.startsWith(`${ROUTES.adminOrders}/`) || pathname.startsWith(`${ROUTES.analyticsOrders}/`)))
        || (role === 'store_manager' && ([ROUTES.admin, ROUTES.manageOrders] as string[]).includes(pathname))
        || (role === 'store_manager' && pathname.startsWith(`${ROUTES.manageOrders}/`)))
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
