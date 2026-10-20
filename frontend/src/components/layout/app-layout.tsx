import { useEffect, type ReactNode } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { ROUTES, isOAuthHandoffPath } from '@/app/routes'
import { useAuthStore } from '@/store/auth-store'
import { isMockApi } from '@/api/client'
import { SiteFooter } from './site-footer'
import { SiteHeader } from './site-header'

export function AppLayout({ children }: { children?: ReactNode }) {
  const { pathname } = useLocation()
  const initialize = useAuthStore(state => state.initialize)

  useEffect(() => {
    // Handoff pages own their requests. Resume the guest/session probe if the
    // user leaves one before authenticating, including client-side navigation.
    if (!isOAuthHandoffPath(pathname)) void initialize()
  }, [pathname, initialize])

  useEffect(() => {
    window.scrollTo({ top: 0 })
  }, [pathname])

  return (
    <div className="flex min-h-svh flex-col">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-background focus:px-4 focus:py-3 focus:outline-2 focus:outline-ring">
        Skip to main content
      </a>
      <SiteHeader />
      <main id="main-content" tabIndex={-1} className="min-w-0 flex-1 scroll-mt-20">
        {!isMockApi && (pathname === ROUTES.catalog || pathname.startsWith('/equipment/') || pathname.startsWith(ROUTES.checkout)) ? <p role="status" className="mx-auto mt-4 max-w-7xl rounded-xl bg-secondary px-4 py-3 text-sm">Demo equipment. Live availability and rental booking are not available yet.</p> : null}
        {children ?? <Outlet />}
      </main>
      <SiteFooter />
    </div>
  )
}
