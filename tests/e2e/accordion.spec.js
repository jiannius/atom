import { test, expect } from '@playwright/test'

// Drives the live demos on /atom/docs/accordion.
const accordionWith = (page, triggerName) =>
  page.locator('[data-atom-accordion]').filter({ has: page.getByRole('button', { name: triggerName }) })

test('toggles a panel open and closed', async ({ page }) => {
  await page.goto('/atom/docs/accordion')

  const root = accordionWith(page, 'What is Atom?')
  const item = root.locator('[data-atom-accordion-item]').first()
  const trigger = item.getByRole('button', { name: 'What is Atom?' })
  // the collapsible region: grid-template-rows animates 0fr -> 1fr (0px when closed)
  const region = item.locator('> div').last()

  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  await expect(region).toHaveCSS('grid-template-rows', '0px')

  await trigger.click()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  await expect(region).not.toHaveCSS('grid-template-rows', '0px')
  // chevron rotates while open
  await expect(trigger.locator('[data-atom-icon]')).toHaveClass(/rotate-180/)

  await trigger.click()
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  await expect(region).toHaveCSS('grid-template-rows', '0px')
})

test('allows multiple panels open at once by default', async ({ page }) => {
  await page.goto('/atom/docs/accordion')

  const root = accordionWith(page, 'What is Atom?')
  const first = root.getByRole('button', { name: 'What is Atom?' })
  const second = root.getByRole('button', { name: 'How do I install it?' })

  await first.click()
  await second.click()

  await expect(first).toHaveAttribute('aria-expanded', 'true')
  await expect(second).toHaveAttribute('aria-expanded', 'true')
})

test('exclusive mode closes the previously open panel', async ({ page }) => {
  await page.goto('/atom/docs/accordion')

  const root = accordionWith(page, 'Shipping')
  const shipping = root.getByRole('button', { name: 'Shipping' })
  const returns = root.getByRole('button', { name: 'Returns' })

  await shipping.click()
  await expect(shipping).toHaveAttribute('aria-expanded', 'true')

  await returns.click()
  await expect(returns).toHaveAttribute('aria-expanded', 'true')
  await expect(shipping).toHaveAttribute('aria-expanded', 'false')
})

test('opens an item marked expanded on load', async ({ page }) => {
  await page.goto('/atom/docs/accordion')

  const root = accordionWith(page, 'Overview')

  await expect(root.getByRole('button', { name: 'Overview' })).toHaveAttribute('aria-expanded', 'true')
  await expect(root.getByRole('button', { name: 'Details' })).toHaveAttribute('aria-expanded', 'false')
})
