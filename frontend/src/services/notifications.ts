import { api } from '@/api/client'
import { buildNotificationQuery } from '@/lib/notification-query'
import type { NotificationFilters, NotificationPage, NotificationRecord } from '@/types/notifications'
export { buildNotificationQuery, changeNotificationFilters } from '@/lib/notification-query'

const NOTIFICATIONS_PATH = '/notification/api/private/notifications'
function notificationRecord(data: unknown): NotificationRecord {
  if (!data || typeof data !== 'object') throw new Error('The server returned an invalid notification response.')
  const record = data as NotificationRecord
  if (![record.id, record.type, record.channel, record.recipient, record.status, record.created_at].every(value => typeof value === 'string') ||
    !Number.isFinite(new Date(record.created_at).getTime()) || !Number.isInteger(record.attempts) || record.attempts < 0 ||
    !('payload' in record) || (record.error !== undefined && typeof record.error !== 'string')) {
    throw new Error('The server returned an invalid notification response.')
  }
  return record
}

export async function listNotifications(filters: NotificationFilters, signal?: AbortSignal): Promise<NotificationPage> {
  const params = buildNotificationQuery(filters)
  const { data } = await api.get<unknown>(NOTIFICATIONS_PATH, { params, signal })
  if (!Array.isArray(data)) throw new Error('The server returned an invalid notification response.')
  const items = data.map(notificationRecord)
  // The backend applies a default of 100 when limit is zero and returns no total.
  const limit = Number(params.get('limit')) || 100
  return { items, limit, offset: Number(params.get('offset')), hasNext: items.length >= limit }
}
export async function getNotification(id: string, signal?: AbortSignal): Promise<NotificationRecord> {
  const { data } = await api.get<unknown>(`${NOTIFICATIONS_PATH}/${encodeURIComponent(id)}`, { signal })
  return notificationRecord(data)
}
