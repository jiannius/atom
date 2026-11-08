import { test, expect } from '@playwright/test'

// The sidebar shell's stashed sidebar below lg is a modal drawer (issue #169): the focus moves into it on open, the page behind is
// inert while it is open, the closed drawer is out of the tab order and the accessibility tree, and every close puts the focus back
// on the menu button. From lg up none of it applies. On the consumer Tailwind build (`node tests/consumer/build.mjs` first).

const go = async (page, query = '') => {
  const css = await page.request.get('/atom/e2e/consumer-css/latest')
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/shell-collapse/one?tw=latest&crumbs=1&actions=1&footer=1${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const PHONE = { width: 390, height: 800 }
const DESKTOP = { width: 1280, height: 800 }

const toggle = (page) => page.locator('[data-atom-sidebar-toggle]')
const stashed = (page) => page.evaluate(() => document.body.hasAttribute('data-show-stashed-sidebar'))
const inertTags = (page) => page.evaluate(() => [...document.querySelectorAll('[inert]')].map((el) => el.matches('[data-atom-main]') ? 'main' : el.matches('[data-atom-footer]') ? 'footer' : el.matches('[data-atom-shell-crumbs]') ? 'crumbs' : el.matches('[data-atom-shell-page-actions]') ? 'actions' : el.tagName).sort())
const PAGE_ALL = ['actions', 'crumbs', 'footer', 'main']
const where = (page) => page.evaluate(() => {
  const a = document.activeElement
  if (!a || a === document.body) return 'body'
  if (a.closest('[data-atom-sidebar]')) return 'drawer'
  if (a.matches('[data-atom-sidebar-toggle]')) return 'toggle'
  if (a.closest('[data-atom-main], [data-atom-footer], [data-atom-shell-crumbs], [data-atom-shell-page-actions]')) return 'page'
  if (a.closest('[data-atom-header]')) return 'bar'
  return 'other:' + a.tagName
})
const sidebarShown = (page) => page.evaluate(() => {
  const el = document.querySelector('[data-atom-sidebar]')
  return getComputedStyle(el).visibility === 'visible' && el.getBoundingClientRect().right > 0
})
// Tab n times from where the focus is, naming the place of each stop
const tabs = async (page, n, key = 'Tab') => {
  const stops = []
  for (let i = 0; i < n; i++) {
    await page.keyboard.press(key)
    stops.push(await where(page))
  }
  return stops
}
// the open drawer covers the menu button on a phone, so it is pressed from the keyboard
const pressToggle = async (page) => {
  await toggle(page).focus()
  await page.keyboard.press('Enter')
}
// What the browser's accessibility tree exposes (Chromium's, over CDP: Playwright's getByRole() does not look at `inert`), as "role: name"
const exposed = async (page) => {
  const cdp = await page.context().newCDPSession(page)
  const { nodes } = await cdp.send('Accessibility.getFullAXTree')
  await cdp.detach()
  return nodes.filter((n) => !n.ignored).map((n) => `${n.role?.value}: ${n.name?.value ?? ''}`)
}
const open = async (page) => {
  await toggle(page).click()
  await expect.poll(() => sidebarShown(page)).toBe(true)
}

test.describe('the stashed sidebar as a modal drawer', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => { try { window.localStorage.removeItem('atom-sidebar-collapsed') } catch {} })
  })

  test('opening moves the focus to the link of the page you are on', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)
    await open(page)

    await expect(page.getByRole('link', { name: 'Page one' })).toBeFocused()
  })

  test('with no current link the focus goes to the first focusable thing in the drawer', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page, '&nocurrent=1')
    await open(page)

    await expect(page.locator('[data-atom-sidebar]').getByRole('link', { name: 'Home' })).toBeFocused()
  })

  test('opening does not scroll the page or the drawer sideways, with reduced motion too', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(PHONE)
    await go(page, '&tall=1')
    await open(page)

    expect(await page.evaluate(() => [window.scrollX, window.scrollY, document.querySelector('[data-atom-sidebar]').scrollLeft])).toEqual([0, 0, 0])
  })

  test('the page behind is inert while the drawer is open and not after it closes; the menu button and the account menu are not', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)
    expect(await inertTags(page)).toEqual([])

    await open(page)
    expect(await inertTags(page)).toEqual(PAGE_ALL)
    expect(await page.evaluate(() => !!document.querySelector('[data-atom-sidebar-toggle]').closest('[inert]'))).toBe(false)
    expect(await page.evaluate(() => !!document.querySelector('[data-atom-header] button[aria-label="Account"]').closest('[inert]'))).toBe(false)
    expect(await page.evaluate(() => !!document.querySelector('[data-atom-sidebar]').closest('[inert]'))).toBe(false)

    // the page's own controls cannot be focused, and the accessibility tree has no field or host action
    await page.locator('[data-field]').evaluate((el) => el.focus())
    expect(await where(page)).not.toBe('page')
    const tree = await exposed(page)
    expect(tree).toContain('link: Page one')
    expect(tree.filter((n) => n.startsWith('textbox'))).toEqual([])
    expect(tree).not.toContain('button: Do it')

    await pressToggle(page)
    expect(await inertTags(page)).toEqual([])
    expect(await exposed(page)).toContain('button: Do it')
  })

  test('Tab and Shift+Tab from the ends of the drawer never reach the page', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)
    await open(page)

    const forward = await tabs(page, 14)
    expect(forward).not.toContain('page')
    expect(forward).toContain('drawer')
    expect(forward).toContain('toggle')

    const back = await tabs(page, 14, 'Shift+Tab')
    expect(back).not.toContain('page')
  })

  test('the menu button and the account menu in the bar stay operable while the drawer is open', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)
    await open(page)

    await page.locator('[data-atom-header] button[aria-label="Account"]').click()
    await expect(page.getByRole('menuitem', { name: 'Settings' }).last()).toBeVisible()
    await page.keyboard.press('Escape')
    expect(await stashed(page)).toBe(true)

    await pressToggle(page)
    expect(await stashed(page)).toBe(false)
  })

  test('closed below lg the drawer is out of the tab order and the accessibility tree', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)

    await expect(page.getByRole('link', { name: 'Page one' })).toHaveCount(0)
    expect(await page.evaluate(() => getComputedStyle(document.querySelector('[data-atom-sidebar]')).visibility)).toBe('hidden')
    await page.locator('[data-atom-sidebar] a[href="/app"]').evaluate((el) => el.focus())
    expect(await where(page)).toBe('body')

    // Tab through the whole page: the drawer is never a stop
    await page.locator('body').evaluate(() => document.activeElement?.blur())
    const stops = await tabs(page, 16)
    expect(stops).not.toContain('drawer')
    expect(stops).toContain('toggle')

    // open, then closed again: back to hidden
    await open(page)
    await expect(page.getByRole('link', { name: 'Page one' })).toHaveCount(1)
    await pressToggle(page)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(page.getByRole('link', { name: 'Page one' })).toHaveCount(0)
  })

  for (const [name, close] of [
    ['Escape', (page) => page.keyboard.press('Escape')],
    ['a click on the backdrop', (page) => page.mouse.click(360, 500)],
    ['the menu button', (page) => pressToggle(page)],
  ]) {
    test(`closing with ${name} puts the focus back on the menu button`, async ({ page }) => {
      await page.setViewportSize(PHONE)
      await go(page)
      await open(page)
      expect(await where(page)).toBe('drawer')

      await close(page)

      expect(await stashed(page)).toBe(false)
      await expect(toggle(page)).toBeFocused()
      expect(await inertTags(page)).toEqual([])
      await expect.poll(() => sidebarShown(page)).toBe(false)
    })
  }

  test('resizing from narrow to wide with the drawer open leaves a usable page and sidebar', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)
    await open(page)

    await page.setViewportSize(DESKTOP)
    await expect.poll(() => stashed(page)).toBe(false)
    expect(await inertTags(page)).toEqual([])
    expect(await sidebarShown(page)).toBe(true)
    await page.getByRole('link', { name: 'Page two' }).focus()
    await expect(page.getByRole('link', { name: 'Page two' })).toBeFocused()
    await page.locator('[data-field]').focus()
    await expect(page.locator('[data-field]')).toBeFocused()

    // and back to narrow: closed, hidden, nothing inert, and it opens again
    await page.setViewportSize(PHONE)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    expect(await inertTags(page)).toEqual([])
    await open(page)
    expect(await inertTags(page)).toEqual(PAGE_ALL)
  })

  test('resizing from wide to narrow and back with the drawer closed', async ({ page }) => {
    await page.setViewportSize(DESKTOP)
    await go(page)
    await expect(page.getByRole('link', { name: 'Page one' })).toHaveCount(1)

    await page.setViewportSize(PHONE)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(page.getByRole('link', { name: 'Page one' })).toHaveCount(0)

    await page.setViewportSize(DESKTOP)
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await page.getByRole('link', { name: 'Page one' }).focus()
    await expect(page.getByRole('link', { name: 'Page one' })).toBeFocused()
  })

  test('from lg up the sidebar is Tab-reachable, nothing is inert, and Ctrl+B still collapses it', async ({ page }) => {
    await page.setViewportSize(DESKTOP)
    await go(page)

    expect(await inertTags(page)).toEqual([])
    await page.locator('body').evaluate(() => document.activeElement?.blur())
    const stops = await tabs(page, 6)
    expect(stops).toContain('drawer')

    await page.getByRole('link', { name: 'Go to the other page' }).focus()
    await page.keyboard.press('Control+b')
    await expect(page.locator('[data-atom-sidebar-collapse]')).toHaveAttribute('aria-expanded', 'false')
    await expect.poll(() => sidebarShown(page)).toBe(false)
    expect(await inertTags(page)).toEqual([])

    await page.keyboard.press('Control+b')
    await expect(page.locator('[data-atom-sidebar-collapse]')).toHaveAttribute('aria-expanded', 'true')
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await page.getByRole('link', { name: 'Page one' }).focus()
    await expect(page.getByRole('link', { name: 'Page one' })).toBeFocused()
  })

  test('a stale flag at lg locks nothing', async ({ page }) => {
    await page.setViewportSize(DESKTOP)
    await go(page)

    await page.evaluate(() => document.body.setAttribute('data-show-stashed-sidebar', ''))
    await page.waitForTimeout(100)
    expect(await inertTags(page)).toEqual([])
    await page.locator('[data-field]').focus()
    await expect(page.locator('[data-field]')).toBeFocused()
  })

  test('a flag left at lg takes hold when the window narrows', async ({ page }) => {
    await page.setViewportSize(DESKTOP)
    await go(page)
    await page.evaluate(() => document.body.setAttribute('data-show-stashed-sidebar', ''))

    await page.setViewportSize(PHONE)
    await expect.poll(() => inertTags(page)).toEqual(PAGE_ALL)
    await expect.poll(() => where(page)).toBe('drawer')
    await expect.poll(() => sidebarShown(page)).toBe(true)
  })

  test('the server renders the page with nothing inert and the drawer closed', async ({ page }) => {
    const res = await page.request.get('/atom/e2e/shell-collapse/one?crumbs=1&actions=1&footer=1')
    const html = await res.text()

    expect(html).not.toMatch(/<[a-z][^>]*\sinert[\s=>]/)
    expect(html).toMatch(/<body[^>]*>/)
    expect(html).not.toMatch(/<body[^>]*data-show-stashed-sidebar/)
  })

  test('the closing drawer stays on screen for its slide out and is hidden after it', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)
    await open(page)

    const during = await page.evaluate(() => {
      document.body.removeAttribute('data-show-stashed-sidebar')
      return getComputedStyle(document.querySelector('[data-atom-sidebar]')).visibility
    })
    expect(during).toBe('visible')
    await expect.poll(() => page.evaluate(() => getComputedStyle(document.querySelector('[data-atom-sidebar]')).visibility)).toBe('hidden')
  })

  test('a Livewire morph under the open drawer keeps the page inert, and under the closed one makes nothing inert', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page, '&morph=1')
    const bump = () => page.evaluate(() => window.Livewire.all().forEach((c) => c.$wire.bump()))
    const counts = () => page.evaluate(() => [...document.querySelectorAll('[data-testid="shell-count"]')].map((el) => el.textContent))

    await bump()
    await expect.poll(counts).toEqual(['1', '1'])
    expect(await inertTags(page)).toEqual([])
    await expect.poll(() => sidebarShown(page)).toBe(false)

    await open(page)
    await bump()
    await expect.poll(counts).toEqual(['2', '2'])
    expect(await inertTags(page)).toEqual(PAGE_ALL)
    // what the morph put in the page is under the inert ancestors
    expect(await page.evaluate(() => [...document.querySelectorAll('[data-morph] [data-testid="shell-bump"]')].every((el) => !!el.closest('[inert]')))).toBe(true)

    await pressToggle(page)
    await bump()
    await expect.poll(counts).toEqual(['3', '3'])
    expect(await inertTags(page)).toEqual([])
  })

  test('a link in the drawer that wire:navigates leaves the new page closed, not inert, and the drawer opens again', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))
    await page.evaluate(() => { window.__same = true })

    await open(page)
    await page.getByRole('link', { name: 'Page two' }).click()
    await expect(page.locator('[data-fixture-page="two"]')).toBeVisible()

    // wire:navigate swapped the page, not reloaded it
    expect(await page.evaluate(() => window.__same)).toBe(true)
    expect(await stashed(page)).toBe(false)
    expect(await inertTags(page)).toEqual([])
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(page.getByRole('link', { name: 'Page two' })).toHaveCount(0)
    await expect(toggle(page)).toBeVisible()

    // the new page's drawer is a working one
    await open(page)
    expect(await inertTags(page)).toEqual(PAGE_ALL)
    await expect(page.getByRole('link', { name: 'Page two' })).toBeFocused()
    await page.keyboard.press('Escape')
    await expect(toggle(page)).toBeFocused()
    expect(await inertTags(page)).toEqual([])
    expect(errors).toEqual([])
  })

  test('a modal a host opens over the open drawer works, and closing it puts the drawer back as it was', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page, '&layers=1')
    await open(page)
    expect(await inertTags(page)).toEqual(PAGE_ALL)

    // opened from the page, where the focus was before the drawer came up: nothing in the drawer has it
    await page.evaluate(() => document.activeElement.blur())
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-modal-show', { detail: { name: 'collapse-modal' } })))
    await expect(page.locator('dialog[data-modal]')).toBeVisible()
    expect(await inertTags(page)).toEqual([])
    await page.locator('[data-modal-field]').fill('typed')
    await expect(page.locator('[data-modal-field]')).toHaveValue('typed')

    await page.keyboard.press('Escape')
    await expect(page.locator('dialog[data-modal]')).toBeHidden()
    expect(await stashed(page)).toBe(true)
    await expect.poll(() => inertTags(page)).toEqual(PAGE_ALL)
    // the focus is not lost to the (inert) page the modal was opened from
    await expect.poll(() => where(page)).toBe('drawer')

    await page.keyboard.press('Escape')
    await expect(toggle(page)).toBeFocused()
    expect(await inertTags(page)).toEqual([])
  })

  test('a toast shown while the drawer is open is on screen, not inert, and can be clicked', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)
    await open(page)

    await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-toast-show', { detail: { message: 'Saved from the page', delay: 0, closeable: true } })))
    const toast = page.locator('[data-atom-toast]')
    await expect(toast).toBeVisible()
    await expect(toast).toContainText('Saved from the page')
    expect(await toast.evaluate((el) => !!el.closest('[inert]'))).toBe(false)
    expect(await toast.evaluate((el) => {
      const r = el.getBoundingClientRect()
      const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
      return el.contains(hit)
    })).toBe(true)
    expect(await stashed(page)).toBe(true)
  })

  test('a dropdown inside the drawer opens', async ({ page }) => {
    await page.setViewportSize(PHONE)
    await go(page)
    await open(page)

    await page.locator('[data-atom-sidebar] [data-atom-account-trigger]').click()
    await expect(page.getByRole('menuitem', { name: 'Settings' }).first()).toBeVisible()
  })
})
