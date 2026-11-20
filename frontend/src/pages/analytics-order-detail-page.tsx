import { useParams } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { StaffOrderDetail } from '@/components/orders/staff-order-detail'

export function AnalyticsOrderDetailPage() {
  const { id } = useParams()
  return (
    <StaffOrderDetail
      orderId={id}
      backPath={ROUTES.analyticsOrders}
      backLabel="Back to order analytics"
      canManage={false}
    />
  )
}
