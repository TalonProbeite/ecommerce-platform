export const NOTIFICATION_STATUSES = ['sent', 'failed'] as const
export const NOTIFICATION_CHANNELS = ['email'] as const
export interface NotificationRecord {
  id: string
  recipient: string
  status: typeof NOTIFICATION_STATUSES[number]
  channel: typeof NOTIFICATION_CHANNELS[number]
  created_at: string
  type: string
  payload: unknown
  attempts: number
  error?: string
}
export interface NotificationFilters {
  status?: string
  channel?: string
  recipient?: string
  start_date?: string
  end_date?: string
  limit?: number
  offset?: number
}
export interface NotificationPage {
  items: NotificationRecord[]
  hasNext: boolean
  limit: number
  offset: number
}
