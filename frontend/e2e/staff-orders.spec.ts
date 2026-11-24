import { expect, test } from '@playwright/test'

const adminEmail = process.env.E2E_ADMIN_EMAIL ?? ''
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? ''
const analystEmail = process.env.E2E_ANALYST_EMAIL ?? ''
const analystPassword = process.env.E2E_ANALYST_PASSWORD ?? ''

async function login(page: import('@playwright/test').Page, email: string, password: string) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill(password)
  await page.getByRole('button', { name: 'Login' }).click()
  await expect(page.getByRole('button', { name: /Account menu/i })).toBeVisible()
}

test.describe('admin and analyst order panels', () => {
  test('admin sees all orders, the journal and the overdue list', async ({ page }) => {
    test.skip(!adminEmail || !adminPassword, 'Set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD to run the admin flow.')
    await login(page, adminEmail, adminPassword)

    await page.goto('/admin/orders')
    await expect(page.getByRole('heading', { name: 'All orders' })).toBeVisible()

    const firstOrder = page.locator('a[href^="/admin/orders/"]').first()
    if (!(await firstOrder.count())) {
      test.skip(true, 'No orders are available.')
    }
    await firstOrder.click()
    await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Journal' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Cancel order' })).toBeVisible()

    await page.goto('/analytics/overdue')
    await expect(page.getByRole('heading', { name: 'Overdue rentals' })).toBeVisible()
  })

  test('analyst reads orders and overdue rentals without action buttons', async ({ page }) => {
    test.skip(!analystEmail || !analystPassword, 'Set E2E_ANALYST_EMAIL and E2E_ANALYST_PASSWORD to run the analyst flow.')
    await login(page, analystEmail, analystPassword)

    await page.goto('/analytics/orders')
    await expect(page.getByRole('heading', { name: 'Order analytics' })).toBeVisible()

    const firstOrder = page.locator('a[href^="/analytics/orders/"]').first()
    if (!(await firstOrder.count())) {
      test.skip(true, 'No orders are available.')
    }
    await firstOrder.click()
    await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Journal' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Cancel order' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Issue selected items' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Return selected items' })).toHaveCount(0)

    await page.goto('/analytics/overdue')
    await expect(page.getByRole('heading', { name: 'Overdue rentals' })).toBeVisible()
  })
})
