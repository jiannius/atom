import { test, expect } from '@playwright/test'

// <atom:form> is novalidate by default (#211): a `required` or `type="email"` field no longer stops the submit with the browser's own
// bubble, so the server rule runs and atom's inline errors and the error toast show. `:novalidate="false"` takes the browser's
// validation back. The page is tests/Fixtures/FormErrorToastFixture.php: its "hints" form has a `required` field and a `type="email"` one.

const open = async (page) => {
  await page.goto('/atom/e2e/form-error-toast')
  await page.waitForFunction(() => window.Livewire && window.atom)
  await expect(page.locator('input[data-probe="must"]')).toBeVisible()
}

test('an empty required field reaches the server and shows atom\'s inline errors and toast, not the browser bubble', async ({ page }) => {
  await open(page)

  const form = page.locator('form[data-form="hints"]')
  await expect(form).toHaveAttribute('novalidate', /.*/)
  await expect(page.locator('input[data-probe="must"]')).toHaveAttribute('required', /.*/)

  await page.locator('[data-submit="hints"]').click()

  await expect(form.locator('[data-atom-error]').first()).toBeVisible()
  await expect(form).toContainText('Must is required.')
  await expect(page.locator('[data-atom-toast]')).toContainText('Please check the form')
})

test('a malformed email gets the server message, not the browser\'s', async ({ page }) => {
  await open(page)

  await page.locator('input[data-probe="must"]').fill('x')
  await page.locator('input[data-probe="hint-mail"]').fill('not-an-email')
  await page.locator('[data-submit="hints"]').click()

  await expect(page.locator('form[data-form="hints"]')).toContainText('Mail must be valid.')
})

test('the required marker still comes from the prop', async ({ page }) => {
  await open(page)

  // the label of a required field carries the marker; novalidate does not touch it
  await expect(page.locator('form[data-form="hints"] [data-atom-label] [data-atom-icon].text-danger-fg')).toHaveCount(1)
})

test(':novalidate="false" keeps the browser\'s validation: an empty required field does not submit', async ({ page }) => {
  await open(page)

  const form = page.locator('form[data-form="native"]')
  await expect(form).not.toHaveAttribute('novalidate', /.*/)

  const requests = []
  page.on('request', (r) => r.url().includes('/livewire') && r.method() === 'POST' && requests.push(r.url()))

  await page.locator('[data-submit="native"]').click()
  await page.waitForTimeout(400)

  expect(requests).toEqual([])
  await expect(page.locator('[data-natives]')).toHaveText('0')

  await page.locator('input[data-probe="native"]').fill('x')
  await page.locator('[data-submit="native"]').click()
  await expect(page.locator('[data-natives]')).toHaveText('1')
})
