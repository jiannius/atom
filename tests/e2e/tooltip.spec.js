import { test, expect } from '@playwright/test'

// Drives the live demos on /atom/docs/tooltip. The positions demo wraps a
// "Top" button whose tooltip content is "Top (default)".
const rootFor = (page, triggerName) =>
  page.locator('[data-atom-tooltip]').filter({ has: page.getByRole('button', { name: triggerName, exact: true }) })

test('shows on hover and hides on mouse out', async ({ page }) => {
  await page.goto('/atom/docs/tooltip')

  const trigger = page.getByRole('button', { name: 'Top', exact: true })
  const content = rootFor(page, 'Top').locator('[data-atom-tooltip-content]')

  await expect(content).toBeHidden()
  await trigger.hover()
  await expect(content).toBeVisible()
  await expect(content).toContainText('Top (default)')

  await page.mouse.move(0, 0)
  await expect(content).toBeHidden()
})

test('shows on keyboard focus and hides on blur', async ({ page }) => {
  // WCAG 1.4.13: the tooltip must be reachable without a pointer.
  await page.goto('/atom/docs/tooltip')

  const trigger = page.getByRole('button', { name: 'Top', exact: true })
  const content = rootFor(page, 'Top').locator('[data-atom-tooltip-content]')

  await trigger.focus()
  await expect(content).toBeVisible()

  await trigger.blur()
  await expect(content).toBeHidden()
})

test('links the trigger to its content via aria-describedby', async ({ page }) => {
  await page.goto('/atom/docs/tooltip')

  const trigger = page.getByRole('button', { name: 'Top', exact: true })
  const describedby = await trigger.getAttribute('aria-describedby')

  expect(describedby).toBeTruthy()
  await expect(page.locator(`#${describedby}`)).toHaveAttribute('data-atom-tooltip-content', /.*/)
})
