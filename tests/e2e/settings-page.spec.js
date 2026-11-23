import { test, expect } from '@playwright/test'

// A settings screen: <atom:page.columns> with a `nav` slot, in the sidebar shell (/atom/e2e/shell-settings, a Livewire component: eleven nav items, the second
// group expandable and collapsed, the last item current, a form with `form.actions bar dirty`). Beside the forms the nav is a sticky 200px column and the
// save bar spans nav and forms; below 768px of row the nav is a strip above them and the bar is the plain bar. On the consumer's Tailwind (the rig has none),
// at the newest 4.x (latest) and the hosts' minimum (floor).

const GAP = 22
const NAV = 200
const PAD = 16

const open = async (page, { width, height = 800, tw = 'latest', query = '' }) => {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.setViewportSize({ width, height })
  await page.addInitScript(() => { try { window.localStorage.removeItem('atom-sidebar-collapsed') } catch {} })
  await page.goto(`/atom/e2e/shell-settings?tw=${tw}${query}`)
  await page.evaluate(() => document.fonts.ready)
  await page.waitForFunction(() => window.Alpine !== undefined && window.Livewire !== undefined)
}

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }
})

const strip = (page) => page.locator('[data-atom-page-columns-nav] [data-atom-navlist]')
const items = (page) => page.locator('[data-atom-page-columns-nav] [data-atom-navlist-item]')
const current = (page) => page.locator('[data-atom-page-columns-nav] [aria-current="page"]')
const inside = (item) => item.evaluate((el) => {
  const r = el.getBoundingClientRect()
  const s = el.closest('[data-atom-navlist]').getBoundingClientRect()

  return r.left >= s.left - 0.5 && r.right <= s.right + 0.5
})
const noSideScroll = (page) => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)

for (const tw of ['latest', 'floor']) {
  test.describe(`settings page, ${tw} consumer build`, () => {
    test('1440px: the nav is a column beside the forms, and the save bar spans both with its content where the forms start', async ({ page }) => {
      await open(page, { width: 1440, tw })

      const nav = await box(strip(page))
      const main = await box(page.locator('[data-atom-page-columns-main]'))
      const bar = await box(page.getByTestId('settings-bar'))
      const start = await box(page.getByTestId('settings-start'))
      const cancel = await box(page.getByTestId('settings-cancel'))
      const save = await box(page.getByTestId('settings-save'))

      expect(Math.abs(nav.width - NAV), `nav ${nav.width}`).toBeLessThanOrEqual(0.5)
      expect(Math.abs(main.x - nav.right - GAP)).toBeLessThanOrEqual(1)
      expect(nav.y, 'the nav is on the forms\' top line').toBeLessThanOrEqual(main.y + 1)

      // the bar: starts where the nav starts, ends where the forms end; a 1px border sits inside its edge, then the 16px of padding
      expect(Math.abs(bar.x - nav.x), 'the bar starts at the nav').toBeLessThanOrEqual(1)
      expect(Math.abs(bar.right - main.right), 'the bar ends at the forms').toBeLessThanOrEqual(1)
      expect(Math.abs(start.x - (main.x + PAD + 1)), 'its label starts where it would in a bar with no nav').toBeLessThanOrEqual(1)
      expect(Math.abs(cancel.right - (main.right - PAD - 1)), 'its buttons end there too').toBeLessThanOrEqual(1)
      expect(save.right).toBeLessThanOrEqual(cancel.x)
      expect(await noSideScroll(page)).toBe(true)
    })

    test('the nav stays in view while the page scrolls: 1rem under the top of the page (the shell\'s bar is not drawn)', async ({ page }) => {
      await open(page, { width: 1440, tw, height: 700 })

      for (const y of [300, 900, 1500]) {
        await page.evaluate((to) => window.scrollTo(0, to), y)
        await page.waitForTimeout(50)

        // the column's box is 1rem under the top less its 6px of bleed (the content is 1rem under it)
        const nav = await box(page.locator('[data-atom-page-columns-nav]'))

        expect(Math.abs(nav.y - 10), `scrolled ${y}: nav top ${nav.y}`).toBeLessThanOrEqual(1)
      }

      expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(0)
    })

    test('390px: the nav is a strip above the forms, in one row with the current item inside it, and the bar is the plain bar', async ({ page }) => {
      await open(page, { width: 390, tw })

      const nav = await box(page.locator('[data-atom-page-columns-nav]'))
      const main = await box(page.locator('[data-atom-page-columns-main]'))
      const bar = await box(page.getByTestId('settings-bar'))
      const start = await box(page.getByTestId('settings-start'))

      expect(nav.bottom, 'the nav is above the forms').toBeLessThanOrEqual(main.y + 1)
      expect(Math.abs(main.y - nav.bottom - GAP)).toBeLessThanOrEqual(1)

      // eleven items, one row
      const tops = await items(page).evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().top)))

      expect(tops).toHaveLength(11)
      expect(new Set(tops).size, `tops ${tops}`).toBe(1)

      // no group label or disclosure, the collapsed group's items shown
      for (const el of await page.locator('[data-atom-page-columns-nav] [data-atom-navlist-group-label], [data-atom-page-columns-nav] [data-atom-navlist-group-toggle]').all()) await expect(el).toBeHidden()
      await expect(page.getByTestId('nav-members')).toBeVisible()

      // it scrolls sideways, and the current item (the last) was scrolled into view, by the strip alone
      const state = await strip(page).evaluate((el) => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth, scrollLeft: el.scrollLeft }))

      expect(state.scrollWidth).toBeGreaterThan(state.clientWidth + 100)
      expect(state.scrollLeft).toBeGreaterThan(0)
      await expect(current(page)).toHaveCount(1)
      expect(await inside(current(page))).toBe(true)
      expect(await page.evaluate(() => [window.scrollX, window.scrollY])).toEqual([0, 0])
      expect(await noSideScroll(page)).toBe(true)

      // the bar has no offset: the card is the forms' width, the label inside its padding
      expect(Math.abs(bar.x - main.x)).toBeLessThanOrEqual(1)
      expect(Math.abs(bar.right - main.right)).toBeLessThanOrEqual(1)
      expect(Math.abs(start.x - (main.x + PAD + 1))).toBeLessThanOrEqual(1)
    })
  })
}

test.describe('settings page, the strip and a Livewire round trip', () => {
  test('a morph that moves the current item brings it into the strip, and the page stays where it was', async ({ page }) => {
    await open(page, { width: 390, query: '&section=profile' })

    const profile = page.getByTestId('nav-profile')

    await expect(profile).toHaveAttribute('aria-current', 'page')
    expect(await inside(profile)).toBe(true)
    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBe(0)

    await page.evaluate(() => window.scrollTo(0, 120))
    await page.evaluate(() => window.Livewire.all().find((c) => c.name === 'atom-e2e-settings-page').$wire.select('domains'))

    await expect(page.getByTestId('nav-domains')).toHaveAttribute('aria-current', 'page')
    await expect.poll(() => inside(page.getByTestId('nav-domains'))).toBe(true)
    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)
    expect(await page.evaluate(() => [window.scrollX, window.scrollY])).toEqual([0, 120])

    // and back: the first item is in view again
    await page.evaluate(() => window.Livewire.all().find((c) => c.name === 'atom-e2e-settings-page').$wire.select('profile'))
    await expect(profile).toHaveAttribute('aria-current', 'page')
    await expect.poll(() => inside(profile)).toBe(true)
  })

  test('a morph that leaves the current item alone does not scroll back a strip the reader moved', async ({ page }) => {
    await open(page, { width: 390 })

    await expect.poll(() => strip(page).evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)

    await strip(page).evaluate((el) => { el.dispatchEvent(new Event('pointerdown')); el.scrollLeft = 0 })
    await page.evaluate(() => window.Livewire.all().find((c) => c.name === 'atom-e2e-settings-page').$wire.$refresh())
    await page.waitForTimeout(300)

    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBe(0)
  })

  test('the collapsed group opens and closes beside the forms, and the strip needs no disclosure', async ({ page }) => {
    await open(page, { width: 1440 })

    await expect(page.getByTestId('nav-general')).toBeHidden()
    await page.locator('[data-atom-page-columns-nav] [data-atom-navlist-group-toggle]').click()
    await expect(page.getByTestId('nav-general')).toBeVisible()

    // narrow the window: the same group's items are in the strip whichever way it was left
    await page.setViewportSize({ width: 390, height: 800 })
    await expect(page.getByTestId('nav-general')).toBeVisible()
    await page.setViewportSize({ width: 1440, height: 800 })
    await expect(page.getByTestId('nav-general')).toBeVisible()
  })

  test('a round trip that submits the form keeps the nav, the bar and the offset', async ({ page }) => {
    await open(page, { width: 1440 })

    await page.getByTestId('settings-save').click()
    await expect(page.getByTestId('settings-start')).toHaveText('Saved 1 times')

    const nav = await box(strip(page))
    const bar = await box(page.getByTestId('settings-bar'))

    expect(Math.abs(bar.x - nav.x)).toBeLessThanOrEqual(1)
  })
})
