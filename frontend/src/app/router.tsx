import { createBrowserRouter } from 'react-router-dom'
import { ProtectedRoute } from '@/components/auth/protected-route'
import { AppLayout } from '@/components/layout/app-layout'
import { ROUTES } from './routes'

export const router = createBrowserRouter([
  {
    element: <AppLayout />,
    children: [
      { path: ROUTES.home, lazy: async () => ({ Component: (await import('@/pages/root-page')).RootPage }) },
      { path: ROUTES.login, lazy: async () => ({ Component: (await import('@/pages/login-page')).LoginPage }) },
      { path: ROUTES.register, lazy: async () => ({ Component: (await import('@/pages/register-page')).RegisterPage }) },
      { path: ROUTES.verifyEmail, lazy: async () => ({ Component: (await import('@/pages/verify-email-page')).VerifyEmailPage }) },
      {
        element: <ProtectedRoute roles={['customer']} />,
        children: [
          { path: ROUTES.catalog, lazy: async () => ({ Component: (await import('@/pages/catalog-page')).CatalogPage }) },
          { path: '/equipment/:id', lazy: async () => ({ Component: (await import('@/pages/equipment-page')).EquipmentPage }) },
          { path: `${ROUTES.checkout}/*`, lazy: async () => ({ Component: (await import('@/pages/checkout-page')).CheckoutPage }) },
          { path: ROUTES.orders, lazy: async () => ({ Component: (await import('@/pages/orders-page')).OrdersPage }) },
          { path: ROUTES.profile, lazy: async () => ({ Component: (await import('@/pages/profile-page')).ProfilePage }) },
        ],
      },
      {
        element: <ProtectedRoute roles={['admin']} />,
        children: [
          { path: ROUTES.admin, lazy: async () => ({ Component: (await import('@/pages/admin-page')).AdminPage }) },
        ],
      },
      {
        element: <ProtectedRoute roles={['admin', 'analyst']} />,
        children: [
          { path: ROUTES.notifications, lazy: async () => ({ Component: (await import('@/pages/notifications-page')).NotificationsPage }) },
        ],
      },
      {
        element: <ProtectedRoute roles={['admin', 'analyst']} />,
        children: [
          { path: ROUTES.analytics, lazy: async () => ({ Component: (await import('@/pages/analytics-page')).AnalyticsPage }) },
        ],
      },
      { path: '*', lazy: async () => ({ Component: (await import('@/pages/not-found-page')).NotFoundPage }) },
    ],
  },
])
