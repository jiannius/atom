import { test, expect } from '@playwright/test'

// Drives the live demos on /atom/docs/context-menu (plain Alpine). Asserts
// open/close state + aria only, NOT pixel position — /atom/docs ships no
// Tailwind, so cursor geometry can't be verified here (see atom-testing memory).

// The trigger wrapper is display:contents (no box), so right-click the inner
// target element — the contextmenu event bubbles up to the wrapper's listener.
const targetOf = (root) => root.locator('[data-atom-context-menu-trigger] > *').first()

test('right-click opens the menu and an item click closes it', async ({ page }) => {
  await page.goto('/atom/docs/context-menu')

  const root = page.locator('[data-atom-context-menu]').first()
  const menu = root.locator('[data-atom-menu]')

  await expect(menu).toBeHidden()

  await targetOf(root).click({ button: 'right' })
  await expect(menu).toBeVisible()
  await expect(root).toHaveAttribute('data-open', '')

  await menu.getByRole('menuitem', { name: 'Edit' }).click()
  await expect(menu).toBeHidden()
})

test('escape closes the menu and clears data-open', async ({ page }) => {
  await page.goto('/atom/docs/context-menu')

  const root = page.locator('[data-atom-context-menu]').first()
  const menu = root.locator('[data-atom-menu]')

  await targetOf(root).click({ button: 'right' })
  await expect(menu).toBeVisible()

  await page.keyboard.press('Escape')
  await expect(menu).toBeHidden()
  await expect(root).not.toHaveAttribute('data-open', '')
})

test('a locked context menu stays open when an item is clicked', async ({ page }) => {
  await page.goto('/atom/docs/context-menu')

  const root = page.locator('[data-atom-context-menu]').nth(1) // locked demo
  const menu = root.locator('[data-atom-menu]')

  await targetOf(root).click({ button: 'right' })
  await expect(menu).toBeVisible()

  await menu.getByRole('menuitem').first().click()
  await expect(menu).toBeVisible()
})

test('the native browser context menu is suppressed', async ({ page }) => {
  await page.goto('/atom/docs/context-menu')

  const trigger = page.locator('[data-atom-context-menu-trigger]').first()

  // the component listens on its root, so it has prevented the event by the time it
  // reaches the document, which sees it prevented
  const prevented = await trigger.evaluate(
    (el) =>
      new Promise((resolve) => {
        document.addEventListener('contextmenu', (e) => resolve(e.defaultPrevented), { once: true })
        el.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true }))
      })
  )

  expect(prevented).toBe(true)
})

// A real right-click presses and releases the button where the pointer is (on a Mac the menu opens on the press).
// The popover is a light-dismiss one, and the release is outside it unless the panel is under the pointer, so a
// menu opened with its corner exactly at the pointer shut the moment the button came up: the rounded corner
// leaves the pointer outside the painted shape and hit-testing follows it. The synthetic `click({ button: 'right' })`
// above never tells; a held button does. /atom/docs ships no Tailwind, so the panel gets the radius `rounded-panel`
// would give it, and the target is pinned in view (its unstyled neighbours are page-sized icons).
const openWithRealRightClick = async (page, [px, py]) => {
  const root = page.locator('[data-atom-context-menu]').first()
  const menu = root.locator('[data-atom-menu]')

  await page.goto('/atom/docs/context-menu')
  await page.addStyleTag({ content: '[data-atom-context-menu] [data-atom-menu] { border-radius: 10px; padding: 4px; border: 1px solid #999 }' })

  await targetOf(root).evaluate((el, [x, y]) => {
    Object.assign(el.style, { position: 'fixed', left: `${x - 40}px`, top: `${y - 40}px`, width: '80px', height: '80px', zIndex: '9999' })
  }, [px, py])

  await page.mouse.move(px, py)
  await page.mouse.down({ button: 'right' })
  await page.waitForTimeout(100)
  await page.mouse.up({ button: 'right' })

  return { root, menu }
}

const expectStillOpenUnderPointer = async (page, root, menu, [px, py]) => {
  await page.waitForTimeout(300)

  await expect(menu).toBeVisible()
  await expect(root).toHaveAttribute('data-open', '')
  expect(await menu.evaluate((el) => el.matches(':popover-open'))).toBe(true)

  // the panel holds the pointer, so the next release is never read as a click outside
  expect(await menu.evaluate((el, [x, y]) => el.contains(document.elementFromPoint(x, y)), [px, py])).toBe(true)

  const panel = await menu.boundingBox()
  const { width, height } = page.viewportSize()
  expect(panel.x).toBeGreaterThanOrEqual(0)
  expect(panel.y).toBeGreaterThanOrEqual(0)
  expect(panel.x + panel.width).toBeLessThanOrEqual(width)
  expect(panel.y + panel.height).toBeLessThanOrEqual(height)
}

test('the menu stays open after the release of a real right-click', async ({ page }) => {
  const at = [300, 200]
  const { root, menu } = await openWithRealRightClick(page, at)

  await expectStillOpenUnderPointer(page, root, menu, at)
})

test('the menu stays open after a real right-click near the bottom-right corner', async ({ page }) => {
  const { width, height } = page.viewportSize()
  const at = [width - 20, height - 20]
  const { root, menu } = await openWithRealRightClick(page, at)

  // no room below or to the right: the panel flips and shifts, and still holds the pointer
  await expectStillOpenUnderPointer(page, root, menu, at)
})
