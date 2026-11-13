import { useEffect, useState, type FormEvent } from 'react'
import { Loader2, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { catalogErrorMessage } from '@/lib/catalog/api'
import { descendantCategoryIds, flattenCategoryTree } from '@/lib/catalog-mapping'
import { slugify } from '@/lib/catalog/slug'
import type { AsyncState } from '@/hooks/use-catalog-data'
import { adminCreateCategory, adminDeleteCategory, adminUpdateCategory } from '@/services/catalog'
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
import type { Category } from '@/types/catalog'

interface CategoryResource {
  state: AsyncState<Category[]>
  reload: () => void
}

function CategoryFormDialog({
  open,
  onOpenChange,
  storeId,
  category,
  categories,
  onSaved,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  storeId: string
  category: Category | null
  categories: Category[]
  onSaved: () => void
}) {
  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const [slugTouched, setSlugTouched] = useState(false)
  const [parentId, setParentId] = useState('none')
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    setName(category?.name ?? '')
    setSlug(category?.slug ?? '')
    setSlugTouched(Boolean(category))
    setParentId(category?.parent_id ?? 'none')
    setError(null)
  }, [open, category])

  const blocked = category ? descendantCategoryIds(categories, category.id) : new Set<string>()
  const parentItems = [
    { value: 'none', label: 'No parent (top level)' },
    ...flattenCategoryTree(categories)
      .filter(({ category: item }) => item.id !== category?.id && !blocked.has(item.id))
      .map(({ category: item, depth }) => ({
        value: item.id,
        label: `${'\u2014 '.repeat(depth)}${item.name}`,
      })),
  ]

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (pending) return
    if (!name.trim() || !slug.trim()) {
      setError('Enter a name and slug.')
      return
    }
    setPending(true)
    setError(null)
    const payload = {
      name: name.trim(),
      slug: slug.trim(),
      parent_id: parentId === 'none' ? null : parentId,
    }
    try {
      if (category) {
        await adminUpdateCategory(storeId, category.id, payload)
        toast.success('Category updated.')
      } else {
        await adminCreateCategory(storeId, payload)
        toast.success('Category created.')
      }
      onSaved()
      onOpenChange(false)
    } catch (submitError) {
      setError(catalogErrorMessage(submitError))
    } finally {
      setPending(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{category ? 'Edit category' : 'Create category'}</DialogTitle>
          <DialogDescription>Categories belong to this store and can be nested.</DialogDescription>
        </DialogHeader>
        <form onSubmit={submit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="category-name">Name</Label>
            <Input
              id="category-name"
              value={name}
              onChange={(event) => {
                const value = event.target.value
                setName(value)
                if (!slugTouched) setSlug(slugify(value))
              }}
              required
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="category-slug">Slug</Label>
            <Input
              id="category-slug"
              value={slug}
              onChange={(event) => { setSlugTouched(true); setSlug(slugify(event.target.value)) }}
              required
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="category-parent">Parent category</Label>
            <Select items={parentItems} value={parentId} onValueChange={(value) => { if (value) setParentId(value) }} disabled={pending}>
              <SelectTrigger id="category-parent" className="h-10 w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {parentItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          {error ? <p role="alert" className="text-sm text-destructive">{error}</p> : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={pending}>Cancel</Button>
            <Button type="submit" disabled={pending}>
              {pending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {pending ? 'Saving...' : category ? 'Save changes' : 'Create category'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function CategorySection({ storeId, categories }: { storeId: string; categories: CategoryResource }) {
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Category | null>(null)
  const [deleting, setDeleting] = useState<Category | null>(null)
  const [deletePending, setDeletePending] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const list = categories.state.status === 'success' ? categories.state.data : []
  const flat = flattenCategoryTree(list)

  function openCreate() {
    setEditing(null)
    setFormOpen(true)
  }

  function openEdit(category: Category) {
    setEditing(category)
    setFormOpen(true)
  }

  async function remove() {
    if (!deleting || deletePending) return
    setDeletePending(true)
    setDeleteError(null)
    try {
      await adminDeleteCategory(storeId, deleting.id)
      toast.success('Category deleted.')
      setDeleting(null)
      categories.reload()
    } catch (error) {
      setDeleteError(catalogErrorMessage(error))
    } finally {
      setDeletePending(false)
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 className="text-lg font-bold">Categories</h3>
          <p className="text-sm text-muted-foreground">Build a category tree for this store.</p>
        </div>
        <Button className="rounded-full px-4" onClick={openCreate}>
          <Plus className="size-4" aria-hidden="true" /> Add category
        </Button>
      </div>

      {categories.state.status === 'loading' ? (
        <p role="status" className="text-sm text-muted-foreground">Loading categories...</p>
      ) : categories.state.status === 'error' ? (
        <div role="alert" className="rounded-2xl border bg-card p-5">
          <p className="font-semibold">Unable to load categories</p>
          <p className="mt-1 text-sm text-muted-foreground">{categories.state.error}</p>
          <Button variant="outline" className="mt-4 rounded-full" onClick={() => categories.reload()}>Try again</Button>
        </div>
      ) : flat.length === 0 ? (
        <div className="rounded-2xl border bg-card p-6 text-sm text-muted-foreground">
          No categories yet. Create one before adding products.
        </div>
      ) : (
        <div className="overflow-hidden rounded-2xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="pl-5">Name</TableHead>
                <TableHead className="w-40">ID</TableHead>
                <TableHead className="hidden sm:table-cell">Slug</TableHead>
                <TableHead className="pr-5 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {flat.map(({ category, depth }) => (
                <TableRow key={category.id}>
                  <TableCell className="pl-5 font-semibold" style={{ paddingLeft: `${20 + depth * 20}px` }}>
                    {category.name}
                  </TableCell>
                  <TableCell className="w-40 whitespace-normal">
                    <span className="block break-all font-mono text-[11px] leading-tight text-muted-foreground">{category.id}</span>
                  </TableCell>
                  <TableCell className="hidden font-mono text-xs text-muted-foreground sm:table-cell">{category.slug}</TableCell>
                  <TableCell className="pr-5 text-right">
                    <div className="inline-flex gap-2">
                      <Button variant="outline" size="sm" className="rounded-full" onClick={() => openEdit(category)}>Edit</Button>
                      <Button variant="destructive" size="sm" className="rounded-full" onClick={() => { setDeleteError(null); setDeleting(category) }}>Delete</Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <CategoryFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        storeId={storeId}
        category={editing}
        categories={list}
        onSaved={categories.reload}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => { if (!open) setDeleting(null) }}
        title="Delete this category?"
        description="Categories with subcategories or products cannot be deleted."
        confirmLabel="Delete category"
        pending={deletePending}
        error={deleteError}
        onConfirm={remove}
      />
    </div>
  )
}
