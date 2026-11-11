import { useRef, useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { getErrorMessage, isMockApi } from '@/api/client'
import { ADMIN_MOCK_TARGETS } from '@/api/mock-adapter'
import { CatalogAdmin } from '@/components/admin/catalog-admin'
import { UserActionForm } from '@/components/admin/user-action-form'
import { FormField } from '@/components/auth/form-field'
import { PageHeader } from '@/components/common/page-header'
import { StatCard } from '@/components/common/stat-card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { EQUIPMENT, getCategoryLabel } from '@/data/equipment'
import { formatCurrency } from '@/lib/pricing'
import { cn } from '@/lib/utils'
import { updateUserStore } from '@/services/admin'
import { useAuthStore } from '@/store/auth-store'
import type { Equipment, ListingStatus } from '@/types'

const STATUS_STYLES: Record<ListingStatus, string> = {
  active: 'bg-success/15 text-success',
  paused: 'bg-warning/20 text-foreground',
  draft: 'bg-muted text-muted-foreground',
}

function MockCatalogOverview() {
  const [listings, setListings] = useState<Equipment[]>(EQUIPMENT)

  const activeCount = listings.filter((item) => item.status === 'active').length
  const totalRentals = listings.reduce((sum, item) => sum + item.totalRentals, 0)

  function toggleStatus(item: Equipment) {
    const next: ListingStatus = item.status === 'active' ? 'paused' : 'active'
    setListings((current) => current.map((entry) => (entry.id === item.id ? { ...entry, status: next } : entry)))
    toast.success(`${item.name} is now ${next}.`)
  }

  return (
    <>
      <h2 className="mt-10 text-2xl font-bold">Catalog overview</h2>
      <p className="mt-2 text-sm text-muted-foreground">Demo listings from the mock data set.</p>

      <div className="mt-8 grid gap-4 sm:grid-cols-3">
        <StatCard label="Total listings" value={String(listings.length)} />
        <StatCard label="Active listings" value={String(activeCount)} />
        <StatCard label="Total rentals" value={totalRentals.toLocaleString('en-US')} />
      </div>

      <div className="mt-8 overflow-hidden rounded-3xl border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="pl-5">Listing</TableHead>
              <TableHead className="hidden md:table-cell">Category</TableHead>
              <TableHead>Price / day</TableHead>
              <TableHead className="hidden sm:table-cell">Status</TableHead>
              <TableHead className="pr-5 text-right">Action</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {listings.map((item) => (
              <TableRow key={item.id}>
                <TableCell className="pl-5">
                  <div className="flex items-center gap-3">
                    <img src={item.image} alt="" className="size-10 shrink-0 rounded-lg object-cover" />
                    <div className="min-w-0">
                      <p className="truncate font-semibold">{item.name}</p>
                      <p className="truncate text-xs text-muted-foreground">{item.provider}</p>
                    </div>
                  </div>
                </TableCell>
                <TableCell className="hidden md:table-cell">{getCategoryLabel(item.category)}</TableCell>
                <TableCell>{formatCurrency(item.pricePerDay)}</TableCell>
                <TableCell className="hidden sm:table-cell">
                  <Badge className={cn('rounded-full px-3 capitalize', STATUS_STYLES[item.status])}>{item.status}</Badge>
                </TableCell>
                <TableCell className="pr-5 text-right">
                  {item.status === 'draft' ? null : (
                    <Button variant="outline" size="sm" className="rounded-full" onClick={() => toggleStatus(item)}>
                      {item.status === 'active' ? 'Pause' : 'Activate'}
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  )
}

function StoreAssignmentForm() {
  const [target, setTarget] = useState('')
  const [storeId, setStoreId] = useState('')
  const [pending, setPending] = useState(false)
  const pendingRef = useRef(false)
  const [touched, setTouched] = useState(false)
  const [feedback, setFeedback] = useState<{ error: boolean; message: string } | null>(null)

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (pendingRef.current) return
    setTouched(true)
    setFeedback(null)
    const id = target.trim()
    if (!id) return
    pendingRef.current = true
    setPending(true)
    try {
      const message = await updateUserStore(id, storeId.trim() || null)
      setFeedback({ error: false, message: `${id}: ${message}` })
    } catch (error) {
      setFeedback({ error: true, message: getErrorMessage(error) })
    } finally {
      pendingRef.current = false
      setPending(false)
    }
  }

  return (
    <form onSubmit={submit} noValidate className="space-y-5 rounded-3xl border bg-card p-6 sm:p-8" aria-labelledby="admin-store-title" aria-busy={pending}>
      <div>
        <h2 id="admin-store-title" className="text-xl font-bold">Assign store to a store manager</h2>
        <p className="mt-2 text-sm text-muted-foreground">
          Set the store_id for an existing store manager account. Leave the store ID empty to unassign the manager.
        </p>
      </div>
      <fieldset disabled={pending} className="space-y-5">
        <FormField id="admin-store-target" label="User ID" value={target} required
          placeholder="e.g. u-user" autoComplete="off"
          onChange={(event) => { setTarget(event.target.value); setFeedback(null) }}
          onBlur={() => setTouched(true)} error={touched && !target.trim() ? 'Enter a user ID.' : undefined} />
        <FormField id="admin-store-id" label="Store ID" value={storeId}
          placeholder="e.g. s-tools" autoComplete="off"
          onChange={(event) => { setStoreId(event.target.value); setFeedback(null) }} />
        <Button type="submit" className="h-11 rounded-full px-6" disabled={pending}>
          {pending ? 'Applying...' : storeId.trim() ? 'Assign store' : 'Unassign store'}
        </Button>
      </fieldset>
      {feedback ? <p role={feedback.error ? 'alert' : 'status'} className={`break-words rounded-xl p-4 text-sm ${feedback.error ? 'bg-destructive/10 text-destructive' : 'bg-success/15 text-foreground'}`}>{feedback.message}</p> : null}
    </form>
  )
}

function UsersPanel() {
  return (
    <>
      <div className="mt-6 rounded-2xl bg-secondary p-5 text-sm">
        <p className="font-semibold">{isMockApi ? 'Mock user administration' : 'User administration'}</p>
        <p className="mt-1 text-muted-foreground">{isMockApi
          ? 'Changes affect local demo accounts. Changing your own role or banning your own account will leave the admin area.'
          : 'User access changes are confirmed by the server.'}</p>
        {isMockApi ? <ul className="mt-3 space-y-1 break-words">{ADMIN_MOCK_TARGETS.map((target) =>
          <li key={target.id}><span className="font-mono font-semibold">{target.id}</span> — {target.email}</li>,
        )}</ul> : null}
      </div>
      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <UserActionForm action="role" />
        <UserActionForm action="ban" />
      </div>
      {!isMockApi ? <div className="mt-6 max-w-xl"><StoreAssignmentForm /></div> : null}
      {isMockApi ? <MockCatalogOverview /> : (
        <p className="mt-6 rounded-2xl border border-dashed bg-card p-4 text-sm text-muted-foreground">
          Manage stores, categories, products, images and stock from the Catalog tab.
        </p>
      )}
    </>
  )
}

export function AdminPage() {
  const user = useAuthStore((state) => state.user)
  const role = user?.role
  const isManager = role === 'store_manager'
  const canManageUsers = !isManager
  const canManageCatalog = !isMockApi && (role === 'admin' || role === 'store_manager')

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <PageHeader
        title={isManager ? 'Store administration' : 'Admin panel'}
        description={isManager
          ? 'Manage your store catalog, categories, products and stock.'
          : 'Manage user access and the store catalog.'}
      />

      {canManageCatalog ? (
        <Tabs defaultValue={canManageUsers ? 'users' : 'catalog'} className="mt-6">
          <TabsList className="w-full max-w-sm">
            {canManageUsers ? <TabsTrigger value="users">Users</TabsTrigger> : null}
            <TabsTrigger value="catalog">Catalog</TabsTrigger>
          </TabsList>
          {canManageUsers ? (
            <TabsContent value="users" className="mt-4">
              <UsersPanel />
            </TabsContent>
          ) : null}
          <TabsContent value="catalog" className="mt-4">
            <CatalogAdmin
              role={isManager ? 'store_manager' : 'admin'}
              managerStoreId={user?.storeId}
            />
          </TabsContent>
        </Tabs>
      ) : isManager ? (
        <div className="mt-6 rounded-2xl border border-dashed bg-card p-6 text-sm text-muted-foreground">
          Store catalog management becomes available when the catalog API is connected.
        </div>
      ) : (
        <UsersPanel />
      )}
    </div>
  )
}
