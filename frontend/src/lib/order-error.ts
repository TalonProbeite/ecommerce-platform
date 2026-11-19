import { isHttpError } from '@/api/http'

export interface ProblemDetails {
  type?: string
  title?: string
  status?: number
  detail?: string
  errors?: Record<string, string[]>
  product_id?: string
}

function problemOf(error: unknown): ProblemDetails | undefined {
  if (!isHttpError(error)) return undefined
  const data = error.response?.data
  return data && typeof data === 'object' ? (data as ProblemDetails) : undefined
}

export function orderErrorMessage(error: unknown, fallback = 'The Order Service request failed. Please try again.'): string {
  const problem = problemOf(error)
  if (problem?.errors) {
    const messages = Object.values(problem.errors).flat().filter(Boolean)
    if (messages.length > 0) return messages.join(' ')
  }
  if (typeof problem?.detail === 'string' && problem.detail.trim()) return problem.detail
  if (isHttpError(error)) {
    if (error.response?.status === 401) return 'Your session expired. Please sign in again.'
    if (error.response?.status === 403) return 'You do not have access to this order.'
    if (error.response?.status === 404) return 'The order was not found.'
    if (error.response?.status === 409) return 'This checkout request is already being processed. Please wait and retry.'
    if (error.response?.status === 429) return 'Too many requests. Please wait a moment and try again.'
  }
  return error instanceof Error ? error.message : fallback
}

/**
 * Manager action errors keep the server detail out of the UI. A 404 never
 * reveals whether the order exists, and a 409 names the business case in plain
 * language instead of echoing the domain exception text.
 */
export function managerActionErrorMessage(error: unknown, fallback = 'The action could not be completed. Please try again.'): string {
  const problem = problemOf(error)
  const status = problem?.status ?? (isHttpError(error) ? error.response?.status : undefined)
  if (status === 401) return 'Your session expired. Please sign in again.'
  if (status === 403) return 'You do not have access to this order.'
  if (status === 404) return 'The order was not found.'
  if (status === 422) return 'One of the selected items is not part of this order.'
  if (status === 409) {
    const detail = typeof problem?.detail === 'string' ? problem.detail : ''
    if (/refund/i.test(detail)) return 'The deposit refund would exceed the amount paid for this order.'
    if (/transition/i.test(detail)) return 'This item or order can no longer change. Refresh to see its current state.'
    if (/processing/i.test(detail)) return 'The payment is still processing. Please try again shortly.'
    return 'This action can no longer be completed. Refresh to see the current state.'
  }
  if (status === 429) return 'Too many requests. Please wait a moment and try again.'
  return error instanceof Error ? error.message : fallback
}
