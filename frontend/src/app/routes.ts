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
  profile: '/profile',
  login: '/login',
  register: '/register',
  oauthSuccess: '/oauth/success',
  oauthComplete: '/register/complete',
  verifyEmail: '/verify-email',
  admin: '/admin',
  analytics: '/analytics',
  notifications: '/notifications',
} as const

export function isOAuthHandoffPath(pathname: string): boolean {
  return pathname === ROUTES.oauthSuccess || pathname === ROUTES.oauthComplete
}
