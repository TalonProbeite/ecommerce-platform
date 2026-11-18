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
