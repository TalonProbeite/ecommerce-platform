export function messageResponse(data: unknown): string {
  if (!data || typeof data !== 'object' || !('message' in data) || typeof data.message !== 'string' || !data.message.trim()) {
    throw new Error('The server returned an invalid success response. Please reload before repeating the operation.')
  }
  return data.message
}
