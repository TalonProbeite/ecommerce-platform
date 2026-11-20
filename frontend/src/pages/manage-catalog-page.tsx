import { CatalogAdmin } from '@/components/admin/catalog-admin'
import { PageHeader } from '@/components/common/page-header'
import { UnderDevelopment } from '@/components/common/under-development'
import { useAuthStore } from '@/store/auth-store'

export function ManageCatalogPage() {
  const user = useAuthStore((state) => state.user)
  if (user?.role !== 'store_manager') {
    return <UnderDevelopment title="Store catalog" description="The store catalog workspace is available to store manager accounts." />
  }
  return (
    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <PageHeader
        title="Store catalog"
        description="Manage the categories, products and stock for your store."
      />
      <div className="mt-6">
        <CatalogAdmin role="store_manager" managerStoreId={user.storeId} />
      </div>
    </div>
  )
}
