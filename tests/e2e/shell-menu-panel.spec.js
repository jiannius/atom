import { test, expect } from '@playwright/test'

// The header shell's menu panel below lg is a modal layer (issue #172), the same behaviour as the sidebar's drawer (#169, shell-drawer.spec.js):
// the focus moves into the panel on open, the page behind is inert while it is open, a click elsewhere or a navigation closes it without
// pulling the focus back to the menu button, and a menu button press or Escape returns it there. From lg up none of it applies.
// The panel covers the top of the page (position: absolute under the bar, no backdrop) and does not push it down, so containment is right.
// On the consumer Tailwind build (`node tests/consumer/build.mjs` first).

const go = async (page, query = '') => {
  const css = await page.request.get('/atom/e2e/consumer-css/latest')
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/shell-header-wide?tw=latest&crumbs=1&footer=1${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const PHONE = { width: 390, height: 800 }
const DESKTOP = { width: 1280, height: 800 }
const PAGE_ALL = ['crumbs', 'footer', 'main']

const button = (page) => page.locator('[data-atom-header-menu-button]')
const panel = (page) => page.locator('[data-atom-header-nav]')
const isOpen = (page) => page.evaluate(() => document.querySelector('[data-atom-header-nav]').hasAttribute('data-open'))
const inertTags = (page) => page.evaluate(() => [...document.querySelectorAll('[inert]')].map((el) => el.matches('[data-atom-main]') ? 'main' : el.matches('[data-atom-footer]') ? 'footer' : el.matches('[data-atom-shell-crumbs]') ? 'crumbs' : el.tagName).sort())
// the page nodes this page has (a link that navigates drops the ?crumbs and ?footer of the first page)
const pageNodes = (page) => page.evaluate(() => [...document.querySelectorAll('[data-atom-main], [data-atom-footer], [data-atom-shell-crumbs]')].map((el) => el.matches('[data-atom-main]') ? 'main' : el.matches('[data-atom-footer]') ? 'footer' : 'crumbs').sort())
const where = (page) => page.evaluate(() => {
  const a = document.activeElement
  if (!a || a === document.body) return 'body'
  if (a.matches('[data-atom-header-menu-button]')) return 'button'
  if (a.closest('[data-atom-header-nav]')) return 'panel'
  if (a.closest('[data-atom-header]')) return 'bar'
  if (a.closest('dialog')) return 'dialog'
  if (a.closest('[data-atom-main], [data-atom-footer], [data-atom-shell-crumbs]')) return 'page'
  return 'other:' + a.tagName
})
const tabs = async (page, n, key = 'Tab') => {
  const stops = []
  for (let i = 0; i < n; i++) {
    await page.keyboard.press(key)
    stops.push(await where(page))
  }
  return stops
}
const open = async (page) => {
  await button(page).click()
  await expect.poll(() => isOpen(page)).toBe(true)
}
const exposed = async (page) => {
  const cdp = await page.context().newCDPSession(page)
  const { nodes } = await cdp.send('Accessibility.getFullAXTree')
  await cdp.detach()
  return nodes.filter((n) => !n.ignored).map((n) => `${n.role?.value}: ${n.name?.value ?? ''}`)
}
const modalEvent = (page) => page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-modal-show', { detail: { name: 'header-modal' } })))

test.describe('the header menu panel as a modal layer', () => {
  test.use({ viewport: PHONE })

  test('opening moves the focus to the link of the page you are on', async ({ page }) => {
    await go(page)
    await open(page)

    await expect(page.locator('[data-atom-header-nav] a[aria-current="page"]')).toBeFocused()
  })

  test('with no current link the focus goes to the first focusable thing in the panel', async ({ page }) => {
    await go(page, '&page=none')
    await open(page)

    const first = await page.evaluate(() => {
      const nav = document.querySelector('[data-atom-header-nav]')
      const shown = (el) => el.getClientRects().length > 0
      return [...nav.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')].find(shown) === document.activeElement
    })
    expect(first).toBe(true)
    expect(await where(page)).toBe('panel')
  })

  test('the page behind is inert while the panel is open, the bar is not, and it is let go on close', async ({ page, browserName }) => {
    test.skip(browserName !== 'chromium', "reads the accessibility tree over CDP, which only Chromium has")
    await go(page)
    expect(await inertTags(page)).toEqual([])

    await open(page)
    expect(await inertTags(page)).toEqual(PAGE_ALL)
    expect(await page.evaluate(() => !!document.querySelector('[data-atom-header]').closest('[inert]') || !!document.querySelector('[data-atom-header] [inert]'))).toBe(false)

    await page.locator('[data-field]').evaluate((el) => el.focus())
    expect(await where(page)).not.toBe('page')
    const tree = await exposed(page)
    expect(tree.some((n) => n.startsWith('link: Purchase orders'))).toBe(true)
    // the panel's own search row is a textbox; the page's field is not
    expect(tree.filter((n) => n.startsWith('textbox'))).toEqual(['textbox: Search'])
    expect(tree.some((n) => n.startsWith('link: Footer link'))).toBe(false)

    await button(page).click()
    expect(await inertTags(page)).toEqual([])
    expect((await exposed(page)).some((n) => n.startsWith('link: Footer link'))).toBe(true)
  })

  test('Tab and Shift+Tab never reach the page behind', async ({ page }) => {
    await go(page)
    await open(page)

    const forward = await tabs(page, 16)
    expect(forward).not.toContain('page')
    expect(forward).toContain('panel')
    expect(forward).toContain('button')

    expect(await tabs(page, 16, 'Shift+Tab')).not.toContain('page')
  })

  test('the menu button closes it and keeps the focus on itself', async ({ page }) => {
    await go(page)
    await open(page)

    // a script click, which does not focus the button the way a mouse click does in Safari
    await page.evaluate(() => document.querySelector('[data-atom-header-menu-button]').click())
    expect(await isOpen(page)).toBe(false)
    await expect(button(page)).toBeFocused()
    expect(await inertTags(page)).toEqual([])
  })

  test('Escape closes it and returns the focus to the menu button', async ({ page }) => {
    await go(page)
    await open(page)
    expect(await where(page)).toBe('panel')

    await page.keyboard.press('Escape')
    expect(await isOpen(page)).toBe(false)
    await expect(button(page)).toBeFocused()
    expect(await inertTags(page)).toEqual([])
  })

  test('a click on the page closes it, lets the page go, and does not pull the focus back to the menu button', async ({ page }) => {
    await go(page)
    await open(page)

    // the page under the panel is inert, so the click lands on the document: it still counts as outside
    await page.mouse.click(200, 760)
    await expect.poll(() => isOpen(page)).toBe(false)
    expect(await inertTags(page)).toEqual([])
    expect(await where(page)).not.toBe('button')

    // and a click that lands on a live control outside the panel keeps the focus where it went
    await open(page)
    await page.locator('[data-atom-account-trigger]').click()
    await expect.poll(() => isOpen(page)).toBe(true) // inside the bar: not outside
  })

  test('a wire:navigate link closes it with the new page not inert, and Back comes back to a closed page', async ({ page }) => {
    await go(page, '&wire=1')
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))
    await page.evaluate(() => { window.__same = true })

    await open(page)
    await page.locator('[data-atom-header-nav] a', { hasText: 'Work orders' }).click()
    await expect(page.locator('[data-testid="pattern-title"]')).toHaveText('work-orders')

    expect(await page.evaluate(() => window.__same)).toBe(true)
    expect(await isOpen(page)).toBe(false)
    expect(await inertTags(page)).toEqual([])
    expect(await where(page)).not.toBe('panel')

    // the new page's panel works
    await open(page)
    expect(await inertTags(page)).toEqual(await pageNodes(page))
    expect(await inertTags(page)).toContain('main')
    await expect(page.locator('[data-atom-header-nav] a[aria-current="page"]')).toBeFocused()

    // Back from the open panel
    await page.goBack()
    await expect(page.locator('[data-testid="pattern-title"]')).toHaveText('purchase-orders')
    await page.waitForTimeout(200)
    expect(await isOpen(page)).toBe(false)
    expect(await inertTags(page)).toEqual([])
    expect(await where(page)).not.toBe('panel')
    expect(errors).toEqual([])

    await open(page)
    expect(await inertTags(page)).toEqual(PAGE_ALL)
  })

  test('a Livewire morph under the open panel keeps it open and the page inert', async ({ page }) => {
    await go(page, '&lw=1&lwmain=1')
    const bump = () => page.evaluate(() => window.Livewire.all().forEach((c) => c.$wire.bump()))
    const counts = () => page.evaluate(() => [...document.querySelectorAll('[data-testid="shell-count"]')].map((el) => el.textContent))

    await open(page)
    await bump()
    await expect.poll(counts).toEqual(['1', '1'])
    expect(await isOpen(page)).toBe(true)
    expect(await inertTags(page)).toEqual(PAGE_ALL)
    expect(await page.evaluate(() => [...document.querySelectorAll('[data-morph] [data-testid="shell-bump"]')].every((el) => !!el.closest('[inert]')))).toBe(true)

    await button(page).click()
    await bump()
    await expect.poll(counts).toEqual(['2', '2'])
    expect(await inertTags(page)).toEqual([])
  })

  test('resizing to lg with the panel open leaves a bar with nothing inert, and narrowing again finds it closed', async ({ page }) => {
    await go(page)
    await open(page)

    await page.setViewportSize(DESKTOP)
    await expect.poll(() => inertTags(page)).toEqual([])
    expect(await isOpen(page)).toBe(false)
    await expect(page.locator('[data-atom-header-nav] a', { hasText: 'Work orders' })).toBeVisible()
    await page.locator('[data-field]').focus()
    await expect(page.locator('[data-field]')).toBeFocused()

    await page.setViewportSize(PHONE)
    await expect(page.locator('[data-atom-header-nav] a', { hasText: 'Work orders' })).toBeHidden()
    expect(await inertTags(page)).toEqual([])
    await open(page)
    expect(await inertTags(page)).toEqual(PAGE_ALL)
  })

  test('at lg nothing is inert and the bar is as it was', async ({ page }) => {
    await page.setViewportSize(DESKTOP)
    await go(page)

    expect(await inertTags(page)).toEqual([])
    await expect(button(page)).toBeHidden()
    await page.locator('[data-atom-header-nav] a[aria-current="page"]').focus()
    await expect(page.locator('[data-atom-header-nav] a[aria-current="page"]')).toBeFocused()
    await page.evaluate(() => { document.querySelector('[data-atom-header-nav]').dispatchEvent(new Event('click', { bubbles: true })) })
    expect(await inertTags(page)).toEqual([])
  })

  test('a menu state left true at lg locks nothing', async ({ page }) => {
    await page.setViewportSize(DESKTOP)
    await go(page)

    await page.evaluate(() => { window.Alpine.$data(document.querySelector('[data-atom-header]')).menu = true })
    await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 100)))
    expect(await inertTags(page)).toEqual([])
    await page.locator('[data-field]').focus()
    await expect(page.locator('[data-field]')).toBeFocused()
  })

  test.describe('layers over the open panel', () => {
    test('a modal a host opens takes the focus, works, and closing it puts the panel back as it was', async ({ page }) => {
      await go(page, '&layers=1')
      await open(page)

      await page.evaluate(() => document.activeElement.blur())
      await modalEvent(page)
      await expect(page.locator('dialog[data-modal]')).toBeVisible()
      await expect.poll(() => where(page)).toBe('dialog')
      expect(await inertTags(page)).toEqual([])
      await page.locator('[data-modal-field]').fill('typed')
      await expect(page.locator('[data-modal-field]')).toHaveValue('typed')

      await page.keyboard.press('Escape')
      await expect(page.locator('dialog[data-modal]')).toBeHidden()
      await expect.poll(() => inertTags(page)).toEqual(PAGE_ALL)
      await expect.poll(() => where(page)).toBe('panel')
      expect(await isOpen(page)).toBe(true)
    })

    test('a modal removed while open gives the containment back', async ({ page }) => {
      await go(page, '&layers=1')
      await open(page)

      await page.evaluate(() => document.activeElement.blur())
      await modalEvent(page)
      await expect(page.locator('dialog[data-modal]')).toBeVisible()
      await page.evaluate(() => document.querySelector('dialog[data-modal]').remove())

      await expect.poll(() => inertTags(page)).toEqual(PAGE_ALL)
      await expect.poll(() => where(page)).toBe('panel')
    })

    test('a non-modal dialog, even a permanent one, lifts nothing', async ({ page }) => {
      await go(page)
      await page.evaluate(() => {
        const permanent = document.createElement('dialog')
        permanent.setAttribute('open', '')
        permanent.textContent = 'Always here'
        document.querySelector('[data-atom-main]').appendChild(permanent)
      })
      await open(page)
      expect(await inertTags(page)).toEqual(PAGE_ALL)

      await page.evaluate(() => {
        const shown = document.createElement('dialog')
        document.querySelector('[data-atom-main]').appendChild(shown)
        shown.show()
      })
      await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 50)))
      expect(await inertTags(page)).toEqual(PAGE_ALL)
    })

    test('a toast shown while the panel is open is on screen, not inert and can be hit', async ({ page }) => {
      await go(page)
      await open(page)

      await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-toast-show', { detail: { message: 'Saved from the page', delay: 0, closeable: true } })))
      const toast = page.locator('[data-atom-toast]')
      await expect(toast).toContainText('Saved from the page')
      expect(await toast.evaluate((el) => !!el.closest('[inert]'))).toBe(false)
      expect(await toast.evaluate((el) => {
        const r = el.getBoundingClientRect()
        return el.contains(document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2))
      })).toBe(true)
      expect(await isOpen(page)).toBe(true)
    })

    test('the context dropdown inside the panel opens and Escape closes it first', async ({ page }) => {
      await go(page)
      await open(page)

      await page.locator('[data-atom-shell-context] button').first().click()
      await expect(page.getByRole('menuitem', { name: 'Acme Trading' })).toBeVisible()
      await page.keyboard.press('Escape')
      await expect(page.getByRole('menuitem', { name: 'Acme Trading' })).toBeHidden()
      expect(await isOpen(page)).toBe(true)
    })
  })

  test('an inert the host put on the page is not taken off by the panel', async ({ page }) => {
    await go(page)
    await page.evaluate(() => { document.querySelector('[data-atom-main]').inert = true })

    await open(page)
    await button(page).click()

    expect(await inertTags(page)).toEqual(['main'])
  })

  test('init() run again without destroy() does not forget what it made inert', async ({ page }) => {
    await go(page)
    await open(page)

    await page.evaluate(() => window.Alpine.$data(document.querySelector('[data-atom-header]')).init())
    expect(await inertTags(page)).toEqual(PAGE_ALL)

    await button(page).click()
    expect(await inertTags(page)).toEqual([])
  })

  test('an unrelated DOM insertion moves neither the focus nor the panel\'s scroll', async ({ page }) => {
    await go(page)
    await open(page)

    await page.evaluate(() => document.activeElement.blur())
    await page.evaluate(() => {
      const row = document.createElement('p')
      row.textContent = 'A streamed row'
      document.querySelector('[data-atom-main]').appendChild(row)
    })
    await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 100)))

    expect(await where(page)).toBe('body')
    expect(await inertTags(page)).toEqual(PAGE_ALL)
  })

  test('search, "More" and the bar at 1024px are untouched by the layer', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 800 })
    await go(page)

    await expect(page.locator('[data-atom-header-more]')).toBeVisible()
    await page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]').click()
    await expect(page.locator('[data-atom-header-more] [data-atom-navlist-group-items] a').first()).toBeVisible()
    expect(await inertTags(page)).toEqual([])

    await page.locator('[data-atom-shell-search-button]').click()
    await expect(page.locator('[data-atom-shell-search] input')).toBeFocused()
    expect(await inertTags(page)).toEqual([])
  })
})
