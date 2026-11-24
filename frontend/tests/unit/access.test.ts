import test from 'node:test'
import assert from 'node:assert/strict'
import { can, type AccessFeature } from '@/lib/access'
import type { Role } from '@/types'

const FEATURES: AccessFeature[] = [
  'orders.read.all',
  'orders.read.store',
  'orders.read.own',
  'orders.manage.all',
  'orders.manage.store',
  'orders.events.all',
  'orders.events.store',
  'overdue.read',
  'catalog.manage.all',
  'catalog.manage.ownStore',
]

const EXPECTED: Record<Role, AccessFeature[]> = {
  customer: ['orders.read.own'],
  store_manager: ['orders.read.store', 'orders.manage.store', 'orders.events.store', 'catalog.manage.ownStore'],
  analyst: ['orders.read.all', 'orders.events.all', 'overdue.read'],
  admin: ['orders.read.all', 'orders.manage.all', 'orders.events.all', 'overdue.read', 'catalog.manage.all'],
}

const ROLES: Role[] = ['customer', 'store_manager', 'analyst', 'admin']

for (const role of ROLES) {
  test(`can(role, feature) matches the backend matrix for ${role}`, () => {
    for (const feature of FEATURES) {
      assert.equal(can(role, feature), EXPECTED[role].includes(feature), `${role} × ${feature}`)
    }
  })
}

test('unknown or missing roles cannot use any feature', () => {
  for (const feature of FEATURES) {
    assert.equal(can(undefined, feature), false)
    assert.equal(can(null, feature), false)
  }
})
