import { expect, test } from '@playwright/test'

const customerEmail = process.env.E2E_CUSTOMER_EMAIL ?? ''
const customerPassword = process.env.E2E_CUSTOMER_PASSWORD ?? ''

test.describe('customer rental order flow', () => {
  test.skip(!customerEmail || !customerPassword, 'Set E2E_CUSTOMER_EMAIL and E2E_CUSTOMER_PASSWORD to run this flow.')

  test('login, catalog, cart, checkout and Stripe payment reach paid', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('Email').fill(customerEmail)
    await page.getByLabel('Password').fill(customerPassword)
    await page.getByRole('button', { name: 'Login' }).click()
    await expect(page.getByRole('button', { name: /Account menu/i })).toBeVisible()

    await page.goto('/equipment')
    const firstProduct = page.locator('a[href^="/equipment/"]').first()
    await expect(firstProduct).toBeVisible()
    await firstProduct.click()

    await page.getByRole('button', { name: 'Reserve now' }).click()
    await expect(page).toHaveURL(/\/checkout/)

    await page.getByRole('button', { name: 'Confirm rental' }).click()
    await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible({ timeout: 30_000 })

    await expect(page.getByText('Ready to pay')).toBeVisible({ timeout: 30_000 })
    await page.getByRole('link', { name: /^Pay / }).click()

    const paymentFrame = page.frameLocator('iframe[name^="__privateStripeFrame"]').first()
    await paymentFrame.getByRole('textbox', { name: /card number/i }).fill('4242 4242 4242 4242')
    await paymentFrame.getByRole('textbox', { name: /expiry|expiration/i }).fill('12 / 34')
    await paymentFrame.getByRole('textbox', { name: /cvc|security code/i }).fill('123')
    await page.getByRole('button', { name: 'Pay now' }).click()

    await expect(page.getByText('Paid')).toBeVisible({ timeout: 60_000 })
    await expect(page).toHaveURL(/\/orders\/[0-9a-f-]+$/)
  })
})
