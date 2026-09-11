import { test, expect } from '@playwright/test'

// Drives the live demos on /atom/docs/dropdown. The basic demo has an
// "Options" trigger and a menu of items.
const rootFor = (page, triggerName) =>
  page.locator('[data-atom-dropdown]').filter({ has: page.getByRole('button', { name: triggerName }) })

test('opens on the trigger and closes when an item is clicked', async ({ page }) => {
  await page.goto('/atom/docs/dropdown')

  const trigger = page.getByRole('button', { name: 'Options' })
  const root = rootFor(page, 'Options')
  const menu = root.locator('[data-atom-menu]')

  await expect(menu).toBeHidden()
  await expect(trigger).toHaveAttribute('aria-haspopup', 'menu')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')

  await trigger.click()
  await expect(menu).toBeVisible()
  await expect(root).toHaveAttribute('data-open', '')
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')

  await menu.getByRole('menuitem', { name: 'Edit' }).click()
  await expect(menu).toBeHidden()
})

test('renders the menu where floating-ui asked it to', async ({ page }) => {
  // Same popover inset/margin regression as the tooltip — the menu is
  // positioned by the same helper, so it drifted too.
  await page.goto('/atom/docs/dropdown')

  const trigger = page.getByRole('button', { name: 'Options' })
  const menu = rootFor(page, 'Options').locator('[data-atom-menu]')

  await trigger.click()
  await expect(menu).toBeVisible()

  const position = await menu.evaluate(el => {
    const rect = el.getBoundingClientRect()

    return {
      requested: { x: Math.round(parseFloat(el.style.left)), y: Math.round(parseFloat(el.style.top)) },
      rendered: { x: Math.round(rect.x), y: Math.round(rect.y) },
    }
  })

  expect(position.rendered).toEqual(position.requested)
})

test('a native dismiss (escape) clears data-open and aria-expanded', async ({ page }) => {
  // Regression: data-open / aria-expanded were only reset in hide(), so a
  // native popover dismiss left them stale. They are now reset on the
  // toggle->closed event.
  await page.goto('/atom/docs/dropdown')

  const trigger = page.getByRole('button', { name: 'Options' })
  const root = rootFor(page, 'Options')
  const menu = root.locator('[data-atom-menu]')

  await trigger.click()
  await expect(menu).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(menu).toBeHidden()
  await expect(root).not.toHaveAttribute('data-open')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
})

// A consuming app's Tailwind utilities land in @layer utilities, and atom.css
// is unlayered, so it wins over them however specific they are — that is what
// keeps the menu viewport-positioned whatever a host app's CSS says. atom's own
// docs rig ships no Tailwind, so stand in for one: deliberately more specific
// than atom's own rule, to pin the layering rather than the specificity.
const CONSUMER_ABSOLUTE_UTILITY = `
@layer utilities {
  html body [data-atom-dropdown] [data-atom-menu][popover] {
    position: absolute;
  }
}
`

test('a menu opened below the fold is positioned against the viewport', async ({ page }) => {
  // Regression: the menu is a native [popover], so it renders in the top layer,
  // where an absolutely-positioned box resolves against the initial containing
  // block — the document origin. floatingui.js computes viewport coordinates
  // (strategy: 'fixed'), so the two agreed only at scrollTop 0 and the menu
  // opened off-screen by exactly the scroll distance.
  // Tall enough that the (unstyled, in this rig) menu genuinely fits below its
  // trigger, so "off screen" can only mean the drift this test is about.
  await page.setViewportSize({ width: 800, height: 900 })
  await page.goto('/atom/docs/dropdown')
  await page.addStyleTag({ content: CONSUMER_ABSOLUTE_UTILITY })

  // The injected rule is only meaningful if it actually selects the menu — a
  // selector that matched nothing would let this test pass for the wrong reason.
  const targeted = await page.evaluate(() => document.querySelectorAll(
    'html body [data-atom-dropdown] [data-atom-menu][popover]'
  ).length)
  expect(targeted).toBeGreaterThan(0)

  const trigger = page.getByRole('button', { name: 'Stays open' })
  await trigger.scrollIntoViewIfNeeded()

  // A page that doesn't scroll would pass this test without proving anything.
  expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(100)

  const menu = rootFor(page, 'Stays open').locator('[data-atom-menu]')

  await trigger.click()
  await expect(menu).toBeVisible()

  const box = await menu.evaluate(el => {
    const rect = el.getBoundingClientRect()

    return {
      position: getComputedStyle(el).position,
      requestedTop: Math.round(parseFloat(el.style.top)),
      top: Math.round(rect.top),
      bottom: Math.round(rect.bottom),
      viewport: window.innerHeight,
    }
  })

  expect(box.position).toBe('fixed')

  // The sharp one: floating-ui writes viewport coordinates, so the rendered box
  // has to land on them. Absolutely positioned it missed by exactly window.scrollY.
  expect(box.top).toBe(box.requestedTop)

  expect(box.top).toBeGreaterThanOrEqual(0)
  expect(box.bottom).toBeLessThanOrEqual(box.viewport)
})
