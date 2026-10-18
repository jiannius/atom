import { test, expect, devices } from '@playwright/test'

// The two app shells (layouts.sidebar and layouts.header) on the consumer Tailwind build
// (`node tests/consumer/build.mjs` first), once at the hosts' minimum Tailwind and once at the newest 4.x. Both pages
// are the pattern pages under resources/views/docs/patterns, which include ONE nav partial, so what is measured is the
// same nav slot drawn by two shells. Sizes and structure only: no text widths, so a Linux font fallback (DejaVu)
// changes nothing here.

const { defaultBrowserType, ...ipad } = devices['iPad (gen 7)']

async function open(page, shell, { tw = 'latest', query = '' } = {}) {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/shell-${shell}?tw=${tw}${query}`)
  await page.evaluate(() => document.fonts.ready)
  // Alpine has started when the sidebar's off-canvas class is applied, or the header's menu state is readable
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { x: r.x, y: r.y, width: r.width, height: r.height }
})

const link = (page, name) => page.locator('[data-atom-nav-shell] a[data-atom-navlist-item]', { hasText: name })

for (const tw of ['floor', 'latest']) {
  test.describe(`sidebar shell, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1280, height: 800 } })

    test('sizes: 228px side surface, 28px rows, 16px icons, 48px top bar, unpadded main', async ({ page }) => {
      await open(page, 'sidebar', { tw })

      const side = await box(page.locator('[data-atom-sidebar]'))
      expect(side.width).toBe(228)

      expect((await box(link(page, 'Customers'))).height).toBe(28)
      expect((await box(link(page, 'Customers').locator('svg').first())).height).toBe(16)

      expect((await box(page.locator('[data-atom-header]'))).height).toBe(48)

      const main = await page.locator('[data-atom-main]').evaluate((el) => {
        const s = getComputedStyle(el)
        return [s.paddingTop, s.paddingRight, s.paddingBottom, s.paddingLeft]
      })
      expect(main).toEqual(['0px', '0px', '0px', '0px'])

      expect(await page.evaluate(() => document.body.dataset.atomDensity)).toBe('compact')
    })

    test('the group label is 12px with no uppercase', async ({ page }) => {
      await open(page, 'sidebar', { tw })

      const label = await page.locator('[data-atom-navlist-group-label]').first().evaluate((el) => {
        const s = getComputedStyle(el)
        return { size: s.fontSize, transform: s.textTransform }
      })
      expect(label).toEqual({ size: '12px', transform: 'none' })
    })

    test('the top bar stays in view while the page scrolls', async ({ page }) => {
      await open(page, 'sidebar', { tw })
      await page.locator('[data-atom-main]').evaluate((el) => el.insertAdjacentHTML('beforeend', '<div style="height:3000px"></div>'))
      await page.mouse.move(700, 400)
      await page.mouse.wheel(0, 1500)
      await page.waitForFunction(() => window.scrollY > 500)

      expect((await box(page.locator('[data-atom-header]'))).y).toBe(0)
    })

    test('the current item is marked, and a click moves the mark', async ({ page }) => {
      await open(page, 'sidebar', { tw })

      await expect(page.locator('[data-atom-nav-shell] [aria-current="page"]')).toHaveText(/Invoices/)

      await link(page, 'Customers').click()
      await page.waitForURL(/page=customers/)

      await expect(page.locator('[data-atom-nav-shell] [aria-current="page"]')).toHaveText(/Customers/)
      await expect(page.getByTestId('pattern-title')).toHaveText('Customers')
    })

    test('a group is a label over its items, and the trigger is not shown', async ({ page }) => {
      await open(page, 'sidebar', { tw })

      await expect(page.locator('[data-atom-navlist-group-label]', { hasText: 'Reports' })).toBeVisible()
      await expect(page.locator('[data-atom-navlist-group-trigger]').first()).toBeHidden()
      await expect(link(page, 'Sales')).toBeVisible()
      // the expandable group keeps its disclosure and hides the header's label
      await expect(page.locator('[data-atom-navlist-group-toggle]')).toBeVisible()
      await expect(page.locator('[data-atom-navlist-group-label][data-for-header]')).toBeHidden()
    })
  })

  test.describe(`header shell at 1280px, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1280, height: 800 } })

    test('sizes: a 52px sticky bar over a centred 1248px column with 24px gutters, unpadded main', async ({ page }) => {
      await open(page, 'header', { tw })

      const bar = await box(page.locator('[data-atom-header-bar]'))
      expect(bar.height).toBe(52)
      expect(bar.width).toBe(1280)

      // the bar's content is the 1248px column: 16px to each side of a 1280px viewport, plus the 24px gutter
      const brand = await box(page.locator('[data-atom-header-bar] a').first())
      expect(brand.x).toBe(16 + 24)

      const main = await page.locator('[data-atom-main]').evaluate((el) => {
        const r = el.getBoundingClientRect()
        const s = getComputedStyle(el)
        return { x: r.x, width: r.width, padding: [s.paddingTop, s.paddingRight, s.paddingBottom, s.paddingLeft] }
      })
      expect(main).toEqual({ x: 16, width: 1248, padding: ['0px', '0px', '0px', '0px'] })

      expect(await page.evaluate(() => document.body.dataset.atomDensity)).toBe('compact')
    })

    test('the nav is a row of bar items that fill the bar, and the menu button is not shown', async ({ page }) => {
      await open(page, 'header', { tw })

      await expect(page.locator('[data-atom-header-menu-button]')).toBeHidden()

      const dashboard = await box(link(page, 'Dashboard'))
      const customers = await box(link(page, 'Customers'))
      expect(customers.y).toBe(dashboard.y)
      expect(customers.x).toBeGreaterThan(dashboard.x)
      expect(customers.height).toBe(51)

      // an icon beside a label is left out of the bar
      await expect(link(page, 'Customers').locator('[data-atom-navlist-icon]')).toBeHidden()
    })

    test('the current item is underlined by a 2px rule, and a click moves the mark', async ({ page }) => {
      await open(page, 'header', { tw })

      const rule = (name) => link(page, name).evaluate((el) => {
        const s = getComputedStyle(el, '::after')
        return s.content === 'none' ? null : { height: s.height, position: s.position }
      })

      await expect(page.locator('[data-atom-nav-shell] [aria-current="page"]')).toHaveText(/Invoices/)
      expect(await rule('Invoices')).toEqual({ height: '2px', position: 'absolute' })
      expect(await rule('Customers')).toBeNull()

      await link(page, 'Customers').click()
      await page.waitForURL(/page=customers/)

      await expect(page.locator('[data-atom-nav-shell] [aria-current="page"]')).toHaveText(/Customers/)
      expect(await rule('Customers')).toEqual({ height: '2px', position: 'absolute' })
    })

    test('a navlist.group is a dropdown: closed at rest, opened by its trigger, closed by Escape, an outside click and Tab', async ({ page }) => {
      await open(page, 'header', { tw })

      const group = page.locator('[data-atom-navlist-group]', { has: page.locator('[data-atom-navlist-group-trigger]', { hasText: 'Reports' }) })
      const trigger = group.locator('[data-atom-navlist-group-trigger]')
      const sales = link(page, 'Sales')

      await expect(group.locator('[data-atom-navlist-group-label]')).toBeHidden()
      await expect(trigger).toBeVisible()
      await expect(trigger).toHaveAttribute('aria-expanded', 'false')
      await expect(sales).toBeHidden()

      await trigger.click()
      await expect(trigger).toHaveAttribute('aria-expanded', 'true')
      await expect(sales).toBeVisible()

      // the panel hangs under the bar's item, as a raised layer
      const t = await box(trigger)
      const p = await box(group.locator('[data-atom-navlist-group-items]'))
      expect(p.y).toBeGreaterThanOrEqual(t.y + t.height)
      expect(p.x).toBe(t.x)

      await page.keyboard.press('Escape')
      await expect(sales).toBeHidden()
      await expect(trigger).toBeFocused()

      await trigger.click()
      await expect(sales).toBeVisible()
      await page.mouse.click(640, 600)
      await expect(sales).toBeHidden()

      await trigger.click()
      await expect(sales).toBeVisible()
      await sales.focus()
      await page.keyboard.press('Tab')
      await page.keyboard.press('Tab')
      await expect(sales).toBeHidden()
    })

    test('Arrow Down on the trigger opens the dropdown and lands on its first item', async ({ page }) => {
      await open(page, 'header', { tw })

      const trigger = page.locator('[data-atom-navlist-group-trigger]', { hasText: 'Reports' })
      await trigger.focus()
      await page.keyboard.press('ArrowDown')

      await expect(link(page, 'Sales')).toBeFocused()
    })

    test('a link inside the dropdown works and is marked current', async ({ page }) => {
      await open(page, 'header', { tw })

      await page.locator('[data-atom-navlist-group-trigger]', { hasText: 'Reports' }).click()
      await link(page, 'Expenses').click()
      await page.waitForURL(/page=expenses/)

      await expect(page.locator('[data-atom-nav-shell] [aria-current="page"]')).toHaveText(/Expenses/)
      // its group's trigger carries the current rule too
      const rule = await page.locator('[data-atom-navlist-group-trigger]', { hasText: 'Reports' }).evaluate((el) => getComputedStyle(el, '::after').height)
      expect(rule).toBe('2px')
    })

    test('the bar stays in view while the page scrolls', async ({ page }) => {
      await open(page, 'header', { tw })
      await page.locator('[data-atom-main]').evaluate((el) => el.insertAdjacentHTML('beforeend', '<div style="height:3000px"></div>'))
      await page.mouse.move(700, 400)
      await page.mouse.wheel(0, 1500)
      await page.waitForFunction(() => window.scrollY > 500)

      expect((await box(page.locator('[data-atom-header-bar]'))).y).toBe(0)
    })
  })

  test.describe(`header shell at 390px, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 390, height: 800 } })

    test('the nav moves into a menu that opens by keyboard and closes with Escape', async ({ page }) => {
      await open(page, 'header', { tw })

      const button = page.locator('[data-atom-header-menu-button]')
      const dashboard = link(page, 'Dashboard')

      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390)
      await expect(button).toBeVisible()
      await expect(button).toHaveAttribute('aria-expanded', 'false')
      await expect(dashboard).toBeHidden()

      await button.focus()
      await page.keyboard.press('Enter')
      await expect(button).toHaveAttribute('aria-expanded', 'true')
      await expect(dashboard).toBeVisible()

      // the panel is the nav slot, drawn as rows: full width under the bar, 28px rows, groups open under their label
      const panel = await box(page.locator('[data-atom-header-nav]'))
      expect(panel.x).toBe(0)
      expect(panel.width).toBe(390)
      expect(panel.y).toBe(52)
      expect((await box(dashboard)).height).toBe(28)
      await expect(page.locator('[data-atom-navlist-group-label]', { hasText: 'Reports' })).toBeVisible()
      // an expandable group shows a label, not its sidebar disclosure, and its items are open
      await expect(page.locator('[data-atom-navlist-group-label]', { hasText: 'Settings' })).toBeVisible()
      await expect(page.locator('[data-atom-navlist-group-toggle]')).toBeHidden()
      await expect(link(page, 'Team')).toBeVisible()
      await expect(page.locator('[data-atom-navlist-group-trigger]').first()).toBeHidden()
      await expect(link(page, 'Sales')).toBeVisible()

      // Tab reaches the links in the panel (the brand link sits between the button and the panel)
      for (let i = 0; i < 4 && !(await dashboard.evaluate((el) => el === document.activeElement)); i++) {
        await page.keyboard.press('Tab')
      }
      await expect(dashboard).toBeFocused()

      await page.keyboard.press('Escape')
      await expect(dashboard).toBeHidden()
      await expect(button).toBeFocused()
      await expect(button).toHaveAttribute('aria-expanded', 'false')
    })

    test('Space opens it too, and a click outside closes it', async ({ page }) => {
      await open(page, 'header', { tw })

      const button = page.locator('[data-atom-header-menu-button]')
      await button.focus()
      await page.keyboard.press('Space')
      await expect(link(page, 'Dashboard')).toBeVisible()

      await page.mouse.click(200, 700)
      await expect(link(page, 'Dashboard')).toBeHidden()
    })

    test('a link in the menu works', async ({ page }) => {
      await open(page, 'header', { tw })

      await page.locator('[data-atom-header-menu-button]').click()
      await link(page, 'Customers').click()
      await page.waitForURL(/page=customers/)

      await page.locator('[data-atom-header-menu-button]').click()
      await expect(page.locator('[data-atom-nav-shell] [aria-current="page"]')).toHaveText(/Customers/)
    })
  })

  test.describe(`sidebar shell at 390px, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 390, height: 800 } })

    test('the sidebar is off canvas, and the top bar button brings it in', async ({ page }) => {
      await open(page, 'sidebar', { tw })

      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390)
      expect((await box(page.locator('[data-atom-sidebar]'))).x).toBeLessThan(0)

      await page.locator('[data-atom-sidebar-toggle]').click()
      await expect.poll(async () => (await box(page.locator('[data-atom-sidebar]'))).x).toBe(0)
      await expect(link(page, 'Customers')).toBeVisible()
    })
  })
}

// What takes room in the bar, left to right: the logo, the context switcher, each bar item, group trigger and "More" that
// is shown, the search field when it is inline (not the popover) and the actions. Each box must start where the one before
// it ends or later. The items can spill out of a box that is too narrow for them, which is how "Settings" once drew over the
// search field at 1024px, so items are measured, not just the nav's own box.
async function barBoxes (page) {
  return page.evaluate(() => {
    const rect = (name, el) => { const r = el.getBoundingClientRect(); return { name, x: r.x, right: r.right, width: r.width } }
    const shown = (el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 }
    const bar = document.querySelector('[data-atom-header-bar]')
    const boxes = [rect('logo', bar.querySelector('a'))]

    const context = bar.querySelector('[data-atom-shell-context]')
    if (context && shown(context)) boxes.push(rect('context', context))

    const items = bar.querySelectorAll('[data-atom-header-nav] a[data-atom-navlist-item], [data-atom-header-nav] [data-atom-navlist-group-trigger]')
    items.forEach((el) => {
      if (el.closest('[data-atom-navlist-group-items]') || !shown(el)) return
      boxes.push(rect((el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 24), el))
    })

    const search = bar.querySelector('[data-atom-shell-search]')
    if (search && shown(search) && getComputedStyle(search).position !== 'absolute') boxes.push(rect('search', search))

    boxes.push(rect('actions', bar.querySelector('[data-atom-shell-actions]')))

    return boxes.sort((a, b) => a.x - b.x)
  })
}

function expectNoOverlap (boxes, width) {
  for (let i = 1; i < boxes.length; i++) {
    expect(boxes[i].x, `${boxes[i].name} starts after ${boxes[i - 1].name} ends`).toBeGreaterThanOrEqual(boxes[i - 1].right - 0.5)
  }
  expect(boxes[boxes.length - 1].right, 'the bar stays inside the viewport').toBeLessThanOrEqual(width)
}

// Every leaf link is reachable: shown in the bar, in a group dropdown whose trigger is in the bar, or in "More".
async function reachability (page, names) {
  return page.evaluate((names) => names.map((name) => {
    const shown = (el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 }
    const originals = [...document.querySelectorAll('[data-atom-header-nav] a[data-atom-navlist-item]:not([data-atom-more-copy])')].filter((a) => a.textContent.includes(name))
    const copies = [...document.querySelectorAll('[data-atom-more-copy]')].filter((a) => a.textContent.includes(name))

    return {
      name,
      inBar: originals.some((a) => !a.closest('[data-atom-navlist-group-items]') && shown(a)),
      inGroup: originals.some((a) => { const g = a.closest('[data-atom-navlist-group]'); return !!g && shown(g) }),
      inMore: copies.length === 1,
    }
  }), names)
}

const underlined = (page) => page.evaluate(() => [...document.querySelectorAll('[data-atom-header-nav] a[data-atom-navlist-item]:not([data-atom-more-copy]), [data-atom-header-nav] [data-atom-navlist-group-trigger]')]
  .filter((el) => el.getBoundingClientRect().width > 0 && !el.closest('[data-atom-navlist-group-items]'))
  .filter((el) => { const s = getComputedStyle(el, '::after'); return s.content !== 'none' && s.height === '2px' })
  .map((el) => (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 24)))

// The bar/menu switch is at 1024px (lg): the logo, the context switcher, six items, an action and the account do not fit
// side by side before that. At every width either the menu button is shown, or nothing in the bar overlaps. From lg
// the nav is priority-plus: what does not fit moves into "More", so the bar never overflows and no item is lost.
for (const tw of ['floor', 'latest']) {
  test.describe(`header shell widths, ${tw} consumer build`, () => {
    for (const width of [768, 1024, 1100, 1280]) {
      test(`nothing in the bar overlaps at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 800 })
        await open(page, 'header', { tw })

        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width)

        const menuMode = await page.locator('[data-atom-header-menu-button]').isVisible()
        // 1024px and up is the bar; below it the menu
        expect(menuMode).toBe(width < 1024)
        if (menuMode) {
          await expect(link(page, 'Dashboard')).toBeHidden()
          await expect(page.locator('[data-atom-header-more]')).toBeHidden()
          return
        }

        const actions = (await box(page.locator('[data-atom-shell-actions]'))).x
        const nav = await box(page.locator('[data-atom-header-nav]'))
        expect(nav.x + nav.width, 'the nav box').toBeLessThanOrEqual(actions)

        expectNoOverlap(await barBoxes(page), width)

        // the context switcher ends before the first item, the logo before the switcher
        const context = await box(page.locator('[data-atom-shell-context]'))
        const brand = await box(page.locator('[data-atom-header-bar] a').first())
        expect(brand.x + brand.width).toBeLessThanOrEqual(context.x)

        // the short labels all fit in the bar at 1280px: no "More"
        if (width === 1280) await expect(page.locator('[data-atom-header-more]')).toBeHidden()

        const reach = await reachability(page, ['Dashboard', 'Invoices', 'Customers', 'Sales', 'Expenses', 'Team', 'Billing', 'Help'])
        for (const r of reach) expect(r.inBar || r.inGroup || r.inMore, `${r.name} is reachable`).toBe(true)

        // the search field is a wide-screen extra: inline from 1280px; below it a button opens it
        if (width < 1280) {
          await expect(page.locator('[data-atom-shell-search]')).toBeHidden()
          await expect(page.locator('[data-atom-shell-search-button]')).toBeVisible()
        }
        else {
          await expect(page.locator('[data-atom-shell-search]')).toBeVisible()
          await expect(page.locator('[data-atom-shell-search-button]')).toBeHidden()
          const search = await box(page.locator('[data-atom-shell-search]'))
          expect(search.x + search.width).toBeLessThanOrEqual(actions)
        }
      })
    }

    test('the context switcher is in the menu panel on a small screen, ahead of the items', async ({ page }) => {
      await page.setViewportSize({ width: 768, height: 800 })
      await open(page, 'header', { tw })

      const context = page.locator('[data-atom-shell-context]')
      await expect(context).toBeHidden()

      await page.locator('[data-atom-header-menu-button]').click()
      await expect(context).toBeVisible()
      await expect(context.getByRole('button', { name: /Acme Studio/ })).toBeVisible()
      // it is inside the panel, above the first item, and printed once
      expect(await page.locator('[data-atom-header-nav] [data-atom-shell-context]').count()).toBe(1)
      expect(await page.locator('[data-atom-shell-context]').count()).toBe(1)
      expect((await box(context)).y + (await box(context)).height).toBeLessThanOrEqual((await box(link(page, 'Dashboard'))).y)
      await expect(page.locator('[data-atom-shell-context-divider]')).toBeHidden()

      // and in the bar, beside the logo, on a wide one
      await page.setViewportSize({ width: 1280, height: 800 })
      await expect(context).toBeVisible()
      expect((await box(context)).y).toBeLessThan(52)
      await expect(page.locator('[data-atom-shell-context-divider]')).toBeVisible()
    })
  })

  // The worst case: long labels, a count on every item, two groups, a context switcher (a dropdown), a search field and a
  // long action. At 1024px nothing near all of it fits; at 1600px the 1248px column still holds only a few.
  test.describe(`header shell with long labels, ${tw} consumer build`, () => {
    const leaves = ['Purchase orders', 'Work orders', 'Sales invoices', 'Delivery orders', 'Stock transfers', 'Trial balance', 'Aged debtors', 'Users and roles', 'Company settings']

    for (const width of [1024, 1100, 1280, 1600]) {
      test(`nothing overlaps and every item is reachable at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 800 })
        await open(page, 'header-wide', { tw })

        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width)
        expectNoOverlap(await barBoxes(page), width)

        // the bar cannot hold nine long items, so "More" is shown, and it is the last thing in the nav
        await expect(page.locator('[data-atom-header-more]')).toBeVisible()
        const nav = await box(page.locator('[data-atom-header-nav]'))
        const actions = await box(page.locator('[data-atom-shell-actions]'))
        expect(nav.x + nav.width).toBeLessThanOrEqual(actions.x)

        const reach = await reachability(page, leaves)
        for (const r of reach) expect(r.inBar || r.inGroup || r.inMore, `${r.name} is reachable`).toBe(true)
        // an item is in the bar or in "More", never both
        for (const r of reach.filter((r) => r.inBar)) expect(r.inMore, `${r.name} is not listed twice`).toBe(false)

        // "More" lists, in order, exactly the moved units, and the panel is on screen
        const trigger = page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]')
        await trigger.click()
        await expect(trigger).toHaveAttribute('aria-expanded', 'true')
        const panel = await box(page.locator('[data-atom-header-more] > [data-atom-navlist-group-items]'))
        expect(panel.x).toBeGreaterThanOrEqual(0)
        expect(panel.x + panel.width).toBeLessThanOrEqual(width)
        expect(panel.y).toBeGreaterThanOrEqual(52)

        const listed = await page.locator('[data-atom-more-copy]').evaluateAll((els) => els.map((el) => el.textContent.replace(/\s+/g, ' ').trim()))
        const inMore = reach.filter((r) => r.inMore).map((r) => r.name)
        expect(listed.length).toBe(inMore.length)
        const order = inMore.map((name) => leaves.indexOf(name))
        expect(order).toEqual([...order].sort((a, b) => a - b))
        for (const el of await page.locator('[data-atom-more-copy]').all()) await expect(el).toBeVisible()

        // every id on the page is unique: a copy carries none
        const ids = await page.evaluate(() => [...document.querySelectorAll('[id]')].map((el) => el.id))
        expect(new Set(ids).size).toBe(ids.length)
      })
    }

    test('a unit that moves into "More" gives "More" the current underline, and a group is a labelled section', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 800 })
      // a group's item is current: the whole group has moved into "More"
      await open(page, 'header-wide', { tw, query: '&page=aged-debtors' })

      expect(await underlined(page)).toEqual(['More'])

      await page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]').click()
      const section = page.locator('[data-atom-header-more-section]', { hasText: 'Financial reports' })
      await expect(section.locator('[data-atom-header-more-label]')).toHaveText('Financial reports')
      await expect(section.locator('[data-atom-more-copy]')).toHaveCount(2)
      await expect(section.locator('[aria-current="page"]')).toHaveText(/Aged debtors/)

      // a plain item that moved: the same
      await page.goto(`/atom/e2e/shell-header-wide?tw=${tw}&page=stock-transfers`)
      await page.waitForFunction(() => window.Alpine !== undefined)
      await expect.poll(() => underlined(page)).toEqual(['More'])

      // an item still in the bar keeps its own underline, and "More" has none
      await page.goto(`/atom/e2e/shell-header-wide?tw=${tw}&page=purchase-orders`)
      await page.waitForFunction(() => window.Alpine !== undefined)
      await expect.poll(() => underlined(page)).toEqual(['Purchase orders 12'])
    })

    test('the bar fits again when it grows: items come back from "More" in order', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 800 })
      await open(page, 'header-wide', { tw })

      const count = () => page.locator('[data-atom-more-copy]').count()
      const narrow = await count()
      expect(narrow).toBeGreaterThan(0)

      // wider, and below xl where the search field is still a button: more room, so an item comes back
      await page.setViewportSize({ width: 1200, height: 800 })
      await expect.poll(count).toBeLessThan(narrow)

      await page.setViewportSize({ width: 1024, height: 800 })
      await expect.poll(count).toBe(narrow)
    })

    test('a copy in "More" is a working link, and Arrow Down opens the panel on its first item', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 800 })
      await open(page, 'header-wide', { tw })

      const trigger = page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]')
      await trigger.focus()
      await page.keyboard.press('ArrowDown')
      await expect(page.locator('[data-atom-header-more] [data-atom-more-copy]').first()).toBeFocused()

      await page.keyboard.press('Escape')
      await expect(trigger).toBeFocused()
      await expect(trigger).toHaveAttribute('aria-expanded', 'false')

      await trigger.click()
      await page.locator('[data-atom-header-more] [data-atom-more-copy]', { hasText: 'Delivery orders' }).click()
      await page.waitForURL(/page=delivery-orders/)
    })
  })

  // The search slot is printed once and shown three ways: a row of the menu panel below lg, a popover a button opens from
  // lg to xl, inline from xl.
  test.describe(`header shell search, ${tw} consumer build`, () => {
    const field = (page) => page.locator('[data-atom-shell-search]')

    test('below lg it is a full-width row at the top of the menu panel, under the context switcher', async ({ page }) => {
      await page.setViewportSize({ width: 768, height: 800 })
      await open(page, 'header-wide', { tw })

      await expect(field(page)).toBeHidden()
      await expect(page.locator('[data-atom-shell-search-button]')).toBeHidden()

      await page.locator('[data-atom-header-menu-button]').click()
      await expect(field(page)).toBeVisible()

      const panel = await box(page.locator('[data-atom-header-nav]'))
      const row = await box(field(page))
      const input = await box(field(page).getByRole('textbox', { name: 'Search' }))
      const context = await box(page.locator('[data-atom-shell-context]'))
      const first = await box(link(page, 'Purchase orders').first())

      expect(row.x).toBeGreaterThanOrEqual(panel.x)
      expect(row.x + row.width).toBeLessThanOrEqual(panel.x + panel.width)
      // full width: the field fills the panel's padding box, not the host's w-44
      expect(input.width).toBeGreaterThan(panel.width - 40)
      expect(context.y + context.height).toBeLessThanOrEqual(row.y)
      expect(row.y + row.height).toBeLessThanOrEqual(first.y)
      expect(await page.locator('[data-atom-shell-search]').count()).toBe(1)
      expect(await page.getByRole('textbox', { name: 'Search' }).count()).toBe(1)
    })

    test('from lg to xl a search button opens it in a popover: Enter or Space opens, Escape closes and returns focus', async ({ page }) => {
      await page.setViewportSize({ width: 1100, height: 800 })
      await open(page, 'header-wide', { tw })

      const button = page.locator('[data-atom-shell-search-button]')
      await expect(button).toBeVisible()
      await expect(button).toHaveAccessibleName('Search')
      await expect(button).toHaveAttribute('aria-expanded', 'false')
      await expect(field(page)).toBeHidden()
      expect(await page.locator('[data-atom-shell-search]').count()).toBe(1)

      for (const key of ['Enter', 'Space']) {
        await button.focus()
        await page.keyboard.press(key)

        await expect(field(page)).toBeVisible()
        await expect(button).toHaveAttribute('aria-expanded', 'true')
        // focus goes to the first input in the slot
        await expect(field(page).getByRole('textbox', { name: 'Search' })).toBeFocused()

        // a layer under the bar, on screen, over the page rather than pushing it
        const popover = await box(field(page))
        expect(popover.y).toBeGreaterThanOrEqual(52)
        expect(popover.x).toBeGreaterThanOrEqual(0)
        expect(popover.x + popover.width).toBeLessThanOrEqual(1100)
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1100)

        await page.keyboard.press('Escape')
        await expect(field(page)).toBeHidden()
        await expect(button).toBeFocused()
        await expect(button).toHaveAttribute('aria-expanded', 'false')
      }

      // a click outside closes it too
      await button.click()
      await expect(field(page)).toBeVisible()
      await page.mouse.click(300, 500)
      await expect(field(page)).toBeHidden()
    })

    test('from xl it is inline in the bar and there is no button', async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 })
      await open(page, 'header-wide', { tw })

      await expect(field(page)).toBeVisible()
      await expect(page.locator('[data-atom-shell-search-button]')).toBeHidden()
      expect((await box(field(page))).y).toBeLessThan(52)
      expect(await page.locator('[data-atom-shell-search]').count()).toBe(1)
    })
  })

  // Escape closes the innermost thing that is open and nothing else.
  test.describe(`header shell Escape, ${tw} consumer build`, () => {
    test('a dropdown inside the menu panel closes on Escape without closing the panel', async ({ page }) => {
      await page.setViewportSize({ width: 768, height: 800 })
      await open(page, 'header-wide', { tw })

      const menuButton = page.locator('[data-atom-header-menu-button]')
      const panel = page.locator('[data-atom-header-nav]')
      await menuButton.click()
      await expect(panel).toBeVisible()

      // the context switcher is a dropdown
      await page.locator('[data-atom-shell-context]').getByRole('button', { name: /Acme Manufacturing/ }).click()
      const item = page.getByRole('menuitem', { name: 'Acme Trading' })
      await expect(item).toBeVisible()

      await page.keyboard.press('Escape')
      await expect(item).toBeHidden()
      await expect(panel).toBeVisible()
      await expect(menuButton).toHaveAttribute('aria-expanded', 'true')

      // the next one closes the panel
      await page.keyboard.press('Escape')
      await expect(panel).toBeHidden()
      await expect(menuButton).toBeFocused()
    })

    test('the account menu closes on Escape without closing an open menu panel', async ({ page }) => {
      await page.setViewportSize({ width: 768, height: 800 })
      await open(page, 'header-wide', { tw })

      const menuButton = page.locator('[data-atom-header-menu-button]')
      await menuButton.click()
      await page.getByRole('button', { name: 'Account' }).click()
      const item = page.getByRole('menuitem', { name: 'Sign out' })
      await expect(item).toBeVisible()

      await page.keyboard.press('Escape')
      await expect(item).toBeHidden()
      await expect(page.locator('[data-atom-header-nav]')).toBeVisible()
      await expect(menuButton).toHaveAttribute('aria-expanded', 'true')
    })

    test('the search popover and "More" each close on their own Escape and nothing else does', async ({ page }) => {
      await page.setViewportSize({ width: 1100, height: 800 })
      await open(page, 'header-wide', { tw })

      const searchButton = page.locator('[data-atom-shell-search-button]')
      const trigger = page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]')
      const items = page.locator('[data-atom-header-more] > [data-atom-navlist-group-items]')

      await searchButton.click()
      await expect(page.locator('[data-atom-shell-search]')).toBeVisible()
      await page.keyboard.press('Escape')
      await expect(page.locator('[data-atom-shell-search]')).toBeHidden()
      await expect(searchButton).toBeFocused()

      await trigger.click()
      await expect(items).toBeVisible()
      await page.keyboard.press('Escape')
      await expect(items).toBeHidden()
      await expect(trigger).toBeFocused()

    })
  })

  // The shells' bar is sticky, so what else sticks to the top of the page (an editor's toolbar) has to stick below it, and
  // scrolling to an anchor or a focused field must not land under it.
  test.describe(`sticky bar and sticky page elements, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1280, height: 800 } })

    for (const [shell, barHeight] of [['sidebar', 48], ['header', 52]]) {
      test(`${shell} shell: a tall editor's toolbar stays visible below the bar mid-scroll`, async ({ page }) => {
        await page.goto(`/atom/e2e/shell-editor?tw=${tw}&shell=${shell}`)
        await page.waitForFunction(() => window.Alpine !== undefined)
        const toolbar = page.getByRole('toolbar', { name: 'Formatting' })
        await expect(toolbar).toBeVisible({ timeout: 15000 })

        const html = await page.evaluate(() => {
          const s = getComputedStyle(document.documentElement)
          return { bar: s.getPropertyValue('--atom-shell-bar-h').trim(), padding: s.scrollPaddingTop }
        })
        expect(html.padding).toBe(`${barHeight}px`)

        await page.mouse.move(700, 500)
        await page.mouse.wheel(0, 700)
        await page.waitForFunction(() => window.scrollY > 600)

        const bar = await box(page.locator(shell === 'sidebar' ? '[data-atom-header]' : '[data-atom-header-bar]'))
        const tools = await box(toolbar)
        expect(bar.y).toBe(0)
        expect(bar.height).toBe(barHeight)
        // below the bar, not under it, and still on screen
        expect(tools.y).toBeGreaterThanOrEqual(barHeight)
        expect(tools.y + tools.height).toBeLessThanOrEqual(800)
      })
    }

    // A slide-over is a <dialog> in the top layer: it scrolls inside its own box and the shell's bar is not above it, so the
    // bar's height must not leak into it (--atom-shell-bar-h is reset on dialogs and popovers).
    for (const [shell, barHeight] of [['sidebar', 48], ['header', 52]]) {
      test(`${shell} shell: a tiptap in a slide-over keeps its toolbar at the top of the slide-over's scroll box`, async ({ page }) => {
        await page.goto(`/atom/e2e/shell-editor?tw=${tw}&shell=${shell}&slide=1`)
        await page.waitForFunction(() => window.Alpine !== undefined)
        await expect(page.getByRole('toolbar', { name: 'Formatting' }).first()).toBeVisible({ timeout: 15000 })

        // the page itself is under the bar, the dialog is not
        expect(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--atom-shell-bar-h').trim())).not.toBe('0px')

        await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-modal-show', { detail: { name: 'editor-slide', variant: 'slide' } })))
        const dialog = page.locator('dialog[data-atom-modal-slide]')
        await expect(dialog).toBeVisible()
        const toolbar = dialog.getByRole('toolbar', { name: 'Formatting' })
        await expect(toolbar).toBeVisible({ timeout: 15000 })

        expect(await dialog.evaluate((el) => getComputedStyle(el).getPropertyValue('--atom-shell-bar-h').trim())).toBe('0px')

        // scroll the slide-over's own box, from script: it is the scroll container
        const scrolled = await dialog.evaluate((el) => { el.scrollTop = 700; return el.scrollTop })
        expect(scrolled, 'the slide-over scrolls').toBeGreaterThan(600)

        // the sticky wrapper (the toolbar's parent) sits at the slide-over's own padding edge: a sticky box keeps that much
        // room inside the scroller. With the page's bar height leaking in it would sit barHeight lower.
        const padding = await dialog.evaluate((el) => parseFloat(getComputedStyle(el).paddingTop))
        const dialogBox = await box(dialog)
        const wrapper = await box(toolbar.locator('..'))
        const gap = wrapper.y - dialogBox.y - padding
        expect(gap, 'stuck at the slide-over padding edge, not pushed down').toBeGreaterThanOrEqual(0)
        expect(gap, `not ${barHeight}px lower`).toBeLessThan(1)
      })
    }

    test('with nothing to hold, the sidebar draws no top bar from lg up, and the page sticks to the top', async ({ page }) => {
      await page.goto(`/atom/e2e/shell-editor?tw=${tw}&bare=1`)
      await page.waitForFunction(() => window.Alpine !== undefined)
      const toolbar = page.getByRole('toolbar', { name: 'Formatting' })
      await expect(toolbar).toBeVisible({ timeout: 15000 })

      await expect(page.locator('[data-atom-header]')).toBeHidden()
      expect(await page.evaluate(() => getComputedStyle(document.documentElement).scrollPaddingTop)).toBe('0px')
      // the main column starts at the top of the page
      expect((await box(page.locator('[data-atom-main]'))).y).toBe(0)

      await page.mouse.move(700, 500)
      await page.mouse.wheel(0, 700)
      await page.waitForFunction(() => window.scrollY > 600)
      expect((await box(toolbar)).y).toBeLessThan(12)

      // below lg the bar is back: it holds the menu button
      await page.setViewportSize({ width: 390, height: 800 })
      await expect(page.locator('[data-atom-header]')).toBeVisible()
      await expect(page.locator('[data-atom-sidebar-toggle]')).toBeVisible()
    })
  })
}

// Dark mode: the page opts in (?dark=1) and the browser asks for it (colorScheme), as a host's visitor would. Computed
// colours and contrast, never pixels.
const contrast = (page, fg, bg) => page.evaluate(([fg, bg]) => {
  const cv = document.createElement('canvas').getContext('2d', { willReadFrequently: true })
  const rgb = (value) => {
    cv.clearRect(0, 0, 1, 1)
    cv.fillStyle = '#000'
    cv.fillStyle = value
    cv.fillRect(0, 0, 1, 1)
    return [...cv.getImageData(0, 0, 1, 1).data].slice(0, 3)
  }
  const lin = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4 }
  const lum = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)
  const [x, y] = [lum(rgb(fg)), lum(rgb(bg))]
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)
}, [fg, bg])

const style = (locator, prop) => locator.evaluate((el, prop) => getComputedStyle(el)[prop], prop)
const luminance = (page, color) => contrast(page, color, '#000')

test.describe('dark mode in the shells', () => {
  test.use({ viewport: { width: 1280, height: 800 }, colorScheme: 'dark' })

  test('the sidebar shell: dark surfaces, and its nav text reads on them', async ({ page }) => {
    await open(page, 'sidebar', { tw: 'latest', query: '&dark=1' })
    expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(true)

    const canvas = await style(page.locator('body'), 'backgroundColor')
    const side = await style(page.locator('[data-atom-sidebar]'), 'backgroundColor')
    // dark: close to black against black, far from white
    expect(await luminance(page, canvas)).toBeLessThan(3)
    expect(await luminance(page, side)).toBeLessThan(3)

    for (const name of ['Customers', 'Invoices']) {
      expect(await contrast(page, await style(link(page, name), 'color'), side), `${name} on the side surface`).toBeGreaterThanOrEqual(4.5)
    }
    const label = page.locator('[data-atom-navlist-group-label]', { hasText: 'Reports' })
    expect(await contrast(page, await style(label, 'color'), side), 'a group label').toBeGreaterThanOrEqual(4.5)
    const count = page.locator('[data-atom-navlist-count]').first()
    expect(await contrast(page, await style(count, 'color'), side), 'a count').toBeGreaterThanOrEqual(4.5)

    // the top bar's crumbs read on the page canvas
    const crumb = page.locator('[data-atom-shell-crumbs] .font-medium')
    expect(await contrast(page, await style(crumb, 'color'), canvas)).toBeGreaterThanOrEqual(4.5)
  })

  test('the header shell: dark bar, items, current item, count pills and dropdown panel read on it', async ({ page }) => {
    await open(page, 'header', { tw: 'latest', query: '&dark=1' })
    expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(true)

    const canvas = await style(page.locator('body'), 'backgroundColor')
    expect(await luminance(page, canvas)).toBeLessThan(3)

    // the bar is the surface colour, a touch see-through over the canvas: items are measured against the canvas it lets through
    for (const name of ['Customers', 'Invoices']) {
      // the original, not its "More" copy: the bar's own colour is what is measured
      const item = page.locator('[data-atom-nav-shell] a[data-atom-navlist-item]:not([data-atom-more-copy] *):not([data-atom-more-copy])', { hasText: name })
      expect(await contrast(page, await style(item, 'color'), canvas), `${name} on the bar`).toBeGreaterThanOrEqual(4.5)
    }

    const pill = page.locator('[data-atom-navlist-count]').first()
    expect(await contrast(page, await style(pill, 'color'), await style(pill, 'backgroundColor')), 'a count pill').toBeGreaterThanOrEqual(4.5)

    // Reports is in the bar or, with a wider system font (DejaVu on Linux CI), already moved into "More": open whichever shows
    const reports = page.locator('[data-atom-navlist-group]:not([data-atom-header-more])', { has: page.locator('[data-atom-navlist-group-trigger]', { hasText: 'Reports' }) }).first()
    const group = await reports.locator('[data-atom-navlist-group-trigger]').isVisible() ? reports : page.locator('[data-atom-header-more]')
    await group.locator('[data-atom-navlist-group-trigger]').click()
    const panel = group.locator('[data-atom-navlist-group-items]')
    await expect(panel).toBeVisible()
    const raised = await style(panel, 'backgroundColor')
    expect(await luminance(page, raised)).toBeLessThan(3)
    const item = panel.locator('a[data-atom-navlist-item]').filter({ visible: true }).first()
    expect(await contrast(page, await style(item, 'color'), raised), 'an item in the dropdown').toBeGreaterThanOrEqual(4.5)
  })

  test('the header shell menu panel on a small screen is dark too', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 })
    await open(page, 'header', { tw: 'latest', query: '&dark=1' })

    await page.locator('[data-atom-header-menu-button]').click()
    const panel = page.locator('[data-atom-header-nav]')
    const bg = await style(panel, 'backgroundColor')
    expect(await luminance(page, bg)).toBeLessThan(3)
    expect(await contrast(page, await style(link(page, 'Customers'), 'color'), bg)).toBeGreaterThanOrEqual(4.5)
    expect(await contrast(page, await style(page.locator('[data-atom-navlist-group-label]', { hasText: 'Reports' }), 'color'), bg)).toBeGreaterThanOrEqual(4.5)
    expect(await contrast(page, await style(page.locator('[data-atom-shell-context] button'), 'color'), bg)).toBeGreaterThanOrEqual(4.5)
  })
})

test.describe('one nav in both shells', () => {
  test.use({ viewport: { width: 1280, height: 800 } })

  test('the same items and counts, in the same order', async ({ page }) => {
    const items = async (shell) => {
      await open(page, shell)
      return page.locator('[data-atom-nav-shell] a[data-atom-navlist-item]').evaluateAll((els) => els.map((el) => ({
        href: el.getAttribute('href'),
        label: el.querySelector('[data-content]')?.textContent.trim(),
        count: el.querySelector('[data-atom-navlist-count]')?.textContent.trim() ?? null,
      })))
    }

    const sidebar = await items('sidebar')
    const header = await items('header')

    expect(sidebar.length).toBeGreaterThan(5)
    expect(header).toEqual(sidebar)
    expect(sidebar[0]).toEqual({ href: '?page=dashboard', label: 'Dashboard', count: '3' })
  })

  test('a count is a plain number in the sidebar and a pill in the bar', async ({ page }) => {
    await open(page, 'sidebar')
    const plain = await page.locator('[data-atom-navlist-count]').first().evaluate((el) => getComputedStyle(el).borderRadius)

    await open(page, 'header')
    const pill = await page.locator('[data-atom-navlist-count]').first().evaluate((el) => getComputedStyle(el).borderRadius)

    expect(plain).toBe('0px')
    expect(pill).toBe('999px')
  })
})

test.describe('touch', () => {
  test.use({ ...ipad })

  test('nav rows are 40px for a coarse pointer', async ({ page }) => {
    await open(page, 'sidebar')
    expect(await page.evaluate(() => matchMedia('(pointer: coarse)').matches)).toBe(true)
    expect((await box(link(page, 'Customers'))).height).toBe(40)
  })
})

// Round 3: the header's priority-plus and its open state, under the cases a review found. The wide page takes switches in its
// query string (resources/views/e2e/shell-header-wide.blade.php): hide=<page>,<page> hides units, lw=1 puts a Livewire counter
// in the actions slot, refs=1 gives one item references to ids.
for (const tw of ['floor', 'latest']) {
  test.describe(`header shell fit, ${tw} consumer build`, () => {
    const moreTrigger = (page) => page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]')
    const moreText = (page) => page.locator('#atom-header-more-items').evaluate((el) => el.textContent.replace(/\s+/g, ' '))

    // a unit that is not shown has a zero box: it once counted as the last edge (so nothing overflowed) and could be copied
    for (const [hide, labels] of [
      ['administration', ['Administration', 'Users and roles', 'Company settings']],
      ['stock-transfers,delivery-orders', ['Stock transfers', 'Delivery orders']],
      ['sales-invoices,administration', ['Sales invoices', 'Administration', 'Users and roles', 'Company settings']],
    ]) {
      test(`a hidden unit takes no room and is never copied into "More" (hidden: ${hide})`, async ({ page }) => {
        await page.setViewportSize({ width: 1024, height: 800 })
        await open(page, 'header-wide', { tw, query: `&hide=${hide}` })

        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1024)
        expectNoOverlap(await barBoxes(page), 1024)

        // what is left is still too much for the bar, so "More" exists and holds only shown units
        await expect(page.locator('[data-atom-header-more]')).toBeVisible()
        const text = await moreText(page)
        for (const label of labels) expect(text, `${label} is not copied`).not.toContain(label)
        expect(await page.locator('[data-atom-more-copy]').count()).toBeGreaterThan(0)
      })
    }

    test('the rightmost edge decides whether the units fit, not the last unit in the document', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 800 })
      await open(page, 'header-wide', { tw, query: '&hide=work-orders,sales-invoices,delivery-orders,stock-transfers,financial-reports,administration' })

      // one unit left: it fits, so there is no "More"
      await expect(page.locator('[data-atom-header-more]')).toBeHidden()

      // a second unit that is last in the document but drawn first (flex order), and the first one drawn last and too wide
      await page.evaluate(() => {
        const first = document.querySelector('[data-atom-header-nav] [data-atom-navlist] > [data-atom-navlist-item]')
        const second = document.querySelector('[data-atom-header-nav] [data-atom-navlist] > [data-atom-navlist-group]')

        second.style.display = ''
        second.style.order = '-1'
        first.style.order = '1'
        first.style.flex = 'none'
        first.style.width = '5000px'
        window.Alpine.$data(document.querySelector('[data-atom-header]')).fit()
      })

      await expect(page.locator('[data-atom-header-more]')).toBeVisible()
    })

    test('fit() after the header is destroyed does nothing', async ({ page }) => {
      const errors = []
      page.on('pageerror', (e) => errors.push(e.message))
      await open(page, 'header-wide', { tw })

      const thrown = await page.evaluate(async () => {
        const header = document.querySelector('[data-atom-header]')
        const data = window.Alpine.$data(header)

        window.Alpine.destroyTree(header)
        header.remove()

        try {
          data.fit()
          await document.fonts.ready.then(() => data.fit())
          document.fonts.dispatchEvent(new Event('loadingdone'))
          await new Promise((resolve) => setTimeout(resolve, 100))
          return null
        }
        catch (e) {
          return e.message
        }
      })

      expect(thrown).toBeNull()
      expect(errors).toEqual([])
    })

    test('a morph elsewhere in the header leaves the open "More" and the focused copy alone', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 800 })
      await open(page, 'header-wide', { tw, query: '&lw=1' })

      await moreTrigger(page).click()
      const copy = page.locator('[data-atom-header-more] [data-atom-more-copy]').first()
      await expect(copy).toBeVisible()
      await copy.focus()
      await copy.evaluate((el) => { el.__probe = 'same copy' })

      // a Livewire morph of the counter in the actions slot: the header refits
      await page.evaluate(() => window.Livewire.first().bump())
      await expect(page.getByTestId('shell-count')).toHaveText('1')
      await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))))

      expect(await page.evaluate(() => document.activeElement?.__probe)).toBe('same copy')
      await expect(moreTrigger(page)).toHaveAttribute('aria-expanded', 'true')
      await expect(page.locator('[data-atom-header-more] > [data-atom-navlist-group-items]')).toBeVisible()
    })

    test('"More" that was open when everything came to fit is closed when it is needed again', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 800 })
      await open(page, 'header-wide', { tw })

      await moreTrigger(page).click()
      await expect(moreTrigger(page)).toHaveAttribute('aria-expanded', 'true')

      // room for everything: hide all units but one, and measure again
      const units = '[data-atom-header-nav] [data-atom-navlist] > [data-atom-navlist-item]:not(:first-child), [data-atom-header-nav] [data-atom-navlist] > [data-atom-navlist-group]'
      await page.evaluate((units) => {
        document.querySelectorAll(units).forEach((el) => { el.dataset.hiddenByTest = ''; el.style.display = 'none' })
        window.Alpine.$data(document.querySelector('[data-atom-header]')).fit()
      }, units)
      await expect(page.locator('[data-atom-header-more]')).toBeHidden()

      // the units are back: "More" is needed again, and it comes back closed
      await page.evaluate(() => {
        document.querySelectorAll('[data-hidden-by-test]').forEach((el) => { el.style.display = '' })
        window.Alpine.$data(document.querySelector('[data-atom-header]')).fit()
      })
      await expect(page.locator('[data-atom-header-more]')).toBeVisible()
      await expect(moreTrigger(page)).toHaveAttribute('aria-expanded', 'false')
      await expect(page.locator('[data-atom-header-more] > [data-atom-navlist-group-items]')).toBeHidden()
    })

    test('a menu left open below lg is closed when the width comes back to it', async ({ page }) => {
      await page.setViewportSize({ width: 768, height: 800 })
      await open(page, 'header-wide', { tw })

      const button = page.locator('[data-atom-header-menu-button]')
      await button.click()
      await expect(page.locator('[data-atom-header-nav]')).toBeVisible()

      await page.setViewportSize({ width: 1100, height: 800 })
      await expect(button).toBeHidden()
      await page.setViewportSize({ width: 768, height: 800 })
      await expect(button).toBeVisible()

      await expect(page.locator('[data-atom-header-nav]')).toBeHidden()
      await expect(button).toHaveAttribute('aria-expanded', 'false')
    })

    test('a search popover left open at lg to xl does not take the first Escape of the menu below lg', async ({ page }) => {
      await page.setViewportSize({ width: 1100, height: 800 })
      await open(page, 'header-wide', { tw })

      await page.locator('[data-atom-shell-search-button]').click()
      await expect(page.locator('[data-atom-shell-search]')).toBeVisible()

      await page.setViewportSize({ width: 768, height: 800 })
      const button = page.locator('[data-atom-header-menu-button]')
      await button.click()
      await expect(page.locator('[data-atom-header-nav]')).toBeVisible()

      await page.keyboard.press('Escape')
      await expect(page.locator('[data-atom-header-nav]')).toBeHidden()
      await expect(button).toHaveAttribute('aria-expanded', 'false')
    })

    test('a search popover left open comes back closed after the width went through xl', async ({ page }) => {
      await page.setViewportSize({ width: 1100, height: 800 })
      await open(page, 'header-wide', { tw })

      const button = page.locator('[data-atom-shell-search-button]')
      await button.click()
      await expect(button).toHaveAttribute('aria-expanded', 'true')

      await page.setViewportSize({ width: 1300, height: 800 })
      await expect(page.locator('[data-atom-shell-search]')).toBeVisible()
      await page.setViewportSize({ width: 1100, height: 800 })

      await expect(button).toBeVisible()
      await expect(button).toHaveAttribute('aria-expanded', 'false')
      await expect(page.locator('[data-atom-shell-search]')).toBeHidden()
    })

    test('opening "More" closes the search popover and the other way round, with no focus moving', async ({ page }) => {
      await page.setViewportSize({ width: 1100, height: 800 })
      await open(page, 'header-wide', { tw })

      const search = page.locator('[data-atom-shell-search-button]')
      const more = moreTrigger(page)

      // dispatched clicks do not focus the button, which is how Safari and Firefox click one
      await more.dispatchEvent('click')
      await expect(more).toHaveAttribute('aria-expanded', 'true')
      await search.dispatchEvent('click')
      await expect(search).toHaveAttribute('aria-expanded', 'true')
      await expect(more).toHaveAttribute('aria-expanded', 'false')

      await more.dispatchEvent('click')
      await expect(more).toHaveAttribute('aria-expanded', 'true')
      await expect(search).toHaveAttribute('aria-expanded', 'false')
      await expect(page.locator('[data-atom-shell-search]')).toBeHidden()
    })

    test('a copy in "More" keeps no reference to an id that is not in the copy', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 800 })
      await open(page, 'header-wide', { tw, query: '&refs=1' })

      const original = page.locator('a[data-atom-navlist-item]:not([data-atom-more-copy])', { hasText: 'Stock transfers' })
      await expect(original).toHaveAttribute('aria-describedby', 'shell-hint')
      await expect(original).toHaveAttribute('aria-controls', 'shell-hint')

      const copy = page.locator('[data-atom-more-copy]', { hasText: 'Stock transfers' })
      await expect(copy).toHaveCount(1)
      const left = await copy.evaluate((el) => [el, ...el.querySelectorAll('*')].flatMap((node) => ['aria-describedby', 'aria-labelledby', 'aria-controls', 'for', 'id'].filter((name) => node.hasAttribute(name)).map((name) => `${node.tagName}[${name}]`)))
      expect(left).toEqual([])
    })

    // Round 4: a unit hidden at fit time and shown later by a class or style toggle (Alpine x-show) has no morph and no
    // resize of anything else to wake the bar, so the units are observed themselves
    test('a unit shown by a style change alone is fitted again', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 800 })
      await open(page, 'header-wide', { tw })

      const unit = '[data-atom-header-nav] [data-atom-navlist] > [data-atom-navlist-group]:last-child'

      await page.evaluate((unit) => {
        document.querySelector(unit).style.display = 'none'
        window.Alpine.$data(document.querySelector('[data-atom-header]')).fit()
      }, unit)
      await expect(page.locator(unit)).toBeHidden()

      // no fit() call: the style change is all there is
      await page.evaluate((unit) => { document.querySelector(unit).style.display = '' }, unit)

      await expect.poll(() => moreText(page)).toContain('Administration')
      await expect(page.locator('[data-atom-header-more]')).toBeVisible()
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1024)
      expectNoOverlap(await barBoxes(page), 1024)
    })

    test('observing the units starts no loop: no warning over a sweep of widths, and no fit when idle', async ({ page }) => {
      const messages = []
      page.on('console', (m) => messages.push(m.text()))
      page.on('pageerror', (e) => messages.push(e.message))
      await page.setViewportSize({ width: 1024, height: 800 })
      await open(page, 'header-wide', { tw })

      for (let width = 1600; width >= 1024; width -= 64) {
        await page.setViewportSize({ width, height: 800 })
        await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))))
      }

      await page.evaluate(() => {
        const data = window.Alpine.$data(document.querySelector('[data-atom-header]'))
        const fit = data.fit

        window.__fitCount = 0
        data.fit = function () { window.__fitCount++; return fit.call(this) }
      })
      await page.waitForTimeout(500)

      expect(await page.evaluate(() => window.__fitCount)).toBe(0)
      expect(messages.filter((m) => /ResizeObserver|loop/i.test(m))).toEqual([])
      expect(messages.filter((m) => /warn|error/i.test(m))).toEqual([])
    })

    test('a context slot that grows takes room from the units, and they are fitted again', async ({ page }) => {
      await page.setViewportSize({ width: 1100, height: 800 })
      await open(page, 'header-wide', { tw })

      const copies = () => page.locator('[data-atom-more-copy]').count()
      const before = await copies()

      await page.locator('[data-atom-shell-context] button[aria-haspopup]').first().evaluate((el) => {
        const spacer = document.createElement('span')
        spacer.style.cssText = 'display: inline-block; flex: none; width: 300px'
        el.append(spacer)
      })

      await expect.poll(copies).toBeGreaterThan(before)
      expectNoOverlap(await barBoxes(page), 1100)
    })
  })
}

// The screenshots a human compares with the approved sample by eye. Written only when asked: SHOTS=<directory>.
if (process.env.SHOTS) {
  const shot = (page, name) => page.screenshot({ path: `${process.env.SHOTS}/${name}.png`, fullPage: false })
  const settle = (page) => page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))))

  for (const mode of ['light', 'dark']) {
    const isDark = mode === 'dark'
    const widths = isDark ? [1280] : [390, 768, 1100, 1280]

    for (const width of widths) {
      test(`screenshot: header, ${mode}, ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 800 })
        await page.emulateMedia({ colorScheme: mode })
        await open(page, 'header', { query: isDark ? '&dark=1' : '' })

        if (width === 768) await page.locator('[data-atom-header-menu-button]').click()
        if (width === 1100) await page.locator('[data-atom-shell-search-button]').click()

        await settle(page)
        await shot(page, `header-${mode}-${width}${width === 768 ? '-menu' : ''}${width === 1100 ? '-search' : ''}`)
      })
    }

    test(`screenshot: header with long labels and "More" open, ${mode}, 1280px`, async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 })
      await page.emulateMedia({ colorScheme: mode })
      await open(page, 'header-wide', { query: isDark ? '&dark=1' : '' })

      await page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]').click()
      await settle(page)
      await shot(page, `header-wide-more-${mode}-1280`)
    })

    for (const width of isDark ? [1280] : [390, 1280]) {
      test(`screenshot: sidebar, ${mode}, ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 800 })
        await page.emulateMedia({ colorScheme: mode })
        await open(page, 'sidebar', { query: isDark ? '&dark=1' : '' })

        await settle(page)
        await shot(page, `sidebar-${mode}-${width}`)
      })
    }
  }
}
