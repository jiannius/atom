import { test, expect } from '@playwright/test'

// Escape closes the sidebar shell's stashed sidebar (below lg) and puts the focus back on the menu button, unless a layer
// above it (a modal, a dropdown) has the key first. On the consumer Tailwind build (`node tests/consumer/build.mjs` first).

const go = async (page, query = '') => {
  const css = await page.request.get('/atom/e2e/consumer-css/latest')
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/shell-collapse/one?tw=latest&crumbs=1${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const stashed = (page) => page.evaluate(() => document.body.hasAttribute('data-show-stashed-sidebar'))
const toggle = (page) => page.locator('[data-atom-sidebar-toggle]')
// on screen: not translated away to the start edge, not hidden
const sidebarShown = (page) => page.evaluate(() => {
  const el = document.querySelector('[data-atom-sidebar]')
  const r = el.getBoundingClientRect()
  return getComputedStyle(el).visibility === 'visible' && r.right > 0
})
const focusIsToggle = (page) => page.evaluate(() => document.activeElement?.matches('[data-atom-sidebar-toggle]'))

// the page under the backdrop is out of reach of the pointer, so the keyboard drives it
const press = (page, selector) => page.locator(selector).evaluate((el) => el.focus()).then(() => page.keyboard.press('Enter'))

test.describe('Escape and the stashed sidebar', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => { try { window.localStorage.removeItem('atom-sidebar-collapsed') } catch {} })
  })

  test('on a phone Escape closes the open sidebar and the focus is back on the menu button', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page)

    await toggle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)
    expect(await stashed(page)).toBe(true)

    // the focus is somewhere inside the sidebar, as after a Tab, and still comes back to the button
    await page.getByRole('link', { name: 'Page one' }).focus()
    await page.keyboard.press('Escape')

    expect(await stashed(page)).toBe(false)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(toggle(page)).toBeFocused()
  })

  test('Escape from the menu button itself closes it too', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page)

    await toggle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await page.keyboard.press('Escape')

    expect(await stashed(page)).toBe(false)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    expect(await focusIsToggle(page)).toBe(true)
  })

  test('Escape with the sidebar closed does nothing, and moves no focus', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page)

    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))

    await page.locator('[data-field]').focus()
    await page.keyboard.press('Escape')

    expect(await stashed(page)).toBe(false)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(page.locator('[data-field]')).toBeFocused()
    expect(errors).toEqual([])
  })

  test('from lg up Escape toggles nothing', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await go(page)

    await expect.poll(() => sidebarShown(page)).toBe(true)
    await page.locator('[data-field]').focus()
    await page.keyboard.press('Escape')

    expect(await stashed(page)).toBe(false)
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await expect(page.locator('[data-atom-sidebar-collapse]')).toHaveAttribute('aria-expanded', 'true')
    await expect(page.locator('[data-field]')).toBeFocused()
  })

  test('a stale stashed flag at lg is not closed by Escape either (it is dropped by the resize, not the key)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await go(page)

    await page.evaluate(() => document.body.setAttribute('data-show-stashed-sidebar', ''))
    await page.keyboard.press('Escape')
    expect(await stashed(page)).toBe(true)
  })

  test('an open modal takes the first Escape and the sidebar stays; the next one closes the sidebar', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page, '&layers=1')

    await toggle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await press(page, '[data-open-modal]')
    await expect(page.locator('dialog[data-modal]')).toBeVisible()
    await page.locator('[data-modal-field]').focus()

    await page.keyboard.press('Escape')
    await expect(page.locator('dialog[data-modal]')).toBeHidden()
    expect(await stashed(page)).toBe(true)
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await page.keyboard.press('Escape')
    expect(await stashed(page)).toBe(false)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(toggle(page)).toBeFocused()
  })

  test('an open dropdown takes the first Escape and the sidebar stays; the next one closes the sidebar', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page, '&layers=1')

    await toggle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await press(page, '[data-open-dropdown]')
    const menu = page.getByRole('menuitem', { name: 'Item one' })
    await expect(menu).toBeVisible()

    await page.keyboard.press('Escape')
    await expect(menu).toBeHidden()
    expect(await stashed(page)).toBe(true)
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await page.keyboard.press('Escape')
    expect(await stashed(page)).toBe(false)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(toggle(page)).toBeFocused()
  })

  test('an open select list takes the first Escape and the sidebar stays; the next one closes the sidebar', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page, '&layers=1')

    await toggle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await press(page, '[data-select-wrap] [role="combobox"]')
    const option = page.getByRole('option', { name: 'Sent' })
    await expect(option).toBeVisible()

    await page.keyboard.press('Escape')
    await expect(option).toBeHidden()
    expect(await stashed(page)).toBe(true)
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await page.keyboard.press('Escape')
    expect(await stashed(page)).toBe(false)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(toggle(page)).toBeFocused()
  })

  test('a repeated Escape (a held key) and one that ends an IME composition leave the sidebar open', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page)

    await toggle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await page.evaluate(() => {
      const fire = (init) => window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', bubbles: true, cancelable: true, ...init }))
      fire({ repeat: true })
      fire({ isComposing: true })
    })
    expect(await stashed(page)).toBe(true)
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await page.keyboard.press('Escape')
    expect(await stashed(page)).toBe(false)
  })

  test('the press that closes the sidebar does not reach the page under it', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page)

    await page.evaluate(() => {
      window.__escapes = 0
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') window.__escapes++ })
    })
    await toggle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await page.keyboard.press('Escape')

    expect(await stashed(page)).toBe(false)
    expect(await page.evaluate(() => window.__escapes)).toBe(0)

    // with the sidebar closed the key is the page's again
    await page.keyboard.press('Escape')
    expect(await page.evaluate(() => window.__escapes)).toBe(1)
  })

  test('a toast on screen (a manual popover that never takes Escape) does not keep the sidebar open', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page)

    await toggle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await page.evaluate(() => {
      const toast = document.createElement('div')
      toast.setAttribute('popover', 'manual')
      toast.setAttribute('data-atom-toast', '')
      toast.textContent = 'Saved'
      document.body.appendChild(toast)
      toast.showPopover()
    })
    await page.keyboard.press('Escape')

    expect(await stashed(page)).toBe(false)
    await expect.poll(() => sidebarShown(page)).toBe(false)
  })

  test('the account menu in the bar closes on its own Escape first', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await go(page)

    await toggle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await press(page, '[data-atom-header] button[aria-label="Account"]')
    const item = page.getByRole('menuitem', { name: 'Settings' })
    await expect(item).toBeVisible()

    await page.keyboard.press('Escape')
    await expect(item).toBeHidden()
    expect(await stashed(page)).toBe(true)

    await page.keyboard.press('Escape')
    expect(await stashed(page)).toBe(false)
  })
})
