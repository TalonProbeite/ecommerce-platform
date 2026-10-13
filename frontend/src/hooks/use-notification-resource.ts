import { useEffect, useState } from 'react'
import { isAxiosError } from 'axios'
import { getErrorMessage } from '@/api/client'

export type NotificationResource<T> = { state: 'loading' } | { state: 'success'; data: T } | { state: 'error'; message: string }
export function notificationError(error: unknown): string {
  if (isAxiosError(error)) {
    if (error.response?.status === 404) return 'Notification not found'
    if (error.response?.status === 403) return 'Forbidden. Only active admins and analysts can view notifications.'
    if (error.response?.status === 401) return 'Your session has expired. Please sign in again.'
  }
  return error instanceof Error && !isAxiosError(error) ? error.message : getErrorMessage(error)
}

export function useNotificationResource<T>(load: (signal: AbortSignal) => Promise<T>, retry: number): NotificationResource<T> {
  const [result, setResult] = useState<{ load: typeof load; retry: number; resource: NotificationResource<T> }>()
  useEffect(() => {
    const controller = new AbortController()
    load(controller.signal).then(data => {
      if (!controller.signal.aborted) setResult({ load, retry, resource: { state: 'success', data } })
    }).catch(error => {
      if (!controller.signal.aborted) setResult({ load, retry, resource: { state: 'error', message: notificationError(error) } })
    })
    return () => controller.abort()
  }, [load, retry])
  return result?.load === load && result.retry === retry ? result.resource : { state: 'loading' }
}
