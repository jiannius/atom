import { test, expect } from '@playwright/test'

// <atom:breadcrumbs> in the v4 sidebar shell (the trail persisted in the `crumbs` slot, 48px bar), on the consumer Tailwind
// build (`node tests/consumer/build.mjs` first), once at the hosts' minimum Tailwind and once at the newest 4.x. The fixture
// (tests/Fixtures/BreadcrumbsTrailFixture.php): /dashboard has one crumb, /two two, /customers five, /northwind six, /invoice
// eight, /long five with a very long last title, /reports one crumb. Sizes and structure, never text widths: a Linux fallback font (DejaVu)
// changes the widths, not what is asserted.

async function open (page, slug, { tw = 'latest', width = 1440, query = '' } = {}) {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.setViewportSize({ width, height: 800 })
  await page.goto(`/atom/e2e/breadcrumbs-trail/${slug}?tw=${tw}${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined)
  await page.evaluate(() => document.fonts.ready)
}

// n animation frames: what a deferred fit() needs to have run, without a sleep
const frames = (page, n = 6) => page.evaluate((count) => new Promise((resolve) => {
  let left = count
  const tick = () => (--left > 0 ? requestAnimationFrame(tick) : resolve())
  requestAnimationFrame(tick)
}), n)

const root = (page) => page.locator('[data-atom-breadcrumbs]')
const list = (page) => page.locator('[data-atom-crumb-list]')
const more = (page) => page.locator('[data-atom-crumb-more]')
const menu = (page) => page.locator('[data-atom-crumb-menu]')
const barHeight = (page) => page.locator('[data-atom-header]').evaluate((el) => el.getBoundingClientRect().height)

// the crumbs that show: in the flow, so not the absolutely positioned ones the list keeps for measuring
const shown = (page) => list(page).locator(':scope > li[data-atom-crumb="crumb"]:not(.invisible)')
const shownTitles = (page) => shown(page).evaluateAll((els) => els.map((el) => el.textContent.trim()))
const hiddenCount = (page) => root(page).evaluate((el) => window.Alpine.$data(el).hiddenCount)
const menuTitles = async (page) => (await menu(page).locator('[data-atom-menu-item]').evaluateAll((els) => els.map((el) => el.textContent.trim())))

// nothing wraps, nothing overflows the list, and the current page is not clipped unless the trail is fully collapsed
async function expectOneLine (page) {
  const lines = await shown(page).evaluateAll((els) => new Set(els.map((el) => { const r = el.getBoundingClientRect(); return Math.round(r.top + r.height / 2) })).size)
  expect(lines, 'one line').toBe(1)
  expect(await list(page).evaluate((el) => el.scrollWidth <= el.clientWidth), 'nothing overflows the list').toBe(true)
  expect(await barHeight(page), 'the bar keeps its height').toBe(48)
}

const fullTrail = ['Dashboard', 'Sales and Marketing Operations', 'Regional Distribution Centres', 'Asia Pacific and Oceania', 'Customer Accounts Receivable', 'Northwind Trading Company International Sdn. Bhd. Head Office Kuala Lumpur']

for (const tw of ['floor', 'latest']) {
  test.describe(`breadcrumbs, ${tw} consumer build`, () => {
    test.beforeEach(({ page }) => {
      page.on('pageerror', (error) => { throw error })
    })

    test('1440: a trail that does not fit keeps first, "...", parent and current on one line, and the menu lists exactly the rest', async ({ page }) => {
      await open(page, 'northwind', { tw })

      const hidden = await hiddenCount(page)
      expect(hidden).toBeGreaterThan(0)

      await expectOneLine(page)
      await expect(more(page)).toBeVisible()

      const titles = await shownTitles(page)
      expect(titles[0]).toBe(fullTrail[0])
      expect(titles.slice(-2)).toEqual(fullTrail.slice(-2))
      expect(titles).toEqual([fullTrail[0], ...fullTrail.slice(1 + hidden)])

      // the hidden crumbs, oldest first, are exactly what the menu lists
      await more(page).click()
      await expect(menu(page)).toBeVisible()
      expect(await menuTitles(page)).toEqual(fullTrail.slice(1, 1 + hidden))

      // a menu item is a link to that crumb
      const first = menu(page).locator('[data-atom-menu-item]').first()
      await expect(first).toHaveAttribute('href', /\/atom\/e2e\/breadcrumbs-trail\/sales$/)
      await first.click()
      await expect(page).toHaveURL(/\/breadcrumbs-trail\/sales/)
    })

    test('1440: collapses only as much as needed, so a wider window gives crumbs back', async ({ page }) => {
      await open(page, 'northwind', { tw, width: 1100 })
      const narrow = await hiddenCount(page)

      await page.setViewportSize({ width: 1440, height: 800 })
      await expect.poll(() => hiddenCount(page)).toBeLessThan(narrow)
      await expectOneLine(page)

      await page.setViewportSize({ width: 2600, height: 800 })
      await expect.poll(() => hiddenCount(page)).toBe(0)
      await expect(more(page)).toBeHidden()
      expect(await shownTitles(page)).toEqual(fullTrail)
      // fully expanded, the current page is not clipped
      expect(await page.locator('[data-atom-crumb-current]').evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true)
    })

    test('1440: two crumbs are not collapsed and read as before', async ({ page }) => {
      await open(page, 'two', { tw })

      expect(await shownTitles(page)).toEqual(['Reports', 'Monthly'])
      await expect(more(page)).toHaveCount(0)
      expect(await hiddenCount(page)).toBe(0)
      await expectOneLine(page)
      await expect(page.locator('[data-atom-crumb-current]')).toHaveAttribute('aria-current', 'page')
    })

    test('1440: a trail of five that fits is not collapsed', async ({ page }) => {
      await open(page, 'customers', { tw })

      expect(await hiddenCount(page)).toBe(0)
      expect(await shownTitles(page)).toEqual(fullTrail.slice(0, 5))
      await expect(more(page)).toBeHidden()
    })

    test('a long current page is truncated last, every truncated crumb has its full title, and the bar stays 48px', async ({ page }) => {
      await open(page, 'long', { tw, width: 1100 })

      const current = page.locator('[data-atom-crumb-current]')
      expect(await current.evaluate((el) => el.scrollWidth > el.clientWidth), 'the current page is truncated').toBe(true)
      expect(await current.evaluate((el) => getComputedStyle(el).textOverflow)).toBe('ellipsis')
      await expect(current).toHaveAttribute('title', /^Quarterly Consolidated Statement of Financial Position/)
      expect(await barHeight(page)).toBe(48)
      await expectOneLine(page)

      // an ancestor that is truncated carries its title too
      for (const link of await shown(page).locator('a').all()) {
        await expect(link).toHaveAttribute('title', /.+/)
      }

      // the current page gets the room: it is wider than any ancestor
      const widths = await shown(page).evaluateAll((els) => els.map((el) => el.getBoundingClientRect().width))
      expect(widths[widths.length - 1]).toBeGreaterThan(Math.max(...widths.slice(0, -1)))
    })

    test('with the middle fully collapsed the current page is what truncates', async ({ page }) => {
      await open(page, 'long', { tw, width: 1100 })

      expect(await hiddenCount(page)).toBe(2)
      expect((await shownTitles(page)).length).toBe(3)
      await expectOneLine(page)
      expect(await page.locator('[data-atom-crumb-current]').evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true)
    })

    test('390: only a back link to the parent, with the query string it was left with', async ({ page }) => {
      await open(page, 'customers', { tw, width: 390, query: '&status=open' })
      await page.locator('[data-go="northwind"]').click()
      await expect(page).toHaveURL(/breadcrumbs-trail\/northwind/)

      await expect(list(page)).toBeHidden()
      const back = page.locator('[data-atom-crumb-back]')
      await expect(back).toBeVisible()
      await expect(back).toContainText('Customer Accounts Receivable')

      const url = new URL(await back.getAttribute('href'), page.url())
      expect(url.pathname).toBe('/atom/e2e/breadcrumbs-trail/customers')
      expect(url.search).toBe('?tw=' + tw + '&status=open')
      expect(await barHeight(page)).toBe(48)

      // the shell's own "back" event goes the same way
      await page.evaluate(() => {
        window.__navigated = []
        Object.defineProperty(Livewire, 'navigate', { value: (url) => window.__navigated.push(url), configurable: true })
        window.dispatchEvent(new CustomEvent('navigate-back'))
      })
      expect((await page.evaluate(() => window.__navigated))[0]).toContain('status=open')
    })

    test('390: a single crumb is still the heading, and no back link', async ({ page }) => {
      await open(page, 'dashboard', { tw, width: 390 })

      await expect(root(page).locator('[data-atom-heading]')).toHaveText('Dashboard')
      await expect(page.locator('[data-atom-crumb-back]')).toHaveCount(0)
    })

    test('resizing 1440 to 900 to 390 and back recomputes, with no error and no flicker loop', async ({ page }) => {
      const errors = []
      page.on('pageerror', (error) => errors.push(error.message))
      page.on('console', (message) => message.type() === 'error' && errors.push(message.text()))

      // "ResizeObserver loop completed with undelivered notifications" is a window `error` event, which Playwright does not
      // report as a pageerror: collect them from the start
      await page.addInitScript(() => {
        window.__windowErrors = []
        window.addEventListener('error', (event) => window.__windowErrors.push(event.message))
      })

      await open(page, 'invoice', { tw })

      // count what the list re-renders from here on
      await page.evaluate(() => {
        window.__mutations = 0
        new MutationObserver((records) => { window.__mutations += records.length }).observe(document.querySelector('[data-atom-crumb-list]'), { childList: true, subtree: true, attributes: true })
      })

      const states = []
      const heights = []
      for (const width of [900, 390, 900, 1440, 1100, 2600, 1100]) {
        await page.setViewportSize({ width, height: 800 })
        await frames(page)

        if (width === 390) {
          await expect(page.locator('[data-atom-crumb-back]')).toBeVisible()
          await expect(list(page)).toBeHidden()
        }
        else {
          await expect(list(page)).toBeVisible()
          await expectOneLine(page)
          heights.push((await list(page).boundingBox()).height)
        }

        states.push(await hiddenCount(page))
      }

      // the same width gives the same answer: 900 on the way down and on the way up, 1100 either side of 2600
      expect(states[0]).toBe(states[2])
      expect(states[4]).toBe(states[6])
      expect(states[3]).toBeLessThanOrEqual(states[2])
      expect(states[5]).toBe(0)
      // the "..." control is taller than a crumb: the list keeps its height whether it shows or not
      expect(new Set(heights).size, `list heights ${heights}`).toBe(1)

      // settled: nothing re-renders while idle (30 frames)
      const before = await page.evaluate(() => window.__mutations)
      await frames(page, 30)
      expect(await page.evaluate(() => window.__mutations)).toBe(before)

      expect(errors).toEqual([])
      expect(await page.evaluate(() => window.__windowErrors), 'no window error, the ResizeObserver loop one included').toEqual([])
      // a few dozen renders per resize, not a loop
      expect(before).toBeLessThan(150 * states.length)
    })

    test('the trail survives wire:navigate: the same element, still collapsed to fit, reset by a new home', async ({ page }) => {
      await open(page, 'customers', { tw, width: 1100 })
      await page.evaluate(() => { document.querySelector('[data-atom-breadcrumbs]').dataset.marker = 'same-element' })
      expect(await hiddenCount(page)).toBe(0)

      // one crumb deeper: it no longer fits, and the middle collapses
      await page.locator('[data-go="northwind"]').click()
      await expect(page).toHaveURL(/northwind/)
      await expect.poll(() => hiddenCount(page)).toBeGreaterThan(0)
      await expectOneLine(page)
      expect(await shownTitles(page)).toEqual([fullTrail[0], ...fullTrail.slice(1 + await hiddenCount(page))])

      await page.locator('[data-go="invoice"]').click()
      await expect(page).toHaveURL(/invoice$/)
      await expect.poll(() => shownTitles(page).then((titles) => titles[titles.length - 1])).toBe('Invoice 1042')
      await expectOneLine(page)

      // another trail's home: the old one is not carried over, and a single crumb is the heading again
      await page.locator('[data-go="reports"]').click()
      await expect(page).toHaveURL(/reports$/)
      await expect(root(page).locator('[data-atom-heading]')).toHaveText('Reports')
      await expect(list(page)).toHaveCount(0)

      await expect(page.locator('[data-atom-breadcrumbs][data-marker="same-element"]')).toHaveCount(1)
    })

    test('keyboard: Enter opens the menu on its first item, arrows walk it, Escape closes it and focus returns to the button', async ({ page }) => {
      await open(page, 'northwind', { tw, width: 1100 })
      expect(await hiddenCount(page)).toBeGreaterThan(1)

      await more(page).focus()
      await expect(more(page)).toHaveAttribute('aria-label', 'Show hidden breadcrumbs')
      await page.keyboard.press('Enter')

      await expect(menu(page)).toBeVisible()
      const items = menu(page).locator('[data-atom-menu-item]')
      await expect(items.first()).toBeFocused()

      await page.keyboard.press('ArrowDown')
      await expect(items.nth(1)).toBeFocused()
      await page.keyboard.press('ArrowUp')
      await expect(items.first()).toBeFocused()
      await page.keyboard.press('End')
      await expect(items.last()).toBeFocused()

      await page.keyboard.press('Escape')
      await expect(menu(page)).toBeHidden()
      await expect(more(page)).toBeFocused()

      // Space opens it too
      await page.keyboard.press('Space')
      await expect(menu(page)).toBeVisible()
      await page.keyboard.press('Escape')
      await expect(more(page)).toBeFocused()
    })

    test('the top-nav shell: the page does not move when the trail collapses or comes back', async ({ page }) => {
      const main = () => page.locator('[data-atom-main]').evaluate((el) => el.getBoundingClientRect().top)
      const crumbs = () => page.locator('[data-atom-header-crumbs]').evaluate((el) => el.getBoundingClientRect().height)

      // five crumbs that fit at 1440 and not at 700: the same page on either side of the threshold
      await open(page, 'customers', { tw, width: 1440, query: '&shell=header' })
      expect(await hiddenCount(page)).toBe(0)
      const top = await main()
      const row = await crumbs()

      await page.setViewportSize({ width: 700, height: 800 })
      await expect.poll(() => hiddenCount(page)).toBeGreaterThan(0)
      expect(await main(), 'collapsed').toBe(top)
      expect(await crumbs(), 'collapsed row').toBe(row)

      await page.setViewportSize({ width: 1440, height: 800 })
      await expect.poll(() => hiddenCount(page)).toBe(0)
      expect(await main(), 'expanded again').toBe(top)

      // and a trail that is collapsed from the start sits at the same place
      await open(page, 'northwind', { tw, width: 1440, query: '&shell=header' })
      expect(await hiddenCount(page)).toBeGreaterThan(0)
      expect(await main(), 'collapsed from the start').toBe(top)
    })

    test('a hostile URL in the trail: the phone back link has no href and does nothing', async ({ page }) => {
      await open(page, 'hostile-parent', { tw, width: 390 })

      const back = page.locator('[data-atom-crumb-back]')
      await expect(back).toContainText('Hostile Parent')
      await expect(back).not.toHaveAttribute('href', /.*/)

      const url = page.url()
      await back.click()

      expect(await page.evaluate(() => window.__pwned)).toBeUndefined()
      expect(page.url()).toBe(url)
    })

    test('a hostile URL in the trail: its row in the "..." menu has no href and does nothing', async ({ page }) => {
      await open(page, 'hostile', { tw, width: 1100 })
      await expect.poll(() => hiddenCount(page)).toBeGreaterThan(0)

      await more(page).click()
      const row = menu(page).locator('[data-atom-menu-item]', { hasText: 'Hostile Crumb' })
      await expect(row).toBeVisible()
      await expect(row).not.toHaveAttribute('href', /.*/)

      const url = page.url()
      await row.click()

      expect(await page.evaluate(() => window.__pwned)).toBeUndefined()
      expect(page.url()).toBe(url)
    })

    test('a hidden crumb is not reachable by keyboard or screen reader, only through the menu', async ({ page }) => {
      await open(page, 'northwind', { tw })

      const hidden = await hiddenCount(page)
      const invisible = list(page).locator(':scope > li[data-atom-crumb="crumb"].invisible')
      await expect(invisible).toHaveCount(hidden)

      for (const el of await invisible.all()) {
        expect(await el.evaluate((li) => getComputedStyle(li).visibility)).toBe('hidden')
      }
    })
  })
}
