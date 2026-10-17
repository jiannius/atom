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
