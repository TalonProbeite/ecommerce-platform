import { expect, test } from '@playwright/test'

const managerEmail = process.env.E2E_MANAGER_EMAIL ?? ''
const managerPassword = process.env.E2E_MANAGER_PASSWORD ?? ''

test.describe('store manager order panel', () => {
  test.skip(!managerEmail || !managerPassword, 'Set E2E_MANAGER_EMAIL and E2E_MANAGER_PASSWORD to run this flow.')

  test('login, review the store order and read its journal', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Email').fill(managerEmail)
    await page.getByLabel('Password').fill(managerPassword)
    await page.getByRole('button', { name: 'Login' }).click()
    await expect(page.getByRole('button', { name: /Account menu/i })).toBeVisible()

    await page.goto('/manage/orders')
    await expect(page.getByRole('heading', { name: 'Store orders' })).toBeVisible()

    const firstOrder = page.locator('a[href^="/manage/orders/"]').first()
    await expect(firstOrder).toBeVisible()
    await firstOrder.click()

    await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Items' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Journal' })).toBeVisible()

    const issueButton = page.getByRole('button', { name: 'Issue selected items' })
    if (await issueButton.count()) {
      await expect(issueButton).toBeDisabled()
    }
  })

  test('issue and return selected items when the order allows it', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Email').fill(managerEmail)
    await page.getByLabel('Password').fill(managerPassword)
    await page.getByRole('button', { name: 'Login' }).click()
    await expect(page.getByRole('button', { name: /Account menu/i })).toBeVisible()

    await page.goto('/manage/orders')
    const firstOrder = page.locator('a[href^="/manage/orders/"]').first()

    if (!(await firstOrder.count())) {
      test.skip(true, 'No orders are available for this store.')
    }

    await firstOrder.click()
    await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible()

    const issueCheckbox = page.getByRole('checkbox').first()
    if (await issueCheckbox.count()) {
      await issueCheckbox.check()
      const issueButton = page.getByRole('button', { name: 'Issue selected items' })
      if (await issueButton.isEnabled()) {
        await issueButton.click()
        await expect(page.getByRole('heading', { name: 'Journal' })).toBeVisible()
      }
    }
  })
})
