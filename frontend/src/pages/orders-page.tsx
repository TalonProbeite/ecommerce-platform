import { OrderCard } from '@/components/orders/order-card'
import { Package } from 'lucide-react'
import { ROUTES } from '@/app/routes'
import { EmptyState } from '@/components/common/empty-state'
import { LinkButton } from '@/components/common/link-button'
import { PageHeader } from '@/components/common/page-header'
import { useAuthStore } from '@/store/auth-store'
import { useOrdersStore } from '@/store/orders-store'
import { isMockApi } from '@/api/client'

export function OrdersPage() {
  const user = useAuthStore((state) => state.user)
  const allOrders = useOrdersStore((state) => state.orders)
  const orders = allOrders.filter((order) => order.userId === user?.id)

  if (!isMockApi) return <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6"><PageHeader title="My rentals" description="Rental history unavailable. Live orders are not connected yet." /></div>

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <PageHeader title="My rentals" description="Track upcoming and past rentals." />

      <div className="mt-8">
        {orders.length === 0 ? (
          <EmptyState
            icon={Package}
            title="No rentals yet"
            description="When you book equipment it will show up here."
            action={<LinkButton to={ROUTES.catalog}>Browse equipment</LinkButton>}
          />
        ) : (
          <ul className="space-y-4">
            {orders.map((order) => <OrderCard key={order.id} order={order} />)}
          </ul>
        )}
      </div>
    </div>
  )
}
