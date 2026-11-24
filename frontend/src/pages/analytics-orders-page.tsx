import { ROUTES } from '@/app/routes'
import { StaffOrdersPage } from '@/components/orders/staff-orders-page'

export function AnalyticsOrdersPage() {
  return (
    <StaffOrdersPage
      title="Order analytics"
      description="Read-only view of every rental order."
      orderPath={ROUTES.analyticsOrder}
    />
  )
}
