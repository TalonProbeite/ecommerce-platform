import { useParams } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { StaffOrderDetail } from '@/components/orders/staff-order-detail'

export function AdminOrderDetailPage() {
  const { id } = useParams()
  return (
    <StaffOrderDetail
      orderId={id}
      backPath={ROUTES.adminOrders}
      backLabel="Back to all orders"
      canManage
    />
  )
}
