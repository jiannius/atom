import { test, expect } from '@playwright/test'

// `<atom:form marker="optional">` (#212), on the docs page of the form: the demo marks Phone number and Notes as optional and draws no asterisk on
// Full name and Work email (labels no other demo on the page uses: a derived field id is the same for the same label). "(optional)" sits inside the label, so the browser's accessible name of the control carries it.

test('the optional note is part of the name of the control, and a required field has no asterisk', async ({ page }) => {
  await page.goto('/atom/docs/form')

  const form = page.locator('form:has(input[type="email"]):has(textarea)', { has: page.getByText('(optional)') }).first()

  await expect(form.getByRole('textbox', { name: 'Phone number (optional)', exact: true })).toBeVisible()
  await expect(form.getByRole('textbox', { name: 'Notes (optional)', exact: true })).toBeVisible()
  await expect(form.getByRole('textbox', { name: 'Full name', exact: true })).toBeVisible()
  await expect(form.getByRole('textbox', { name: 'Work email', exact: true })).toBeVisible()

  // no asterisk on the required ones, and exactly two notes
  await expect(form.locator('label [data-atom-icon]')).toHaveCount(0)
  await expect(form.locator('[data-atom-label-optional]')).toHaveCount(2)
})
