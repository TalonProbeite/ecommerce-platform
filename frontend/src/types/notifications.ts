// Temporary UI/mock model. These fields, enums and envelope are NOT a backend contract.
export const NOTIFICATION_STATUSES = ['pending', 'sent', 'failed'] as const
export const NOTIFICATION_CHANNELS = ['email', 'sms'] as const
export interface NotificationRecord {
  id: string
  recipient: string
  status: typeof NOTIFICATION_STATUSES[number]
  channel: typeof NOTIFICATION_CHANNELS[number]
  createdAt: string
  title: string
  body: string
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
  total: number
  limit: number
  offset: number
}
