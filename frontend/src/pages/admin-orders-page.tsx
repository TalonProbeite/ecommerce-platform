import { ROUTES } from '@/app/routes'
import { StaffOrdersPage } from '@/components/orders/staff-orders-page'

export function AdminOrdersPage() {
  return (
    <StaffOrdersPage
      title="All orders"
      description="Review every rental order across all stores."
      orderPath={ROUTES.adminOrder}
      canFilterByStore
    />
  )
}
