import { useState } from 'react'
import { Plus } from 'lucide-react'
import { useAdminCategories, useAdminStores } from '@/hooks/use-admin-catalog'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { CategorySection } from './category-section'
import { ProductSection } from './product-section'
import { StoreCreateDialog, StoreSection } from './store-section'

function StoreWorkspace({
  storeId,
  role,
  onStoreDeleted,
  onStoreUpdated,
}: {
  storeId: string
  role: 'admin' | 'store_manager'
  onStoreDeleted: () => void
  onStoreUpdated: () => void
}) {
  const categories = useAdminCategories(storeId)

  return (
    <Tabs defaultValue="store" className="mt-6">
      <TabsList className="w-full max-w-md">
        <TabsTrigger value="store">Store</TabsTrigger>
        <TabsTrigger value="categories">Categories</TabsTrigger>
        <TabsTrigger value="products">Products</TabsTrigger>
      </TabsList>
      <TabsContent value="store" className="mt-4">
        <StoreSection storeId={storeId} role={role} onDeleted={onStoreDeleted} onUpdated={onStoreUpdated} />
      </TabsContent>
      <TabsContent value="categories" className="mt-4">
        <CategorySection storeId={storeId} categories={categories} />
      </TabsContent>
      <TabsContent value="products" className="mt-4">
        <ProductSection storeId={storeId} categories={categories} />
      </TabsContent>
    </Tabs>
  )
}

export function CatalogAdmin({
  role,
  managerStoreId,
}: {
  role: 'admin' | 'store_manager'
  managerStoreId?: string | null
}) {
  const isAdmin = role === 'admin'
  const stores = useAdminStores(isAdmin)
  const [selectedStoreId, setSelectedStoreId] = useState('')
  const [createOpen, setCreateOpen] = useState(false)

  if (!isAdmin && !managerStoreId) {
    return (
      <div role="alert" className="rounded-2xl border border-destructive/30 bg-destructive/5 p-5">
        <p className="font-semibold">No store assigned</p>
        <p className="mt-1 text-sm text-muted-foreground">
          Your account is a store manager but has no store_id. Ask an administrator to assign a store.
        </p>
      </div>
    )
  }

  const storeList = stores.state.status === 'success' ? stores.state.data.data : []
  const activeStoreId = isAdmin ? selectedStoreId : managerStoreId ?? ''
  const storeItems = storeList.map((store) => ({
    value: store.id,
    label: store.active ? store.name : `${store.name} (inactive)`,
  }))

  return (
    <div>
      {isAdmin ? (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div className="w-full space-y-1.5 sm:max-w-sm">
            <label htmlFor="catalog-admin-store" className="text-sm font-medium">Store</label>
            <Select
              items={storeItems}
              value={selectedStoreId || null}
              onValueChange={(value) => { if (value) setSelectedStoreId(value) }}
              disabled={stores.state.status === 'loading'}
            >
              <SelectTrigger id="catalog-admin-store" className="h-10 w-full">
                <SelectValue placeholder="Select a store" />
              </SelectTrigger>
              <SelectContent>
                {storeItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <Button variant="outline" className="h-10 rounded-full px-4" onClick={() => setCreateOpen(true)}>
            <Plus className="size-4" aria-hidden="true" /> New store
          </Button>
        </div>
      ) : null}

      {stores.state.status === 'error' ? (
        <div role="alert" className="mt-4 rounded-2xl border bg-card p-4">
          <p className="font-semibold">Unable to load stores</p>
          <p className="mt-1 text-sm text-muted-foreground">{stores.state.error}</p>
          <Button variant="outline" className="mt-3 rounded-full" onClick={() => stores.reload()}>Try again</Button>
        </div>
      ) : null}

      {!activeStoreId ? (
        <div className="mt-6 rounded-2xl border border-dashed bg-card p-6 text-sm text-muted-foreground">
          {isAdmin
            ? 'Select a store to manage its categories and products, or create a new store.'
            : 'No store selected.'}
        </div>
      ) : (
        <StoreWorkspace
          key={activeStoreId}
          storeId={activeStoreId}
          role={role}
          onStoreDeleted={() => {
            setSelectedStoreId('')
            stores.reload()
          }}
          onStoreUpdated={() => stores.reload()}
        />
      )}

      {isAdmin ? (
        <StoreCreateDialog
          open={createOpen}
          onOpenChange={setCreateOpen}
          onCreated={(store) => {
            stores.reload()
            setSelectedStoreId(store.id)
          }}
        />
      ) : null}
    </div>
  )
}
