import { useEffect, useState, type FormEvent } from 'react'
import { Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { catalogErrorMessage } from '@/lib/catalog/api'
import { useAdminStore } from '@/hooks/use-admin-catalog'
import { adminCreateStore, adminDeleteStore, adminUpdateStore } from '@/services/catalog'
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
import { Textarea } from '@/components/ui/textarea'
import { ConfirmDialog } from './confirm-dialog'
import type { Store } from '@/types/catalog'

const EMPTY_FORM = { name: '', description: '', location: '', active: true }

export function StoreCreateDialog({
  open,
  onOpenChange,
  onCreated,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated: (store: Store) => void
}) {
  const [form, setForm] = useState(EMPTY_FORM)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (open) {
      setForm(EMPTY_FORM)
      setError(null)
    }
  }, [open])

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (pending) return
    if (!form.name.trim()) {
      setError('Enter a store name.')
      return
    }
    setPending(true)
    setError(null)
    try {
      const store = await adminCreateStore({
        name: form.name.trim(),
        description: form.description.trim() || null,
        location: form.location.trim() || null,
        active: form.active,
      })
      toast.success('Store created.')
      onCreated(store)
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
          <DialogTitle>Create store</DialogTitle>
          <DialogDescription>Stores group categories and products for one rental business.</DialogDescription>
        </DialogHeader>
        <form onSubmit={submit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="new-store-name">Name</Label>
            <Input id="new-store-name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="new-store-location">Location</Label>
            <Input id="new-store-location" value={form.location} onChange={(event) => setForm({ ...form, location: event.target.value })} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="new-store-description">Description</Label>
            <Textarea id="new-store-description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} rows={3} />
          </div>
          <div className="flex items-center gap-2">
            <Checkbox id="new-store-active" checked={form.active} onCheckedChange={(checked) => setForm({ ...form, active: checked })} />
            <Label htmlFor="new-store-active">Active (visible in the public catalog)</Label>
          </div>
          {error ? <p role="alert" className="text-sm text-destructive">{error}</p> : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={pending}>Cancel</Button>
            <Button type="submit" disabled={pending}>
              {pending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {pending ? 'Creating...' : 'Create store'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function StoreSection({
  storeId,
  role,
  onDeleted,
  onUpdated,
}: {
  storeId: string
  role: 'admin' | 'store_manager'
  onDeleted: () => void
  onUpdated?: () => void
}) {
  const store = useAdminStore(storeId)
  const [form, setForm] = useState(EMPTY_FORM)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    if (store.state.status === 'success') {
      setForm({
        name: store.state.data.name,
        description: store.state.data.description ?? '',
        location: store.state.data.location ?? '',
        active: store.state.data.active,
      })
    }
  }, [store.state])

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (pending) return
    if (!form.name.trim()) {
      setError('Enter a store name.')
      return
    }
    setPending(true)
    setError(null)
    try {
      await adminUpdateStore(storeId, {
        name: form.name.trim(),
        description: form.description.trim() || null,
        location: form.location.trim() || null,
        active: form.active,
      })
      toast.success('Store updated.')
      store.reload()
      onUpdated?.()
    } catch (submitError) {
      setError(catalogErrorMessage(submitError))
    } finally {
      setPending(false)
    }
  }

  async function remove() {
    if (deleting) return
    setDeleting(true)
    setError(null)
    try {
      await adminDeleteStore(storeId)
      toast.success('Store deleted.')
      setConfirmOpen(false)
      onDeleted()
    } catch (deleteError) {
      setError(catalogErrorMessage(deleteError))
    } finally {
      setDeleting(false)
    }
  }

  if (store.state.status === 'loading') {
    return <p role="status" className="text-sm text-muted-foreground">Loading store...</p>
  }

  if (store.state.status === 'error') {
    return (
      <div role="alert" className="rounded-2xl border bg-card p-5">
        <p className="font-semibold">Unable to load the store</p>
        <p className="mt-1 text-sm text-muted-foreground">{store.state.error}</p>
        <Button variant="outline" className="mt-4 rounded-full" onClick={() => store.reload()}>Try again</Button>
      </div>
    )
  }

  return (
    <form onSubmit={submit} className="max-w-2xl space-y-4 rounded-2xl border bg-card p-5 sm:p-6">
      <div className="space-y-1.5">
        <Label htmlFor="store-id">Store ID</Label>
        <Input id="store-id" value={storeId} readOnly className="font-mono text-xs" />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="store-name">Name</Label>
        <Input id="store-name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="store-location">Location</Label>
        <Input id="store-location" value={form.location} onChange={(event) => setForm({ ...form, location: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="store-description">Description</Label>
        <Textarea id="store-description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} rows={4} />
      </div>
      <div className="flex items-center gap-2">
        <Checkbox id="store-active" checked={form.active} onCheckedChange={(checked) => setForm({ ...form, active: checked })} />
        <Label htmlFor="store-active">Active (visible in the public catalog)</Label>
      </div>
      {error ? <p role="alert" className="text-sm text-destructive">{error}</p> : null}
      <div className="flex flex-wrap gap-2">
        <Button type="submit" className="rounded-full px-5" disabled={pending}>
          {pending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
          {pending ? 'Saving...' : 'Save changes'}
        </Button>
        {role === 'admin' ? (
          <Button type="button" variant="destructive" className="rounded-full px-5" onClick={() => setConfirmOpen(true)} disabled={pending}>
            Delete store
          </Button>
        ) : null}
      </div>

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title="Delete this store?"
        description="The store is removed from the catalog. Products and categories must be empty first."
        confirmLabel="Delete store"
        pending={deleting}
        error={error}
        onConfirm={remove}
      />
    </form>
  )
}
