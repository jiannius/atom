import { test, expect } from '@playwright/test'

// `<atom:form marker="optional">` (#212), on the docs page of the form: the demo marks Phone and Message as optional and draws no asterisk on
// Name and Email. "(optional)" sits inside the label, so the browser's accessible name of the control carries it.

test('the optional note is part of the name of the control, and a required field has no asterisk', async ({ page }) => {
  await page.goto('/atom/docs/form')

  const form = page.locator('form:has(input[type="email"]):has(textarea)', { has: page.getByText('(optional)') }).first()

  await expect(form.getByRole('textbox', { name: 'Phone (optional)', exact: true })).toBeVisible()
  await expect(form.getByRole('textbox', { name: 'Message (optional)', exact: true })).toBeVisible()
  await expect(form.getByRole('textbox', { name: 'Name', exact: true })).toBeVisible()
  await expect(form.getByRole('textbox', { name: 'Email', exact: true })).toBeVisible()

  // no asterisk on the required ones, and exactly two notes
  await expect(form.locator('label [data-atom-icon]')).toHaveCount(0)
  await expect(form.locator('[data-atom-label-optional]')).toHaveCount(2)
})
