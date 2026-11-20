import { expect, test } from '@playwright/test'

const managerEmail = process.env.E2E_MANAGER_EMAIL ?? ''
const managerPassword = process.env.E2E_MANAGER_PASSWORD ?? ''

test.describe('store manager catalog', () => {
  test.skip(!managerEmail || !managerPassword, 'Set E2E_MANAGER_EMAIL and E2E_MANAGER_PASSWORD to run this flow.')

  test('manager opens the store catalog workspace', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Email').fill(managerEmail)
    await page.getByLabel('Password').fill(managerPassword)
    await page.getByRole('button', { name: 'Login' }).click()
    await expect(page.getByRole('button', { name: /Account menu/i })).toBeVisible()

    await page.goto('/manage/catalog')
    await expect(page.getByRole('heading', { name: 'Store catalog' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Products' })).toBeVisible()
  })
})
