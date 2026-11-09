import { createBrowserRouter, Navigate, useLocation } from 'react-router-dom'
import { ProtectedRoute } from '@/components/auth/protected-route'
import { AppLayout } from '@/components/layout/app-layout'
import { RouteError } from '@/components/common/route-error'
import { CATALOG_PATH } from '@/lib/catalog/routes'
import { ROUTES } from './routes'

function LegacyCatalogRedirect() {
  const location = useLocation()
  return <Navigate to={{ pathname: CATALOG_PATH, search: location.search }} replace />
}

export const router = createBrowserRouter([
  {
    element: <AppLayout />,
    errorElement: <AppLayout><RouteError /></AppLayout>,
    children: [
      { path: ROUTES.home, lazy: async () => ({ Component: (await import('@/pages/root-page')).RootPage }) },
      { path: ROUTES.login, lazy: async () => ({ Component: (await import('@/pages/login-page')).LoginPage }) },
      { path: ROUTES.register, lazy: async () => ({ Component: (await import('@/pages/register-page')).RegisterPage }) },
      { path: ROUTES.oauthSuccess, lazy: async () => ({ Component: (await import('@/pages/oauth-success-page')).OAuthSuccessPage }) },
      { path: ROUTES.oauthComplete, lazy: async () => ({ Component: (await import('@/pages/oauth-complete-page')).OAuthCompletePage }) },
      { path: ROUTES.verifyEmail, lazy: async () => ({ Component: (await import('@/pages/verify-email-page')).VerifyEmailPage }) },
      // The catalog is public on the backend; guests may browse it.
      { path: CATALOG_PATH, lazy: async () => ({ Component: (await import('@/pages/catalog-page')).CatalogPage }) },
      { path: `${CATALOG_PATH}/:id`, lazy: async () => ({ Component: (await import('@/pages/equipment-page')).EquipmentPage }) },
      // The old SPA path collides with the /catalog API prefix; keep a client-side redirect.
      { path: '/catalog', element: <LegacyCatalogRedirect /> },
      { path: '/catalog/*', element: <LegacyCatalogRedirect /> },
      {
        element: <ProtectedRoute roles={['customer']} />,
        children: [
          { path: `${ROUTES.checkout}/*`, lazy: async () => ({ Component: (await import('@/pages/checkout-page')).CheckoutPage }) },
          { path: ROUTES.orders, lazy: async () => ({ Component: (await import('@/pages/orders-page')).OrdersPage }) },
        ],
      },
      {
        element: <ProtectedRoute />,
        children: [
          { path: ROUTES.profile, lazy: async () => ({ Component: (await import('@/pages/profile-page')).ProfilePage }) },
        ],
      },
      {
        element: <ProtectedRoute roles={['admin', 'store_manager']} />,
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
