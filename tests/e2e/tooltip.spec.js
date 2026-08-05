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

test('renders where floating-ui asked it to', async ({ page }) => {
  // Regression: the panel is a native [popover], and the UA stylesheet's
  // `inset: 0; margin: auto` centred it in the space left over once floatingui
  // set left/top — so it rendered hundreds of pixels off its anchor. The helper
  // now zeroes the margin and releases right/bottom.
  await page.goto('/atom/docs/tooltip')

  const trigger = page.getByRole('button', { name: 'Top', exact: true })
  const content = rootFor(page, 'Top').locator('[data-atom-tooltip-content]')

  await trigger.hover()
  await expect(content).toBeVisible()

  const position = await content.evaluate(el => {
    const rect = el.getBoundingClientRect()

    return {
      requested: { x: Math.round(parseFloat(el.style.left)), y: Math.round(parseFloat(el.style.top)) },
      rendered: { x: Math.round(rect.x), y: Math.round(rect.y) },
    }
  })

  expect(position.rendered).toEqual(position.requested)
})

test('links the trigger to its content via aria-describedby', async ({ page }) => {
  await page.goto('/atom/docs/tooltip')

  const trigger = page.getByRole('button', { name: 'Top', exact: true })
  const describedby = await trigger.getAttribute('aria-describedby')

  expect(describedby).toBeTruthy()
  await expect(page.locator(`#${describedby}`)).toHaveAttribute('data-atom-tooltip-content', /.*/)
})
