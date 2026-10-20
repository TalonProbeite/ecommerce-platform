import { useEffect } from 'react'
import { RouterProvider } from 'react-router-dom'
import { Toaster } from '@/components/ui/sonner'
import { useAuthStore } from '@/store/auth-store'
import { router } from './router'
import { isMockApi } from '@/api/client'

export function App() {
  useEffect(() => {
    if (isMockApi) return
    const revalidate = () => {
      if (document.visibilityState !== 'visible') return
      const state = useAuthStore.getState()
      if (state.status === 'authenticated') {
        // Keep the active page and form drafts during transient outages.
        // A definitive 401 still expires the session through the API client.
        void state.revalidateProfile().catch(() => {})
      }
    }
    window.addEventListener('focus', revalidate)
    const timer = window.setInterval(revalidate, 60_000)
    return () => {
      window.removeEventListener('focus', revalidate)
      window.clearInterval(timer)
    }
  }, [])

  return (
    <>
      <RouterProvider router={router} />
      <Toaster position="top-center" richColors />
    </>
  )
}
