import { test, expect } from '@playwright/test'

// A fixed trail: <atom:breadcrumbs :items> in both shells, drawn by the same engine as the component's trail (collapse, the phone's back link,
// the single-crumb heading, the empty bar) and NOT persisted. Fixture: /atom/e2e/breadcrumbs-static/{one|new|two|other|deep|hostile|hostile-parent|morph}
// (resources/views/e2e/breadcrumbs-static.blade.php, tests/Fixtures/BreadcrumbsStaticFixture.php), on the consumer Tailwind
// (`node tests/consumer/build.mjs` first). Sizes and structure, never text widths: a Linux fallback font changes the widths.

const url = (slug, query = '') => `/atom/e2e/breadcrumbs-static/${slug}${query}`
const root = (page) => page.locator('[data-atom-breadcrumbs]')
const list = (page) => page.locator('[data-atom-crumb-list]')
const more = (page) => page.locator('[data-atom-crumb-more]')
const menu = (page) => page.locator('[data-atom-crumb-menu]')
const back = (page) => page.locator('[data-atom-crumb-back]')
const shown = (page) => list(page).locator(':scope > li[data-atom-crumb="crumb"]:not(.invisible)')
const shownTitles = (page) => shown(page).evaluateAll((els) => els.map((el) => el.textContent.trim()))
const hiddenCount = (page) => root(page).evaluate((el) => window.Alpine.$data(el).hiddenCount)
const header = (page) => page.locator('[data-atom-main] h1[data-atom-page-header-title]')
const trailBuilt = (page, count) => page.waitForFunction((n) => {
  const el = document.querySelector('[data-atom-breadcrumbs]')
  return el && window.Alpine?.$data(el).built && window.Alpine.$data(el).breadcrumbs.length === n
}, count)

const deep = ['Dashboard', 'Sales and Marketing Operations', 'Regional Distribution Centres', 'Asia Pacific and Oceania', 'Customer Accounts Receivable', 'Northwind Trading Company International Sdn. Bhd. Head Office Kuala Lumpur']

async function open (page, slug, { width = 1440, query = '' } = {}) {
  const css = await page.request.get('/atom/e2e/consumer-css/latest')
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.setViewportSize({ width, height: 800 })
  await page.goto(url(slug, query))
  await page.waitForFunction(() => window.Alpine !== undefined)
  await page.evaluate(() => document.fonts.ready)
}

for (const shell of ['sidebar', 'header']) {
  test.describe(`a fixed trail, ${shell} shell`, () => {
    const query = shell === 'header' ? '?shell=header' : ''
    // what is drawn above the page: the sidebar's 48px bar, or the header shell's crumbs row under its (always drawn) bar
    const bar = (page) => page.locator(shell === 'header' ? '[data-atom-header-crumbs]' : '[data-atom-header]')
    const barHeight = (page) => bar(page).evaluate((el) => el.getBoundingClientRect().height)

    test.beforeEach(({ page }) => {
      page.on('pageerror', (error) => { throw error })
    })

    test('two items: a link and the current page, with no component behind it', async ({ page }) => {
      await open(page, 'two', { query })
      await trailBuilt(page, 2)

      expect(await shownTitles(page)).toEqual(['Reports', 'Monthly'])
      await expect(shown(page).first().locator('a')).toHaveAttribute('href', /\/breadcrumbs-static\/one$/)
      await expect(page.locator('[data-atom-crumb-current]')).toHaveAttribute('aria-current', 'page')
      await expect(more(page)).toHaveCount(0)
    })

    test('1100: a trail that does not fit collapses behind the "..." menu, which lists exactly the hidden ones', async ({ page }) => {
      await open(page, 'deep', { width: 1100, query })
      await trailBuilt(page, 6)

      const hidden = await hiddenCount(page)
      expect(hidden).toBeGreaterThan(0)
      await expect(more(page)).toBeVisible()

      const titles = await shownTitles(page)
      expect(titles[0]).toBe(deep[0])
      expect(titles.slice(-2)).toEqual(deep.slice(-2))
      expect(titles).toEqual([deep[0], ...deep.slice(1 + hidden)])
      // one line, and the current page is the one that truncates last
      expect(await list(page).evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true)

      await more(page).click()
      await expect(menu(page)).toBeVisible()
      expect(await menu(page).locator('[data-atom-menu-item]').evaluateAll((els) => els.map((el) => el.textContent.trim()))).toEqual(deep.slice(1, 1 + hidden))
      await expect(menu(page).locator('[data-atom-menu-item]').first()).toHaveAttribute('href', /\/breadcrumbs-static\/two$/)

      // a wider window gives the crumbs back
      await page.keyboard.press('Escape')
      await page.setViewportSize({ width: 2600, height: 800 })
      // the top-nav shell's crumbs row is a 1248px column, so only the sidebar shell can show the whole trail
      if (shell === 'sidebar') {
        await expect.poll(() => hiddenCount(page)).toBe(0)
        expect(await shownTitles(page)).toEqual(deep)
      }
      else {
        await expect.poll(() => hiddenCount(page)).toBeLessThanOrEqual(hidden)
      }
    })

    test('390: a phone gets the back link to the parent, and it navigates', async ({ page }) => {
      await open(page, 'deep', { width: 390, query })
      await trailBuilt(page, 6)

      await expect(back(page)).toBeVisible()
      await expect(back(page)).toContainText(deep[4])
      await expect(list(page)).toBeHidden()
      await expect(back(page)).toHaveAttribute('href', /\/breadcrumbs-static\/new$/)

      await back(page).click()
      await expect(page).toHaveURL(/\/breadcrumbs-static\/new/)
    })

    test('the navigate-back event goes to the parent, as for the component\'s trail', async ({ page }) => {
      await open(page, 'two', { query })
      await trailBuilt(page, 2)

      await page.evaluate(() => window.dispatchEvent(new Event('navigate-back')))
      await expect(page).toHaveURL(/\/breadcrumbs-static\/one/)
    })

    test('the trail is in Alpine\'s own first read, before any navigation event builds it', async ({ page }) => {
      await page.addInitScript(() => {
        document.addEventListener('alpine:initialized', () => {
          const el = document.querySelector('[data-atom-breadcrumbs]')
          window.__atFirstRead = el ? { length: window.Alpine.$data(el).breadcrumbs.length, built: window.Alpine.$data(el).built } : null
        })
      })
      await open(page, 'two', { query })

      expect(await page.evaluate(() => window.__atFirstRead)).toEqual({ length: 2, built: false })
    })

    test('one item above a page.header draws no bar, from the first frame', async ({ page }) => {
      await page.addInitScript((selector) => {
        window.__bar = []
        const sample = () => {
          const el = document.querySelector(selector)
          if (el) window.__bar.push(Math.round(el.getBoundingClientRect().height))
          if (window.__bar.length < 90) requestAnimationFrame(sample)
        }
        requestAnimationFrame(sample)
      }, shell === 'header' ? '[data-atom-header-crumbs]' : '[data-atom-header]')

      await open(page, 'new', { query })
      await expect.poll(() => page.evaluate(() => window.__bar.length)).toBeGreaterThan(30)

      const frames = await page.evaluate(() => window.__bar)
      expect(frames.every((height) => height === 0), `every frame from the first: ${frames.slice(0, 12)}`).toBe(true)

      await expect(header(page)).toHaveText('Reports')
      await trailBuilt(page, 1)
      await expect(bar(page)).toHaveAttribute('data-atom-header-empty', '')
      // the one crumb is not printed again as a heading
      await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveCount(0)
    })

    test('one item and no page.header: the trail prints it as the heading', async ({ page }) => {
      await open(page, 'one', { query })
      await trailBuilt(page, 1)

      await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveText('Reports')
      await expect(bar(page)).not.toHaveAttribute('data-atom-header-empty', '')
      expect(await barHeight(page)).toBeGreaterThan(20)
    })

    test('wire:navigate to a page with other items: the trail is the page\'s own, never the one before it', async ({ page }) => {
      await open(page, 'deep', { width: 1440, query })
      await trailBuilt(page, 6)

      await page.locator('[data-go="other"]').click()
      await expect(page).toHaveURL(/\/breadcrumbs-static\/other/)
      await trailBuilt(page, 3)
      expect(await shownTitles(page)).toEqual(['Settings', 'Billing', 'Cards'])
      expect(await hiddenCount(page)).toBe(0)

      // one crumb above a page.header takes the bar away, two crumbs bring it back
      await page.locator('[data-go="new"]').click()
      await expect(page).toHaveURL(/\/breadcrumbs-static\/new/)
      await expect(header(page)).toHaveText('Reports')
      await expect.poll(() => barHeight(page)).toBe(0)

      await page.locator('[data-go="two"]').click()
      await expect(page).toHaveURL(/\/breadcrumbs-static\/two/)
      await trailBuilt(page, 2)
      await expect(bar(page)).not.toHaveAttribute('data-atom-header-empty', '')
      expect(await shownTitles(page)).toEqual(['Reports', 'Monthly'])
      expect(await barHeight(page)).toBeGreaterThan(20)
    })

    test('a javascript: href is dropped: a crumb with no link, in the list, the menu and the back link', async ({ page }) => {
      await open(page, 'hostile', { width: 1100, query })
      await trailBuilt(page, 6)

      expect(await page.locator('[data-atom-breadcrumbs]').evaluate((el) => el.outerHTML.toLowerCase().includes('javascript'))).toBe(false)
      await more(page).click()
      // listed, so the menu is the whole trail, but as text: not a link, and not a stop for the arrow keys
      const row = menu(page).locator('[data-atom-menu-text]', { hasText: 'Hostile Crumb' })
      await expect(row).toBeVisible()
      await expect(row).toHaveJSProperty('tagName', 'SPAN')
      await expect(menu(page).locator('[data-atom-menu-item]', { hasText: 'Hostile Crumb' })).toHaveCount(0)
      await row.click({ force: true })
      expect(await page.evaluate(() => window.__pwned)).toBeUndefined()
      await expect(page).toHaveURL(/\/breadcrumbs-static\/hostile/)

      // the parent is hostile: the phone's way up is the nearest crumb above it that has a safe address, never the hostile one
      await open(page, 'hostile-parent', { width: 390, query })
      await trailBuilt(page, 3)
      await expect(back(page)).toContainText('Dashboard')
      await expect(back(page)).toHaveAttribute('href', /\/breadcrumbs-static\/one$/)
      await page.evaluate(() => window.dispatchEvent(new Event('navigate-back')))
      await expect(page).toHaveURL(/\/breadcrumbs-static\/one/)
      expect(await page.evaluate(() => window.__pwned)).toBeUndefined()
    })

    test('a crumb with no address is text: no link, no wire:navigate, and the back link skips it', async ({ page }) => {
      await open(page, 'nolink', { query })
      await trailBuilt(page, 3)

      const section = list(page).locator('li[data-atom-crumb="crumb"]', { hasText: 'Section' })
      await expect(section.locator('a')).toHaveCount(0)
      await expect(section.locator('span', { hasText: 'Section' })).toBeVisible()
      await expect(list(page).locator('li[data-atom-crumb="crumb"]', { hasText: 'Dashboard' }).locator('a')).toHaveAttribute('wire:navigate', '')
      expect(await section.evaluate((el) => el.querySelectorAll('[wire\\:navigate]').length)).toBe(0)

      // a phone: the parent has no address, so the back link is the nearest crumb that has one
      await page.setViewportSize({ width: 390, height: 800 })
      await expect(back(page)).toContainText('Dashboard')
      await expect(back(page)).toHaveAttribute('href', /\/breadcrumbs-static\/one$/)
      await page.evaluate(() => window.dispatchEvent(new Event('navigate-back')))
      await expect(page).toHaveURL(/\/breadcrumbs-static\/one/)
    })

    test('a trail with no address anywhere has no back link on a phone, and navigate-back does nothing', async ({ page }) => {
      await open(page, 'nolink-all', { width: 390, query })
      await trailBuilt(page, 2)

      await expect(back(page)).toHaveCount(0)
      await page.evaluate(() => window.dispatchEvent(new Event('navigate-back')))
      await expect(page).toHaveURL(/\/breadcrumbs-static\/nolink-all/)
    })

    test('a phone with nothing to go back to draws no bar for the trail, and a wider window brings it back', async ({ page }) => {
      await open(page, 'nolink-all', { width: 390, query })
      await trailBuilt(page, 2)

      await expect(back(page)).toHaveCount(0)
      // the top-nav shell's crumbs row (the sidebar's bar also holds the menu button below lg, so it stays)
      if (shell === 'header') {
        await expect(bar(page)).toHaveAttribute('data-atom-header-empty', '')
        await expect.poll(() => barHeight(page)).toBe(0)
      }

      await page.setViewportSize({ width: 1440, height: 800 })
      await expect(bar(page)).not.toHaveAttribute('data-atom-header-empty', '')
      await expect(list(page)).toBeVisible()

      await page.setViewportSize({ width: 390, height: 800 })
      if (shell === 'header') await expect(bar(page)).toHaveAttribute('data-atom-header-empty', '')

      // and a trail that has a back link keeps its row on a phone
      await open(page, 'nolink', { width: 390, query })
      await trailBuilt(page, 3)
      await expect(back(page)).toBeVisible()
      await expect(bar(page)).not.toHaveAttribute('data-atom-header-empty', '')
    })

    test('every hidden crumb has no address: the "..." menu lists them as text, and opening and closing it throws nothing', async ({ page }) => {
      await open(page, 'unlinked-mid', { width: 1100, query })
      await trailBuilt(page, 6)
      await expect.poll(() => hiddenCount(page)).toBeGreaterThan(0)

      await more(page).click()
      await expect(menu(page)).toBeVisible()
      expect(await menu(page).locator('[data-atom-menu-item]').count()).toBe(0)
      expect(await menu(page).locator('[data-atom-menu-text]').count()).toBe(await hiddenCount(page))
      await page.keyboard.press('ArrowDown')
      await page.keyboard.press('Escape')
      await expect(menu(page)).toBeHidden()
    })

    // The server marks the bar empty from the first frame only when the slot holds nothing but the trail. With a sibling it cannot, so the
    // browser has to get it right alone: the one-crumb heading repeats the page.header's title, and must not paint for a frame.
    test('a slot with a sibling: the one-crumb heading never appears above a page.header, not even for a frame', async ({ page }) => {
      await page.addInitScript(() => {
        window.__headingSeen = false
        new MutationObserver(() => {
          if (document.querySelector('[data-atom-breadcrumbs] [data-atom-heading]')) window.__headingSeen = true
        }).observe(document, { childList: true, subtree: true })
      })
      await open(page, 'new', { query: (query ? query + '&' : '?') + 'extra=1' })
      await trailBuilt(page, 1)

      await expect(page.locator('[data-testid="crumbs-extra"]')).toBeVisible()
      await expect(header(page)).toHaveText('Reports')
      expect(await page.evaluate(() => window.__headingSeen)).toBe(false)
    })
  })
}

test('a fixed trail inside @persist warns in the console: it would keep the first page\'s crumbs', async ({ page }) => {
  const warnings = []
  page.on('console', (message) => { if (message.type() === 'warning') warnings.push(message.text()) })

  await open(page, 'two', { query: '?persist=1' })
  await trailBuilt(page, 2)

  expect(warnings.some((text) => text.includes('inside @persist'))).toBe(true)

  // and an unpersisted one does not
  warnings.length = 0
  await open(page, 'two')
  await trailBuilt(page, 2)
  expect(warnings.some((text) => text.includes('inside @persist'))).toBe(false)
})

test.describe('a fixed trail whose items change on a Livewire morph', () => {
  const stage = (page, name) => page.locator(`[data-stage="${name}"]`).click()

  test.beforeEach(({ page }) => {
    page.on('pageerror', (error) => { throw error })
  })

  test('the tag\'s items win over the component\'s own breadcrumbs() trail', async ({ page }) => {
    await open(page, 'morph', { width: 1100 })
    await trailBuilt(page, 2)

    expect(await shownTitles(page)).toEqual(['Reports', 'Monthly'])
    await expect(page.locator('body')).not.toContainText('From the component')
    // and it is what a morph does not bring back either
    await stage(page, 'long')
    await expect.poll(() => root(page).evaluate((el) => window.Alpine.$data(el).breadcrumbs.length)).toBe(6)
    await expect(page.locator('body')).not.toContainText('From the component')
  })

  test('the new items are drawn, collapse again, and a single item prints the heading', async ({ page }) => {
    await open(page, 'morph', { width: 1100 })
    await trailBuilt(page, 2)
    expect(await shownTitles(page)).toEqual(['Reports', 'Monthly'])

    // the same element and the same factory: only the items attribute changed
    // (a JS property: a morph strips an attribute the server did not render, and replaces a node whose key changed)
    await root(page).evaluate((el) => { el.__sameElement = true })

    await stage(page, 'long')
    await expect.poll(() => root(page).evaluate((el) => window.Alpine.$data(el).breadcrumbs.length)).toBe(6)
    await expect.poll(() => hiddenCount(page)).toBeGreaterThan(0)
    await expect(more(page)).toBeVisible()
    expect((await shownTitles(page))[0]).toBe(deep[0])
    expect((await shownTitles(page)).slice(-1)).toEqual(deep.slice(-1))
    expect(await root(page).evaluate((el) => el.__sameElement)).toBe(true)

    await stage(page, 'single')
    await expect.poll(() => root(page).evaluate((el) => window.Alpine.$data(el).breadcrumbs.length)).toBe(1)
    await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveText('Only page')
    await expect(list(page)).toHaveCount(0)

    await stage(page, 'none')
    await expect.poll(() => root(page).evaluate((el) => window.Alpine.$data(el).breadcrumbs.length)).toBe(0)
    await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveCount(0)
    await expect(list(page)).toHaveCount(0)

    await stage(page, 'short')
    await expect.poll(() => shownTitles(page)).toEqual(['Reports', 'Monthly'])
    await expect(more(page)).toHaveCount(0)
  })
})
