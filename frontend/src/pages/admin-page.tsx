import { useState } from 'react'
import { toast } from 'sonner'
import { isMockApi } from '@/api/client'
import { ADMIN_MOCK_TARGETS } from '@/api/mock-adapter'
import { UserActionForm } from '@/components/admin/user-action-form'
import { PageHeader } from '@/components/common/page-header'
import { StatCard } from '@/components/common/stat-card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { EQUIPMENT, getCategoryLabel } from '@/data/equipment'
import { formatCurrency } from '@/lib/pricing'
import { cn } from '@/lib/utils'
import type { Equipment, ListingStatus } from '@/types'

const STATUS_STYLES: Record<ListingStatus, string> = {
  active: 'bg-success/15 text-success',
  paused: 'bg-warning/20 text-foreground',
  draft: 'bg-muted text-muted-foreground',
}

export function AdminPage() {
  const [listings, setListings] = useState<Equipment[]>(EQUIPMENT)

  const activeCount = listings.filter((item) => item.status === 'active').length
  const totalRentals = listings.reduce((sum, item) => sum + item.totalRentals, 0)

  function toggleStatus(item: Equipment) {
    if (!isMockApi) return
    const next: ListingStatus = item.status === 'active' ? 'paused' : 'active'
    setListings((current) => current.map((entry) => (entry.id === item.id ? { ...entry, status: next } : entry)))
    toast.success(`${item.name} is now ${next}.`)
  }

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <PageHeader title="Admin panel" description="Manage user access and try the available administration actions." />

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

      <h2 className="mt-10 text-2xl font-bold">Catalog overview</h2>
      {!isMockApi ? <p className="mt-2 text-sm text-muted-foreground">Demo listings. Listing management and rental statistics are unavailable for live accounts.</p> : null}

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
                    <Button variant="outline" size="sm" className="rounded-full" disabled={!isMockApi} onClick={() => toggleStatus(item)}>
                      {item.status === 'active' ? 'Pause' : 'Activate'}
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}
