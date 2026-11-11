import { useState, type FormEvent } from 'react'
import { Loader2, Plus, RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { catalogErrorMessage } from '@/lib/catalog/api'
import { FALLBACK_IMAGE, flattenCategoryTree } from '@/lib/catalog-mapping'
import { formatCents } from '@/lib/pricing'
import type { AsyncState } from '@/hooks/use-catalog-data'
import { useAdminProducts } from '@/hooks/use-admin-catalog'
import { adminDeleteProduct, adminRestoreProduct } from '@/services/catalog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ConfirmDialog } from './confirm-dialog'
import { ProductFormDialog } from './product-form-dialog'
import type { Category, Product } from '@/types/catalog'

const STATUS_ITEMS = [
  { value: 'all', label: 'All statuses' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
]

const PAGE_SIZE_ITEMS = [
  { value: '10', label: '10' },
  { value: '20', label: '20' },
  { value: '50', label: '50' },
]

interface CategoryResource {
  state: AsyncState<Category[]>
  reload: () => void
}

export function ProductSection({ storeId, categories }: { storeId: string; categories: CategoryResource }) {
  const [categoryId, setCategoryId] = useState('all')
  const [status, setStatus] = useState('all')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(20)
  const [createOpen, setCreateOpen] = useState(false)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null)
  const [deletePending, setDeletePending] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const [restoreOpen, setRestoreOpen] = useState(false)
  const [restoreId, setRestoreId] = useState('')
  const [restorePending, setRestorePending] = useState(false)
  const [restoreError, setRestoreError] = useState<string | null>(null)

  const products = useAdminProducts(storeId, {
    category_id: categoryId === 'all' ? undefined : categoryId,
    is_active: status === 'all' ? undefined : status === 'active',
    page,
    per_page: perPage,
  })

  const categoryList = categories.state.status === 'success' ? categories.state.data : []
  const categoryItems = [
    { value: 'all', label: 'All categories' },
    ...flattenCategoryTree(categoryList).map(({ category, depth }) => ({
      value: category.id,
      label: `${'\u2014 '.repeat(depth)}${category.name}`,
    })),
  ]
  const categoryNames = new Map(flattenCategoryTree(categoryList).map(({ category }) => [category.id, category.name]))
  const pageData = products.state.status === 'success' ? products.state.data : undefined
  const rows = pageData?.data ?? []
  const meta = pageData?.meta

  function changeFilter(setter: (value: string) => void, value: string) {
    setter(value)
    setPage(1)
  }

  function productCategory(product: Product): string {
    return product.category_name ?? product.category?.name ?? categoryNames.get(product.category_id) ?? '—'
  }

  async function remove() {
    if (!deleteTarget || deletePending) return
    setDeletePending(true)
    setDeleteError(null)
    try {
      await adminDeleteProduct(storeId, deleteTarget.id)
      toast.success('Product deleted.')
      setDeleteTarget(null)
      products.reload()
    } catch (error) {
      setDeleteError(catalogErrorMessage(error))
    } finally {
      setDeletePending(false)
    }
  }

  async function restore(event: FormEvent) {
    event.preventDefault()
    if (restorePending) return
    const id = restoreId.trim()
    if (!id) {
      setRestoreError('Enter a product ID.')
      return
    }
    setRestorePending(true)
    setRestoreError(null)
    try {
      await adminRestoreProduct(storeId, id)
      toast.success('Product restored.')
      setRestoreOpen(false)
      setRestoreId('')
      products.reload()
    } catch (error) {
      setRestoreError(catalogErrorMessage(error))
    } finally {
      setRestorePending(false)
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h3 className="text-lg font-bold">Products</h3>
          <p className="text-sm text-muted-foreground">Manage listings, images and stock for this store.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" className="rounded-full" onClick={() => { setRestoreError(null); setRestoreOpen(true) }}>
            <RotateCcw className="size-4" aria-hidden="true" /> Restore by ID
          </Button>
          <Button className="rounded-full" onClick={() => setCreateOpen(true)} disabled={categories.state.status === 'success' && categoryList.length === 0}>
            <Plus className="size-4" aria-hidden="true" /> Add product
          </Button>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        <div className="space-y-1.5">
          <Label htmlFor="admin-product-category">Category</Label>
          <Select items={categoryItems} value={categoryId} onValueChange={(value) => { if (value) changeFilter(setCategoryId, value) }}>
            <SelectTrigger id="admin-product-category" className="h-10 w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {categoryItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="admin-product-status">Status</Label>
          <Select items={STATUS_ITEMS} value={status} onValueChange={(value) => { if (value) changeFilter(setStatus, value) }}>
            <SelectTrigger id="admin-product-status" className="h-10 w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {STATUS_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="admin-product-per-page">Items per page</Label>
          <Select items={PAGE_SIZE_ITEMS} value={String(perPage)} onValueChange={(value) => { if (value) { setPerPage(Number(value)); setPage(1) } }}>
            <SelectTrigger id="admin-product-per-page" className="h-10 w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {PAGE_SIZE_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
      </div>

      {categories.state.status === 'success' && categoryList.length === 0 ? (
        <p className="rounded-2xl border border-dashed bg-card p-4 text-sm text-muted-foreground">
          Create a category first, then add products.
        </p>
      ) : null}

      {products.state.status === 'loading' ? (
        <p role="status" className="text-sm text-muted-foreground">Loading products...</p>
      ) : products.state.status === 'error' ? (
        <div role="alert" className="rounded-2xl border bg-card p-5">
          <p className="font-semibold">Unable to load products</p>
          <p className="mt-1 text-sm text-muted-foreground">{products.state.error}</p>
          <Button variant="outline" className="mt-4 rounded-full" onClick={() => products.reload()}>Try again</Button>
        </div>
      ) : rows.length === 0 ? (
        <div className="rounded-2xl border bg-card p-6 text-sm text-muted-foreground">
          No products match these filters.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="pl-5">Product</TableHead>
                <TableHead>ID</TableHead>
                <TableHead className="hidden md:table-cell">Category</TableHead>
                <TableHead>Price / hour</TableHead>
                <TableHead className="hidden sm:table-cell">Deposit</TableHead>
                <TableHead className="hidden sm:table-cell">Status</TableHead>
                <TableHead className="pr-5 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((product) => (
                <TableRow key={product.id}>
                  <TableCell className="pl-5">
                    <div className="flex items-center gap-3">
                      <img src={product.main_image_url || FALLBACK_IMAGE} alt="" className="size-10 shrink-0 rounded-lg object-cover" />
                      <div className="min-w-0">
                        <p className="truncate font-semibold">{product.name}</p>
                        <p className="truncate font-mono text-xs text-muted-foreground">{product.slug}</p>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="font-mono text-xs text-muted-foreground">{product.id}</span>
                  </TableCell>
                  <TableCell className="hidden md:table-cell">{productCategory(product)}</TableCell>
                  <TableCell>{formatCents(product.price_per_hour)}</TableCell>
                  <TableCell className="hidden sm:table-cell">{formatCents(product.deposit_amount)}</TableCell>
                  <TableCell className="hidden sm:table-cell">
                    <Badge className="rounded-full px-3">{product.is_active ? 'Active' : 'Inactive'}</Badge>
                  </TableCell>
                  <TableCell className="pr-5 text-right">
                    <div className="inline-flex gap-2">
                      <Button variant="outline" size="sm" className="rounded-full" onClick={() => setEditingId(product.id)}>Edit</Button>
                      <Button variant="destructive" size="sm" className="rounded-full" onClick={() => { setDeleteError(null); setDeleteTarget(product) }}>Delete</Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      {meta && meta.last_page > 1 ? (
        <nav aria-label="Admin product pagination" className="flex items-center justify-center gap-3">
          <Button variant="outline" className="rounded-full" disabled={meta.current_page <= 1} onClick={() => setPage(meta.current_page - 1)}>
            Previous
          </Button>
          <span className="text-sm text-muted-foreground">Page {meta.current_page} of {meta.last_page}</span>
          <Button variant="outline" className="rounded-full" disabled={meta.current_page >= meta.last_page} onClick={() => setPage(meta.current_page + 1)}>
            Next
          </Button>
        </nav>
      ) : null}

      <ProductFormDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
        storeId={storeId}
        categories={categoryList}
        productId={null}
        onChanged={() => products.reload()}
      />
      <ProductFormDialog
        open={Boolean(editingId)}
        onOpenChange={(open) => { if (!open) setEditingId(null) }}
        storeId={storeId}
        categories={categoryList}
        productId={editingId}
        onChanged={() => products.reload()}
      />

      <ConfirmDialog
        open={Boolean(deleteTarget)}
        onOpenChange={(open) => { if (!open) setDeleteTarget(null) }}
        title="Delete this product?"
        description="The product is soft-deleted. You can restore it by ID afterwards."
        confirmLabel="Delete product"
        pending={deletePending}
        error={deleteError}
        onConfirm={remove}
      />

      <Dialog open={restoreOpen} onOpenChange={setRestoreOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Restore a deleted product</DialogTitle>
            <DialogDescription>Enter the ID of a soft-deleted product from this store.</DialogDescription>
          </DialogHeader>
          <form onSubmit={restore} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="restore-product-id">Product ID</Label>
              <Input id="restore-product-id" value={restoreId} onChange={(event) => setRestoreId(event.target.value)} placeholder="e.g. p-123" />
            </div>
            {restoreError ? <p role="alert" className="text-sm text-destructive">{restoreError}</p> : null}
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setRestoreOpen(false)} disabled={restorePending}>Cancel</Button>
              <Button type="submit" disabled={restorePending}>
                {restorePending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                {restorePending ? 'Restoring...' : 'Restore product'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
