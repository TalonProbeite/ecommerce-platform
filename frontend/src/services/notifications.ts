import { api, isMockApi } from '@/api/client'
import { buildNotificationQuery } from '@/lib/notification-query'
import type { NotificationFilters, NotificationPage, NotificationRecord } from '@/types/notifications'
export { buildNotificationQuery, changeNotificationFilters } from '@/lib/notification-query'

const NOTIFICATIONS_PATH = '/notification/api/private/notifications'
function requireMock() {
  if (!isMockApi) throw new Error('Notifications are available in mock mode only until the response schema is confirmed.')
}
export async function listNotifications(filters: NotificationFilters, signal?: AbortSignal): Promise<NotificationPage> {
  requireMock()
  const { data } = await api.get<NotificationPage>(NOTIFICATIONS_PATH, { params: buildNotificationQuery(filters), signal })
  return data
}
export async function getNotification(id: string, signal?: AbortSignal): Promise<NotificationRecord> {
  requireMock()
  const { data } = await api.get<NotificationRecord>(`${NOTIFICATIONS_PATH}/${encodeURIComponent(id)}`, { signal })
  return data
}
