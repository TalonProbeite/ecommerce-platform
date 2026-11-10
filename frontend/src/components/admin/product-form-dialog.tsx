import { useEffect, useState, type FormEvent } from 'react'
import { Loader2, Plus, Star, Trash2, Upload } from 'lucide-react'
import { toast } from 'sonner'
import { catalogErrorMessage } from '@/lib/catalog/api'
import { flattenCategoryTree } from '@/lib/catalog-mapping'
import { slugify } from '@/lib/catalog/slug'
import { useAdminProduct } from '@/hooks/use-admin-catalog'
import {
  adminCreateProduct,
  adminDeleteProductImage,
  adminGetStock,
  adminSetMainProductImage,
  adminUpdateProduct,
  adminUpdateStock,
  adminUploadProductImage,
} from '@/services/catalog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
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
import { Textarea } from '@/components/ui/textarea'
import type { Category } from '@/types/catalog'

interface ProductFormState {
  name: string
  slug: string
  description: string
  categoryId: string
  price: string
  deposit: string
  quantity: string
  active: boolean
}

const EMPTY_FORM: ProductFormState = {
  name: '',
  slug: '',
  description: '',
  categoryId: '',
  price: '',
  deposit: '0',
  quantity: '0',
  active: true,
}

export function ProductFormDialog({
  open,
  onOpenChange,
  storeId,
  categories,
  productId,
  onChanged,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  storeId: string
  categories: Category[]
  productId: string | null
  onChanged: () => void
}) {
  const isEdit = Boolean(productId)
  const detail = useAdminProduct(storeId, productId ?? undefined, open && isEdit)
  const [form, setForm] = useState<ProductFormState>(EMPTY_FORM)
  const [slugTouched, setSlugTouched] = useState(false)
  const [attributes, setAttributes] = useState<Array<{ name: string; value: string }>>([])
  const [loadedProductId, setLoadedProductId] = useState<string | null>(null)
  const [stockQuantity, setStockQuantity] = useState('0')
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [imagePending, setImagePending] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)
  const [refreshingStock, setRefreshingStock] = useState(false)

  useEffect(() => {
    if (!open) return
    setForm(EMPTY_FORM)
    setSlugTouched(false)
    setAttributes([])
    setLoadedProductId(null)
    setStockQuantity('0')
    setError(null)
  }, [open, productId])

  useEffect(() => {
    if (!open || detail.state.status !== 'success') return
    const product = detail.state.data
    if (product.id === loadedProductId) return
    setForm({
      name: product.name,
      slug: product.slug,
      description: product.description ?? '',
      categoryId: product.category_id,
      price: String(product.price_per_hour),
      deposit: String(product.deposit_amount),
      quantity: String(product.stock?.quantity ?? 0),
      active: product.is_active,
    })
    setAttributes((product.attributes ?? []).map((attribute) => ({ name: attribute.name, value: attribute.value })))
    setStockQuantity(String(product.stock?.quantity ?? 0))
    setLoadedProductId(product.id)
    setSlugTouched(true)
  }, [open, detail.state, loadedProductId])

  const categoryItems = flattenCategoryTree(categories).map(({ category, depth }) => ({
    value: category.id,
    label: `${'\u2014 '.repeat(depth)}${category.name}`,
  }))

  function updateAttribute(index: number, key: 'name' | 'value', value: string) {
    setAttributes((current) => current.map((entry, entryIndex) => (entryIndex === index ? { ...entry, [key]: value } : entry)))
  }

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (pending) return
    if (!form.name.trim() || !form.slug.trim() || !form.categoryId) {
      setError('Enter a name, slug and category.')
      return
    }
    if (!form.price.trim()) {
      setError('Enter an hourly price.')
      return
    }
    const price = Number(form.price)
    const deposit = Number(form.deposit)
    const quantity = Number(form.quantity)
    if (!Number.isInteger(price) || price < 0) {
      setError('Enter a whole-number hourly price.')
      return
    }
    if (!Number.isInteger(deposit) || deposit < 0) {
      setError('Enter a whole-number deposit amount.')
      return
    }
    const incompleteAttribute = attributes.some(
      (attribute) => (attribute.name.trim() && !attribute.value.trim()) || (!attribute.name.trim() && attribute.value.trim()),
    )
    if (incompleteAttribute) {
      setError('Fill both the name and value for every attribute, or remove the empty row.')
      return
    }
    const cleanAttributes = attributes
      .map((attribute) => ({ name: attribute.name.trim(), value: attribute.value.trim() }))
      .filter((attribute) => attribute.name && attribute.value)

    setPending(true)
    setError(null)
    try {
      if (productId) {
        await adminUpdateProduct(storeId, productId, {
          name: form.name.trim(),
          slug: form.slug.trim(),
          category_id: form.categoryId,
          price_per_hour: price,
          deposit_amount: deposit,
          description: form.description.trim() || null,
          is_active: form.active,
          attributes: cleanAttributes,
        })
        toast.success('Product updated.')
        onChanged()
        onOpenChange(false)
      } else {
        if (!Number.isFinite(quantity) || quantity < 0 || !Number.isInteger(quantity)) {
          setError('Enter a whole-number stock quantity.')
          return
        }
        await adminCreateProduct(storeId, {
          name: form.name.trim(),
          slug: form.slug.trim(),
          category_id: form.categoryId,
          price_per_hour: price,
          deposit_amount: deposit,
          quantity,
          description: form.description.trim() || null,
          is_active: form.active,
          attributes: cleanAttributes,
        })
        toast.success('Product created.')
        onChanged()
        onOpenChange(false)
      }
    } catch (submitError) {
      setError(catalogErrorMessage(submitError))
    } finally {
      setPending(false)
    }
  }

  async function uploadImage(file: File) {
    if (!productId || uploading) return
    setUploading(true)
    setError(null)
    try {
      await adminUploadProductImage(storeId, productId, file)
      toast.success('Image uploaded.')
      detail.reload()
      onChanged()
    } catch (uploadError) {
      setError(catalogErrorMessage(uploadError))
    } finally {
      setUploading(false)
    }
  }

  async function setMainImage(imageId: string) {
    if (!productId || imagePending) return
    setImagePending(imageId)
    setError(null)
    try {
      await adminSetMainProductImage(storeId, productId, imageId)
      toast.success('Main image updated.')
      detail.reload()
      onChanged()
    } catch (imageError) {
      setError(catalogErrorMessage(imageError))
    } finally {
      setImagePending(null)
    }
  }

  async function deleteImage(imageId: string) {
    if (!productId || imagePending) return
    setImagePending(imageId)
    setError(null)
    try {
      await adminDeleteProductImage(storeId, productId, imageId)
      toast.success('Image deleted.')
      detail.reload()
      onChanged()
    } catch (imageError) {
      setError(catalogErrorMessage(imageError))
    } finally {
      setImagePending(null)
    }
  }

  async function updateStock() {
    if (!productId || pending) return
    const quantity = Number(stockQuantity)
    if (!Number.isFinite(quantity) || quantity < 0 || !Number.isInteger(quantity)) {
      setError('Enter a whole-number stock quantity.')
      return
    }
    setPending(true)
    setError(null)
    try {
      const stock = await adminUpdateStock(storeId, productId, quantity)
      setStockQuantity(String(stock.quantity))
      toast.success('Stock updated.')
      detail.reload()
      onChanged()
    } catch (stockError) {
      setError(catalogErrorMessage(stockError))
    } finally {
      setPending(false)
    }
  }

  async function refreshStock() {
    if (!productId || refreshingStock) return
    setRefreshingStock(true)
    setError(null)
    try {
      const stock = await adminGetStock(storeId, productId)
      setStockQuantity(String(stock.quantity))
      toast.success('Stock refreshed.')
      detail.reload()
    } catch (stockError) {
      setError(catalogErrorMessage(stockError))
    } finally {
      setRefreshingStock(false)
    }
  }

  const product = detail.state.status === 'success' ? detail.state.data : null
  const images = product?.images ?? []
  const stock = product?.stock

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit product' : 'Create product'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'Update details, images and stock for this product.' : 'Add a product to this store catalog.'}
          </DialogDescription>
        </DialogHeader>

        {isEdit && detail.state.status === 'loading' ? (
          <p role="status" className="text-sm text-muted-foreground">Loading product...</p>
        ) : isEdit && detail.state.status === 'error' ? (
          <div role="alert" className="rounded-2xl border bg-card p-4">
            <p className="font-semibold">Unable to load this product</p>
            <p className="mt-1 text-sm text-muted-foreground">{detail.state.error}</p>
            <Button variant="outline" className="mt-3 rounded-full" onClick={() => detail.reload()}>Try again</Button>
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5 sm:col-span-2">
                <Label htmlFor="product-name">Name</Label>
                <Input
                  id="product-name"
                  value={form.name}
                  onChange={(event) => {
                    const value = event.target.value
                    setForm((current) => ({
                      ...current,
                      name: value,
                      slug: slugTouched ? current.slug : slugify(value),
                    }))
                  }}
                  required
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="product-slug">Slug</Label>
                <Input
                  id="product-slug"
                  value={form.slug}
                  onChange={(event) => { setSlugTouched(true); setForm({ ...form, slug: slugify(event.target.value) }) }}
                  required
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="product-category">Category</Label>
                {categoryItems.length === 0 ? (
                  <p className="rounded-lg border border-dashed p-2 text-sm text-muted-foreground">
                    Create a category before adding products.
                  </p>
                ) : (
                  <Select items={categoryItems} value={form.categoryId || null} onValueChange={(value) => { if (value) setForm({ ...form, categoryId: value }) }}>
                    <SelectTrigger id="product-category" className="h-10 w-full">
                      <SelectValue placeholder="Choose a category" />
                    </SelectTrigger>
                    <SelectContent>
                      {categoryItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
                    </SelectContent>
                  </Select>
                )}
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="product-price">Price / hour</Label>
                <Input id="product-price" type="number" min={0} step={1} inputMode="numeric" value={form.price} onChange={(event) => setForm({ ...form, price: event.target.value })} required />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="product-deposit">Deposit</Label>
                <Input id="product-deposit" type="number" min={0} step={1} inputMode="numeric" value={form.deposit} onChange={(event) => setForm({ ...form, deposit: event.target.value })} />
              </div>
              {!isEdit ? (
                <div className="space-y-1.5">
                  <Label htmlFor="product-quantity">Initial stock</Label>
                  <Input id="product-quantity" type="number" min={0} step={1} value={form.quantity} onChange={(event) => setForm({ ...form, quantity: event.target.value })} />
                </div>
              ) : null}
              <div className="flex items-end pb-1">
                <div className="flex items-center gap-2">
                  <Checkbox id="product-active" checked={form.active} onCheckedChange={(checked) => setForm({ ...form, active: checked })} />
                  <Label htmlFor="product-active">Active listing</Label>
                </div>
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label htmlFor="product-description">Description</Label>
                <Textarea id="product-description" rows={4} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} />
              </div>
            </div>

            <section className="space-y-3 rounded-2xl border p-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <h3 className="font-bold">Attributes</h3>
                  <p className="text-xs text-muted-foreground">Each name/value pair is shown as a key feature.</p>
                </div>
                <Button type="button" variant="outline" size="sm" className="rounded-full" onClick={() => setAttributes([...attributes, { name: '', value: '' }])}>
                  <Plus className="size-4" aria-hidden="true" /> Add
                </Button>
              </div>
              {attributes.length === 0 ? (
                <p className="text-sm text-muted-foreground">No attributes yet.</p>
              ) : (
                <div className="space-y-2">
                  {attributes.map((attribute, index) => (
                    <div key={index} className="flex flex-col gap-2 sm:flex-row">
                      <Input placeholder="Name" value={attribute.name} onChange={(event) => updateAttribute(index, 'name', event.target.value)} />
                      <Input placeholder="Value" value={attribute.value} onChange={(event) => updateAttribute(index, 'value', event.target.value)} />
                      <Button type="button" variant="outline" size="icon" aria-label="Remove attribute" onClick={() => setAttributes(attributes.filter((_, entryIndex) => entryIndex !== index))}>
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </section>

            {isEdit && product ? (
              <>
                <section className="space-y-3 rounded-2xl border p-4">
                  <div>
                    <h3 className="font-bold">Images</h3>
                    <p className="text-xs text-muted-foreground">The main image appears in the catalog.</p>
                  </div>
                  {images.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No images uploaded.</p>
                  ) : (
                    <div className="grid gap-3 sm:grid-cols-2">
                      {images.map((image) => (
                        <div key={image.id} className="space-y-2 rounded-xl border p-2">
                          <img src={image.url} alt="" className="h-32 w-full rounded-lg object-cover" />
                          <div className="flex flex-wrap items-center gap-2">
                            {image.is_main ? <Badge className="rounded-full">Main</Badge> : null}
                            <Button type="button" variant="outline" size="sm" className="rounded-full" disabled={image.is_main || imagePending === image.id} onClick={() => setMainImage(image.id)}>
                              <Star className="size-3.5" aria-hidden="true" /> Set main
                            </Button>
                            <Button type="button" variant="destructive" size="sm" className="rounded-full" disabled={imagePending === image.id} onClick={() => deleteImage(image.id)}>
                              <Trash2 className="size-3.5" aria-hidden="true" /> Delete
                            </Button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                  <div className="space-y-1.5">
                    <Label htmlFor="product-image">Upload image</Label>
                    <Input
                      id="product-image"
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      disabled={uploading}
                      onChange={(event) => {
                        const file = event.target.files?.[0]
                        if (file) void uploadImage(file)
                        event.target.value = ''
                      }}
                    />
                    {uploading ? <p className="text-xs text-muted-foreground">Uploading...</p> : null}
                  </div>
                </section>

                <section className="space-y-3 rounded-2xl border p-4">
                  <div>
                    <h3 className="font-bold">Stock</h3>
                    <p className="text-xs text-muted-foreground">Set the absolute quantity available for rent.</p>
                  </div>
                  <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1.5">
                      <Label htmlFor="product-stock">Quantity</Label>
                      <Input id="product-stock" type="number" min={0} step={1} className="w-32" value={stockQuantity} onChange={(event) => setStockQuantity(event.target.value)} />
                    </div>
                    <Button type="button" variant="outline" className="rounded-full" onClick={updateStock} disabled={pending}>
                      {pending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                      Update stock
                    </Button>
                    <Button type="button" variant="ghost" className="rounded-full" onClick={refreshStock} disabled={refreshingStock}>
                      {refreshingStock ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                      Refresh
                    </Button>
                  </div>
                  {stock ? (
                    <p className="text-sm text-muted-foreground">
                      Reserved: {stock.reserved} &middot; Available: {stock.available}
                    </p>
                  ) : null}
                </section>
              </>
            ) : null}

            {error ? <p role="alert" className="text-sm text-destructive">{error}</p> : null}
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={pending}>Cancel</Button>
              <Button type="submit" disabled={pending || categoryItems.length === 0}>
                {pending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                {pending ? 'Saving...' : isEdit ? 'Save product' : 'Create product'}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  )
}
