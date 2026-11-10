import { api, isMockApi } from '@/api/client'
import { messageResponse } from '@/api/response'
import { useAuthStore } from '@/store/auth-store'
import type { UpdateBanPayload, UpdateRolePayload } from '@/types'

async function updateUser(id: string, action: 'role' | 'ban', payload: UpdateRolePayload | UpdateBanPayload) {
  const targetId = id.trim()
  if (!targetId) throw new Error('Enter a user ID.')
  const { data } = await api.patch<unknown>(
    `/auth/api/admin/users/${encodeURIComponent(targetId)}/${action}`, payload,
  )
  const message = messageResponse(data)
  const user = useAuthStore.getState().user
  if (user?.id === targetId) {
    if (!isMockApi) await useAuthStore.getState().reloadProfile()
    else if ('role' in payload) useAuthStore.setState({ user: { ...user, role: payload.role } })
    else if (payload.is_banned) useAuthStore.setState({ user: null, status: 'unauthenticated', sessionExpired: false })
  }
  return message
}

export function updateUserRole(id: string, role: UpdateRolePayload['role']) {
  return updateUser(id, 'role', { role })
}

export function updateUserBan(id: string, isBanned: boolean) {
  return updateUser(id, 'ban', { is_banned: isBanned })
}

export async function updateUserStore(id: string, storeId: string | null) {
  const targetId = id.trim()
  if (!targetId) throw new Error('Enter a user ID.')
  const { data } = await api.patch<unknown>(
    `/auth/api/admin/users/${encodeURIComponent(targetId)}/store`, { store_id: storeId },
  )
  const message = messageResponse(data)
  const user = useAuthStore.getState().user
  if (user?.id === targetId) {
    if (!isMockApi) await useAuthStore.getState().reloadProfile()
    else useAuthStore.setState({ user: { ...user, storeId } })
  }
  return message
}
