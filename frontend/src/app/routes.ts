import { CATALOG_PATH, equipmentPath } from '@/lib/catalog/routes'

export const ROUTES = {
  home: '/',
  catalog: CATALOG_PATH,
  equipment: equipmentPath,
  checkout: '/checkout',
  checkoutCart: '/checkout/cart',
  checkoutDelivery: '/checkout/delivery',
  checkoutSuccess: (id: string) => `/checkout/success?order=${encodeURIComponent(id)}`,
  orders: '/orders',
  order: (id: string) => `/orders/${encodeURIComponent(id)}`,
  orderPayment: (id: string) => `/orders/${encodeURIComponent(id)}/pay`,
  manageOrders: '/manage/orders',
  manageOrder: (id: string) => `/manage/orders/${encodeURIComponent(id)}`,
  manageCatalog: '/manage/catalog',
  profile: '/profile',
  login: '/login',
  register: '/register',
  oauthSuccess: '/oauth/success',
  oauthComplete: '/register/complete',
  verifyEmail: '/verify-email',
  admin: '/admin',
  adminOrders: '/admin/orders',
  adminOrder: (id: string) => `/admin/orders/${encodeURIComponent(id)}`,
  analytics: '/analytics',
  analyticsOrders: '/analytics/orders',
  analyticsOrder: (id: string) => `/analytics/orders/${encodeURIComponent(id)}`,
  analyticsOverdue: '/analytics/overdue',
  notifications: '/notifications',
} as const

export function isOAuthHandoffPath(pathname: string): boolean {
  return pathname === ROUTES.oauthSuccess || pathname === ROUTES.oauthComplete
}
