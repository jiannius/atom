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

// The bar/menu switch is at 1024px (lg): the logo, the context switcher, six items, an action and the account do not fit
// side by side before that. At every width either the menu button is shown, or nothing in the bar overlaps: the nav's own
// box AND each of its items end before the actions begin (the items can spill out of a box that is too narrow for them,
// which is how "Settings" once drew over the search field at 1024px).
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
          return
        }

        const left = (await box(page.locator('[data-atom-shell-actions]'))).x
        const nav = await box(page.locator('[data-atom-header-nav]'))
        expect(nav.x + nav.width, 'the nav box').toBeLessThanOrEqual(left)

        const items = await page.locator('[data-atom-header-nav] a[data-atom-navlist-item], [data-atom-header-nav] [data-atom-navlist-group-trigger]').evaluateAll((els) => els
          .filter((el) => el.getBoundingClientRect().width > 0)
          .map((el) => { const r = el.getBoundingClientRect(); return { x: r.x, right: r.right } }))
        expect(items.length).toBeGreaterThanOrEqual(6)
        for (const item of items) {
          expect(item.right, 'a nav item').toBeLessThanOrEqual(left)
        }

        // the context switcher ends before the first item, the logo before the switcher
        const context = await box(page.locator('[data-atom-shell-context]'))
        expect(context.x + context.width).toBeLessThanOrEqual(Math.min(...items.map((i) => i.x)))
        const brand = await box(page.locator('[data-atom-header-bar] a').first())
        expect(brand.x + brand.width).toBeLessThanOrEqual(context.x)

        // the search field is a wide-screen extra: it shows from 1280px, and is out of the way below it
        if (width < 1280) {
          await expect(page.locator('[data-atom-shell-search]')).toBeHidden()
        }
        else {
          await expect(page.locator('[data-atom-shell-search]')).toBeVisible()
          const search = await box(page.locator('[data-atom-shell-search]'))
          expect(search.x).toBeGreaterThanOrEqual(nav.x + nav.width)
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
      expect(await contrast(page, await style(link(page, name), 'color'), canvas), `${name} on the bar`).toBeGreaterThanOrEqual(4.5)
    }

    const pill = page.locator('[data-atom-navlist-count]').first()
    expect(await contrast(page, await style(pill, 'color'), await style(pill, 'backgroundColor')), 'a count pill').toBeGreaterThanOrEqual(4.5)

    const trigger = page.locator('[data-atom-navlist-group-trigger]', { hasText: 'Reports' })
    await trigger.click()
    const panel = page.locator('[data-atom-navlist-group-items]').first()
    const raised = await style(panel, 'backgroundColor')
    expect(await luminance(page, raised)).toBeLessThan(3)
    expect(await contrast(page, await style(link(page, 'Sales'), 'color'), raised), 'an item in the dropdown').toBeGreaterThanOrEqual(4.5)
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
