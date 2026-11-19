import { useParams } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { UnderDevelopment } from '@/components/common/under-development'
import { StaffOrderDetail } from '@/components/orders/staff-order-detail'
import { useAuthStore } from '@/store/auth-store'

export function ManageOrderDetailPage() {
  const { id } = useParams()
  const role = useAuthStore((state) => state.user?.role)
  if (role !== 'store_manager') {
    return <UnderDevelopment title="Order details" description="The store order workspace is available to store manager accounts." />
  }
  return (
    <StaffOrderDetail
      orderId={id}
      backPath={ROUTES.manageOrders}
      backLabel="Back to store orders"
      canManage
    />
  )
}
