import { test, expect } from '@playwright/test'

// The V4-2c parts (frameless table, numeric columns, the quiet header, toolbar, saved views, form.section, the save bar and the
// timeline) on the consumer Tailwind build (`node tests/consumer/build.mjs` first), once at the hosts' minimum Tailwind and once
// at the newest 4.x. The list, document, form and slide-over pages are the pattern pages under resources/views/docs/patterns, so
// what is measured is what a host copies. Numbers come from the approved sample (sample/invoices.html): sizes, weights, paddings
// and radii, never a text width, so a Linux font fallback (DejaVu) changes nothing here. Screenshots (SHOTS=<directory>) are for
// a human to compare by eye, not a gate.

async function open(page, url, { tw = 'latest', dark = false } = {}) {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.emulateMedia({ colorScheme: dark ? 'dark' : 'light' })
  await page.goto(`${url}${url.includes('?') ? '&' : '?'}tw=${tw}${dark ? '&dark=1' : ''}`)
  await page.evaluate(() => document.fonts.ready)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const livewire = async (page, url, opts) => {
  await open(page, url, opts)
  await page.waitForFunction(() => window.Livewire?.first())
}

const shot = async (page, name, options = {}) => {
  if (process.env.SHOTS) await page.screenshot({ path: `${process.env.SHOTS}/${name}.png`, ...options })
}

const styles = (locator, props) => locator.evaluate((el, props) => {
  const s = getComputedStyle(el)
  return Object.fromEntries(props.map((p) => [p, s.getPropertyValue(p)]))
}, props)

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }
})

// the box of an element's TEXT, not of the element
const textBox = (locator) => locator.evaluate((el) => {
  const range = document.createRange()
  range.selectNodeContents(el)
  const r = range.getBoundingClientRect()
  return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }
})

const contrastBetween = (page, a, b) => page.evaluate(([a, b]) => {
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
  const [x, y] = [lum(rgb(a)), lum(rgb(b))]
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)
}, [a, b])

// How far the save bar's surface reaches, from the computed style of its ::before: the box, plus the box-shadow spread, clipped by the
// clip-path insets the shell sets. (The side regions are a shadow, so they are not hit-testable: a click goes through them.)
const surfaceReach = (page) => page.evaluate(() => {
  const bar = document.querySelector('[data-atom-save-bar]')
  const r = bar.getBoundingClientRect()
  const c = getComputedStyle(bar, '::before')
  const spread = parseFloat((c.boxShadow.match(/ (-?[\d.]+)px$/) || [0, 0])[1])
  const inset = c.clipPath.match(/^inset\((.+)\)$/)

  // no clip-path: the shadow is not held to the content area at all
  if (!inset) return { left: r.left - spread, right: r.right + spread, clipped: false }

  // the computed value is the shortest form: one to four lengths, top, right, bottom, left
  const v = inset[1].split(' ').map(parseFloat)
  const [, right, , left] = [v[0], v[1] ?? v[0], v[2] ?? v[0], v[3] ?? v[1] ?? v[0]]

  return { left: r.left - Math.min(Math.max(-left, 0), spread) + Math.max(left, 0), right: r.right + Math.min(Math.max(-right, 0), spread) - Math.max(right, 0), clipped: true }
})

const barAt = async (page, x) => {
  const reach = await surfaceReach(page)

  return x >= reach.left - 0.5 && x <= reach.right + 0.5
}

// The colour of one pixel of the screen, read back from a screenshot
const pixel = async (page, x, y) => {
  const png = await page.screenshot({ clip: { x: Math.round(x), y: Math.round(y), width: 1, height: 1 } })

  return page.evaluate(async (base64) => {
    const image = new Image()
    image.src = `data:image/png;base64,${base64}`
    await image.decode()
    const cv = document.createElement('canvas')
    cv.width = cv.height = 1
    const ctx = cv.getContext('2d')
    ctx.drawImage(image, 0, 0)

    return [...ctx.getImageData(0, 0, 1, 1).data].slice(0, 3)
  }, png.toString('base64'))
}

const clientWidth = (page) => page.evaluate(() => document.documentElement.clientWidth)

const noSideScroll = (page) => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)

for (const tw of ['floor', 'latest']) {
  test.describe(`list page, ${tw} consumer build`, () => {
    const list = (page, query = '', opts = {}) => open(page, `/atom/e2e/shell-list${query ? `?${query}` : ''}`, { tw, ...opts })

    test.describe('at 1280px', () => {
      test.use({ viewport: { width: 1280, height: 900 } })

      test('the views, the toolbar, the rows and the foot are one 10px panel; the table draws no frame of its own', async ({ page }) => {
        await list(page)

        const panel = await styles(page.getByTestId('list-panel'), ['border-top-width', 'border-top-left-radius', 'overflow-x', 'background-color'])
        const table = await styles(page.locator('[data-atom-table-frameless]'), ['border-top-width', 'border-top-left-radius', 'box-shadow', 'background-color'])

        expect(panel['border-top-width']).toBe('1px')
        expect(panel['border-top-left-radius']).toBe('10px')
        expect(panel['overflow-x']).toBe('hidden')
        expect(table['border-top-width']).toBe('0px')
        expect(table['border-top-left-radius']).toBe('0px')
        expect(table['box-shadow']).toBe('none')
        // the table paints nothing of its own: it is the panel's surface behind it
        expect(table['background-color']).toBe('rgba(0, 0, 0, 0)')
        await expect(page.locator('[data-atom-table-frameless] [data-atom-table-scroll]')).toHaveCount(1)
        await shot(page, `list-${tw}-1280`, { fullPage: true })
      })

      test('a header cell is a 12px label at 510, not uppercase, in the cell padding', async ({ page }) => {
        await list(page)

        const label = page.locator('[data-atom-table] thead th').first().locator('> div')
        const s = await styles(label, ['font-size', 'font-weight', 'text-transform', 'letter-spacing', 'padding-left', 'padding-top', 'font-family'])
        const th = await styles(page.locator('[data-atom-table] thead th').first(), ['border-bottom-width', 'padding-left'])
        const cell = await styles(page.locator('[data-atom-table] tbody td').first(), ['padding-left'])

        expect(s['font-size']).toBe('12px')
        expect(s['font-weight']).toBe('510')
        expect(s['text-transform']).toBe('none')
        expect(['normal', '0px']).toContain(s['letter-spacing'])
        expect(s['font-family']).toContain('Geist')
        // 12px sideways like the cell under it, 8px above and below
        expect(s['padding-left']).toBe('12px')
        expect(s['padding-top']).toBe('8px')
        expect(cell['padding-left']).toBe('12px')
        expect(th['border-bottom-width']).toBe('1px')
      })

      test('a cell pads 9px by 12px with a 13px body at 1.35, and a row is at least 40px', async ({ page }) => {
        await list(page)

        const cell = page.locator('[data-atom-table] tbody td').nth(2)
        const s = await styles(cell, ['padding-top', 'padding-bottom', 'padding-left', 'padding-right', 'font-size', 'line-height'])
        const rows = await page.locator('[data-atom-table] tbody tr').evaluateAll((trs) => trs.map((tr) => tr.getBoundingClientRect().height))

        expect([s['padding-top'], s['padding-bottom'], s['padding-left'], s['padding-right']]).toEqual(['9px', '9px', '12px', '12px'])
        expect(s['font-size']).toBe('13px')
        expect(parseFloat(s['line-height'])).toBeCloseTo(17.55, 1)
        expect(Math.min(...rows)).toBeGreaterThanOrEqual(40)
      })

      test('a two-line cell (a name and a 12px line under it) keeps its padding off the row borders', async ({ page }) => {
        await list(page)

        // the first column is the checkbox
        const cell = page.locator('[data-atom-table] tbody tr').first().locator('td').nth(2)
        const text = await textBox(cell.locator('> div').first())
        const sub = await textBox(cell.locator('> div').nth(1))
        const outer = await box(cell)
        const row = await box(page.locator('[data-atom-table] tbody tr').first())
        const subStyle = await styles(cell.locator('> div').nth(1), ['font-size', 'color'])

        expect(subStyle['font-size']).toBe('12px')
        // two lines, one over the other, each a padding clear of the cell edge
        expect(sub.y).toBeGreaterThan(text.y + 10)
        expect(text.y - outer.y).toBeGreaterThanOrEqual(9)
        expect(outer.bottom - sub.bottom).toBeGreaterThanOrEqual(9)
        // taller than the 40px floor: the cell grew instead of squeezing the second line against the border
        expect(row.height).toBeGreaterThan(40)
      })

      test('money is a numeric column: right-aligned header and cells ending on one edge, tabular, the currency named once', async ({ page }) => {
        await list(page)

        const th = page.locator('[data-atom-table] thead th', { hasText: 'Amount (RM)' })
        const cells = page.locator('[data-atom-table] tbody tr td:nth-child(6)')
        const s = await styles(cells.first(), ['text-align', 'font-variant-numeric'])
        const head = await textBox(th.locator('> div'))
        const right = []

        for (const i of [0, 1, 2, 4]) {
          right.push((await textBox(cells.nth(i))).right)
        }

        expect(s['text-align']).toBe('right')
        expect(s['font-variant-numeric']).toContain('tabular-nums')
        expect((await styles(th, ['text-align']))['text-align']).toBe('right')
        // the header text and every amount end on the same edge
        for (const edge of right) expect(Math.abs(edge - head.right)).toBeLessThan(1.5)
        // the currency is in the header and in no cell
        await expect(th).toContainText('(RM)')
        expect(await cells.evaluateAll((els) => els.some((el) => /RM/.test(el.textContent)))).toBe(false)
      })

      test('an identifier is Geist Mono at 12px', async ({ page }) => {
        await list(page)

        const id = await styles(page.locator('[data-atom-table] tbody .font-id').first(), ['font-family', 'font-size', 'letter-spacing'])

        expect(id['font-family']).toContain('Geist Mono')
        expect(id['font-size']).toBe('12px')
        expect(parseFloat(id['letter-spacing'])).toBeCloseTo(-0.12, 2)
      })

      test('a hovered row takes a faint fill; a row link opens the record', async ({ page }) => {
        await list(page)

        const row = page.locator('[data-atom-table] tbody tr').first()
        const before = (await styles(row, ['background-color']))['background-color']
        await row.hover()
        await expect.poll(async () => (await styles(row, ['background-color']))['background-color']).not.toBe(before)
        expect(await styles(row, ['cursor'])).toEqual({ cursor: 'pointer' })
      })

      test('the saved views are a 13px strip with counts; the chosen one is underlined 2px in the foreground colour', async ({ page }) => {
        await list(page, 'view=overdue')

        const strip = page.locator('[data-atom-table-views]')
        const on = strip.locator('[aria-pressed="true"]')
        const off = strip.locator('[data-value="paid"]')
        const count = on.locator('[data-atom-table-view-count]')

        await expect(on).toHaveCount(1)
        await expect(on).toContainText('Overdue')
        await expect(count).toHaveText('2')
        expect((await styles(strip, ['border-bottom-width']))['border-bottom-width']).toBe('1px')

        const a = await styles(on, ['font-size', 'font-weight', 'border-bottom-width', 'border-bottom-color', 'color'])
        const b = await styles(off, ['border-bottom-width', 'border-bottom-color'])
        const c = await styles(count, ['font-size', 'font-variant-numeric', 'font-weight'])

        expect(a['font-size']).toBe('13px')
        expect(a['font-weight']).toBe('510')
        expect(a['border-bottom-width']).toBe('2px')
        expect(a['border-bottom-color']).toBe(a.color)
        expect(b['border-bottom-color']).toBe('rgba(0, 0, 0, 0)')
        expect(c['font-size']).toBe('12px')
        expect(c['font-variant-numeric']).toContain('tabular-nums')
        expect(c['font-weight']).toBe('400')
        await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(2)
      })

      test('choosing a view link shows that view', async ({ page }) => {
        await list(page)

        await page.locator('[data-atom-table-views] [data-value="paid"]').click()
        await expect(page).toHaveURL(/view=paid/)
        await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(3)
        await expect(page.locator('[data-atom-table-views] [aria-pressed="true"]')).toContainText('Paid')
      })

      test('the toolbar is a 240px search with the export at the far end, in its own hairline strip', async ({ page }) => {
        await list(page)

        const bar = page.locator('[data-atom-table-toolbar]')
        const search = await box(bar.locator('[data-atom-table-search]'))
        const end = await box(bar.locator('[data-atom-table-toolbar-end]'))
        const barBox = await box(bar)

        expect(search.width).toBe(240)
        expect(Math.abs(end.right - (barBox.right - 12))).toBeLessThan(1.5)
        expect(end.x).toBeGreaterThan(search.right)
        expect((await styles(bar, ['border-bottom-width', 'padding-left']))).toEqual({ 'border-bottom-width': '1px', 'padding-left': '12px' })
      })

      test('the count and the total are in the foot: 12px, tabular, and they follow the view', async ({ page }) => {
        await list(page, 'view=paid')

        const foot = page.getByTestId('list-sum')
        await expect(foot).toContainText('3 of 8 invoices · RM 10,000.00')
        expect(await styles(foot, ['font-size', 'font-variant-numeric'])).toEqual({ 'font-size': '12px', 'font-variant-numeric': 'tabular-nums' })
      })

      test('the header holds the page`s one primary action, and nothing in the panel is a second', async ({ page }) => {
        await list(page)

        await expect(page.locator('[data-atom-button].bg-accent:not(dialog *)')).toHaveCount(1)
        await expect(page.getByTestId('list-primary')).toHaveClass(/(?:^| )bg-accent(?: |$)/)
      })

      test('header text and cell text hold 4.5:1 on the panel in both themes', async ({ page }) => {
        for (const dark of [false, true]) {
          await list(page, '', { dark })

          const surface = (await styles(page.getByTestId('list-panel'), ['background-color']))['background-color']
          const header = (await styles(page.locator('[data-atom-table] thead th > div').first(), ['color']))['color']
          const body = (await styles(page.locator('[data-atom-table] tbody td').nth(1), ['color']))['color']
          const muted = (await styles(page.locator('[data-atom-table] tbody td').nth(3), ['color']))['color']

          for (const [name, color] of [['header', header], ['body', body], ['muted', muted]]) {
            expect(await contrastBetween(page, color, surface), `${name}, ${dark ? 'dark' : 'light'}`).toBeGreaterThanOrEqual(4.5)
          }

          if (dark) await shot(page, `list-${tw}-1280-dark`, { fullPage: true })
        }
      })
    })

    test.describe('at 390px', () => {
      test.use({ viewport: { width: 390, height: 844 } })

      test('the table scrolls sideways inside its panel, never the page, and the status column is in reach', async ({ page }) => {
        await list(page)

        expect(await noSideScroll(page)).toBe(true)

        const scroll = page.locator('[data-atom-table-scroll]')
        const dims = await scroll.evaluate((el) => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth, overflowX: getComputedStyle(el).overflowX }))
        const panel = await box(page.getByTestId('list-panel'))

        expect(dims.overflowX).toBe('auto')
        expect(dims.scrollWidth).toBeGreaterThan(dims.clientWidth)
        // the panel is the page's width less its gutters, whatever the table inside it needs
        expect(panel.x).toBe(16)
        expect(Math.round(panel.right)).toBe(374)
        await shot(page, `list-${tw}-390`, { fullPage: true })

        await scroll.evaluate((el) => { el.scrollLeft = el.scrollWidth })

        const status = await box(page.locator('[data-atom-table] thead th', { hasText: 'Status' }))
        expect(status.right).toBeLessThanOrEqual(panel.right + 1)
        expect(status.x).toBeGreaterThanOrEqual(panel.x)
        expect(await noSideScroll(page)).toBe(true)
        await expect(page.locator('[data-atom-table] tbody tr').first().locator('[data-atom-status]')).toBeInViewport()
      })

      test('the views scroll inside their own strip and the toolbar wraps: the search fills the row beside the icon-only Export', async ({ page }) => {
        await list(page)

        const search = await box(page.locator('[data-atom-table-toolbar] [data-atom-table-search]'))
        const exporter = await box(page.getByTestId('list-panel').getByRole('button', { name: 'Export' }))
        const panel = await box(page.getByTestId('list-panel'))

        // the whole row of the panel less its 12px strip padding: the search, an 8px gap and the Export icon
        expect(Math.abs(search.width + 8 + exporter.width - (panel.width - 2 - 24))).toBeLessThan(2)
        expect(await noSideScroll(page)).toBe(true)
      })
    })
  })

  test.describe(`a wide table in a page at 390px, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 390, height: 844 } })

    // the first host trial: a table of many columns directly in <atom:page> scrolled the page sideways and cut the status column off
    // (a parent that sizes its children by content, a grid or a flex row, is what widened the page before the table's root was min-w-0)
    for (const [name, query] of [['framed', ''], ['frameless in a panel', 'frameless=1'], ['framed in a grid parent', 'parent=grid'], ['framed in a flex parent', 'parent=flex'], ['frameless in a grid parent', 'parent=grid&frameless=1']]) {
      test(`${name}: the table scrolls inside its own box, the page does not, and the status column is reachable`, async ({ page }) => {
        await open(page, `/atom/e2e/table-wide-page${query ? `?${query}` : ''}`, { tw })

        expect(await noSideScroll(page)).toBe(true)

        const scroll = page.locator('[data-atom-table-scroll]')
        const dims = await scroll.evaluate((el) => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth }))
        const pageBox = await box(page.locator('[data-atom-page]'))
        const table = await box(page.getByTestId('wide-table'))

        expect(dims.scrollWidth).toBeGreaterThan(dims.clientWidth)
        expect(table.right).toBeLessThanOrEqual(pageBox.right - 16 + 1)

        await scroll.evaluate((el) => { el.scrollLeft = el.scrollWidth })

        const status = await box(page.locator('[data-atom-table] thead th', { hasText: 'Status' }))
        expect(status.right).toBeLessThanOrEqual(table.right + 1)
        expect(await noSideScroll(page)).toBe(true)
        await expect(page.locator('[data-atom-table] tbody tr').first().locator('[data-atom-status]')).toBeInViewport()
      })
    }
  })

  test.describe(`saved views, the toolbar and the bulk bar (Livewire), ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1100, height: 800 } })

    const views = (page, query = '') => livewire(page, `/atom/e2e/table-views${query ? `?${query}` : ''}`, { tw })
    const bound = (page) => page.evaluate(() => window.Livewire.all().find((c) => c.name === 'atom-e2e-table-views').$wire.$get('filters.view'))
    const view = (page, value) => page.locator(`[data-atom-table-views] [data-value="${value}"]`)
    const chip = (page, value) => view(page, value).locator('[data-atom-table-view-count]')

    test('switching a view updates the bound property, the rows and the URL', async ({ page }) => {
      await views(page)

      expect(await bound(page)).toBe('all')
      await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(5)
      await expect(view(page, 'all')).toHaveAttribute('aria-pressed', 'true')

      await view(page, 'overdue').click()

      await expect.poll(() => bound(page)).toBe('overdue')
      await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(2)
      await expect(page.getByTestId('bound')).toHaveText('overdue')
      await expect(view(page, 'overdue')).toHaveAttribute('aria-pressed', 'true')
      await expect(view(page, 'all')).toHaveAttribute('aria-pressed', 'false')
      await expect(page).toHaveURL(/view=overdue/)
      await shot(page, `views-${tw}`)
    })

    test('the count chips follow what the host computes: a change moves the counts, not the chosen view', async ({ page }) => {
      await views(page, 'view=unpaid')

      await expect(chip(page, 'all')).toHaveText('5')
      await expect(chip(page, 'unpaid')).toHaveText('3')
      await expect(chip(page, 'paid')).toHaveText('2')
      await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(3)

      await page.getByTestId('mark-paid').click()

      await expect(chip(page, 'unpaid')).toHaveText('2')
      await expect(chip(page, 'paid')).toHaveText('3')
      await expect(chip(page, 'all')).toHaveText('5')
      // still on the same view, with its row gone
      await expect(view(page, 'unpaid')).toHaveAttribute('aria-pressed', 'true')
      await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(2)
    })

    test('a reload keeps the view: the URL carries it and the strip reads it back', async ({ page }) => {
      await views(page, 'view=paid')

      expect(await bound(page)).toBe('paid')
      await expect(view(page, 'paid')).toHaveAttribute('aria-pressed', 'true')
      await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(2)

      await page.reload()
      await page.waitForFunction(() => window.Livewire?.first())

      expect(await bound(page)).toBe('paid')
      await expect(view(page, 'paid')).toHaveAttribute('aria-pressed', 'true')
    })

    test('a view with a value the list does not know opens the default one', async ({ page }) => {
      await views(page, 'view=nope')

      expect(await bound(page)).toBe('all')
      await expect(view(page, 'all')).toHaveAttribute('aria-pressed', 'true')
    })

    test('a view is a button: Tab reaches it and Enter chooses it', async ({ page }) => {
      await views(page)

      await view(page, 'paid').focus()
      await page.keyboard.press('Enter')

      await expect.poll(() => bound(page)).toBe('paid')
      await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(2)
    })

    test('the search inside a view narrows its rows and leaves the counts alone', async ({ page }) => {
      await views(page, 'view=all')

      await page.getByPlaceholder('Search invoices').fill('customer c')

      await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(1)
      await expect(page.getByTestId('shown')).toHaveText('1 shown')
      await expect(chip(page, 'all')).toHaveText('5')
    })

    test('switching a view drops a bulk selection: the bar goes and the ticked ids are gone', async ({ page }) => {
      await views(page)

      const checked = () => page.evaluate(() => window.Livewire.all().find((c) => c.name === 'atom-e2e-table-views').$wire.$get('_table.checkboxes'))

      await page.locator('tbody [data-atom-table-checkbox]').first().click()
      await expect(page.locator('[data-atom-table-checked]')).toBeVisible()
      expect(await checked()).toHaveLength(1)

      await view(page, 'overdue').click()

      await expect(page.locator('[data-atom-table-checked]')).toHaveCount(0)
      await expect(page.locator('[data-atom-table-toolbar]')).toBeVisible()
      await expect.poll(checked).toEqual([])
      await expect(page.locator('[data-atom-table] tbody tr')).toHaveCount(2)
    })

    test('a view bound to an integer property still marks its item, on load and after a click', async ({ page }) => {
      await views(page)

      const stage = (value) => page.locator(`[data-testid="stages"] [data-value="${value}"]`)

      await expect(stage('2')).toHaveAttribute('aria-pressed', 'true')
      await expect(stage('1')).toHaveAttribute('aria-pressed', 'false')

      await stage('3').click()

      await expect(stage('3')).toHaveAttribute('aria-pressed', 'true')
      await expect(stage('2')).toHaveAttribute('aria-pressed', 'false')
    })

    test('ticking a row swaps the toolbar for the bulk bar, and clearing it brings the toolbar back', async ({ page }) => {
      await views(page)

      const toolbar = page.locator('[data-atom-table-toolbar]')
      const checked = page.locator('[data-atom-table-checked]')

      await expect(toolbar).toBeVisible()
      await expect(checked).toHaveCount(0)
      // the toolbar sits in the table's own hairline strip
      expect((await styles(toolbar.locator('xpath=..'), ['border-bottom-width', 'padding-left']))).toEqual({ 'border-bottom-width': '1px', 'padding-left': '12px' })

      await page.locator('tbody [data-atom-table-checkbox]').first().click()

      await expect(checked).toBeVisible()
      await expect(page.getByTestId('bulk-action')).toBeVisible()
      await expect(toolbar).toHaveCount(0)
      expect((await styles(checked, ['border-bottom-width', 'padding-left']))).toEqual({ 'border-bottom-width': '1px', 'padding-left': '12px' })

      await page.locator('tbody [data-atom-table-checkbox]').first().click()

      await expect(toolbar).toBeVisible()
      await expect(checked).toHaveCount(0)
    })
  })

  test.describe(`form page, ${tw} consumer build`, () => {
    const form = (page, query = '', opts = {}) => open(page, `/atom/e2e/shell-form${query ? `?${query}` : ''}`, { tw, ...opts })
    const section = (page, name) => page.locator('[data-atom-form-section]', { hasText: name }).first()

    test.describe('at 1280px', () => {
      test.use({ viewport: { width: 1280, height: 700 } })

      test('aside: the heading takes a 220px column beside the fields, which are two across', async ({ page }) => {
        await form(page)

        const details = section(page, 'Dates and terms')
        const outer = await box(details)
        const label = await box(details.locator('[data-atom-form-section-label]'))
        const fields = await box(details.locator('[data-atom-form-section-body]'))
        const inputs = details.locator('[data-atom-form-section-fields] > *')
        const [a, b, c] = [await box(inputs.nth(0)), await box(inputs.nth(1)), await box(inputs.nth(2))]

        expect(label.width).toBe(220)
        expect(label.x).toBe(outer.x)
        expect(fields.x - label.right).toBe(24)
        expect(Math.abs(label.y - fields.y)).toBeLessThan(1)
        // two columns: one row holds the first two fields, the third starts a new row
        expect(Math.abs(a.y - b.y)).toBeLessThan(1)
        expect(b.x).toBeGreaterThan(a.right)
        expect(c.y).toBeGreaterThan(a.bottom)
        expect(Math.abs(c.x - a.x)).toBeLessThan(1)
        await shot(page, `form-${tw}-1280`, { fullPage: true })
      })

      test('stacked: the heading is above the fields even on a wide screen, and the fields stay two across', async ({ page }) => {
        await form(page, 'layout=stacked')

        const details = section(page, 'Dates and terms')
        const label = await box(details.locator('[data-atom-form-section-label]'))
        const fields = await box(details.locator('[data-atom-form-section-body]'))
        const outer = await box(details)
        const inputs = details.locator('[data-atom-form-section-fields] > *')
        const [a, b] = [await box(inputs.nth(0)), await box(inputs.nth(1))]

        expect(label.bottom).toBeLessThanOrEqual(fields.y)
        expect(fields.width).toBe(outer.width)
        expect(Math.abs(a.y - b.y)).toBeLessThan(1)
        await shot(page, `form-stacked-${tw}-1280`, { fullPage: true })
      })

      test('a section is set off from the one before by a hairline and 24px; the first has neither', async ({ page }) => {
        await form(page)

        const first = await styles(page.locator('[data-atom-form-section]').first(), ['border-top-width', 'padding-top'])
        const second = await styles(page.locator('[data-atom-form-section]').nth(1), ['border-top-width', 'padding-top', 'border-top-color'])
        const gap = (await box(page.locator('[data-atom-form-section]').nth(1))).y - (await box(page.locator('[data-atom-form-section]').first())).bottom

        expect(first).toEqual({ 'border-top-width': '0px', 'padding-top': '0px' })
        expect(second['border-top-width']).toBe('1px')
        expect(second['padding-top']).toBe('24px')
        // 24px of the form's gap above the hairline
        expect(gap).toBe(24)
        expect(second['border-top-color']).not.toBe('rgba(0, 0, 0, 0)')
      })

      test('the section heading is 14px at 590, its help 13px muted', async ({ page }) => {
        await form(page)

        const h = await styles(page.locator('[data-atom-form-section-heading]').first(), ['font-size', 'font-weight', 'margin-top'])
        const p = await styles(page.locator('[data-atom-form-section-subheading]').first(), ['font-size', 'margin-top'])

        expect(h['font-size']).toBe('14px')
        expect(h['font-weight']).toBe('590')
        expect(p['font-size']).toBe('13px')
        expect(p['margin-top']).toBe('4px')
      })

      test('the save bar is pinned to the bottom of the screen on a long page, and stays there to the end', async ({ page }) => {
        await form(page)

        const bar = page.locator('[data-atom-save-bar]')
        const doc = await page.evaluate(() => document.documentElement.scrollHeight)
        const s = await styles(bar, ['position', 'bottom', 'z-index'])
        // the surface and the hairline are the bar's pseudo-element
        const surface = await bar.evaluate((el) => {
          const c = getComputedStyle(el, '::before')
          const h = getComputedStyle(el, '::after')
          return { border: h.height, hairline: h.backgroundColor, blur: c.backdropFilter, background: c.backgroundColor }
        })

        // a page taller than the screen, or the test proves nothing
        expect(doc).toBeGreaterThan(700 * 1.15)
        expect(s.position).toBe('sticky')
        expect(s.bottom).toBe('0px')
        // the hairline is a 1px ::after at the bar's top, in the border colour; the surface a translucent ::before
        expect(surface.border).toBe('1px')
        expect(surface.hairline).not.toBe('rgba(0, 0, 0, 0)')
        expect(surface.blur).toContain('blur(8px)')
        // a surface you can see through: the blurred bar is not opaque
        expect(surface.background).toMatch(/\/ 0?\.9\d*\)|rgba\(.*0\.9/)

        // at the top, in the middle, and at the very end the bar's bottom edge is the screen's: it takes the page's bottom room
        for (const y of [0, 400, doc]) {
          await page.evaluate((y) => window.scrollTo(0, y), y)
          const b = await box(bar)

          expect(Math.abs(b.bottom - 700), `scrolled to ${y}`).toBeLessThan(1.5)
          await expect(bar).toBeInViewport()
        }

        // at the foot the page's own 120px of room sits under the bar, and the last field is not hidden by it
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))
        await expect(page.getByLabel('Note to customer (optional)')).toBeInViewport()
        const note = await box(page.getByLabel('Note to customer (optional)'))
        expect(note.bottom).toBeLessThanOrEqual((await box(bar)).y)
        await shot(page, `form-savebar-${tw}-1280`)
      })

      test('the bar`s own box is the page`s, and its surface goes on to the screen edge: no gap on the right in either shell, the sidebar left alone', async ({ page }) => {
        for (const shell of ['sidebar', 'header']) {
          await form(page, `shell=${shell}`)

          const bar = await box(page.locator('[data-atom-save-bar]'))
          const pageBox = await box(page.locator('[data-atom-page]'))
          const gutter = await styles(page.locator('[data-atom-page]'), ['padding-left'])
          const width = await clientWidth(page)

          expect(Math.abs(bar.x - pageBox.x), shell).toBeLessThan(1)
          expect(Math.abs(bar.right - pageBox.right), shell).toBeLessThan(1)
          expect(gutter['padding-left'], shell).toBe(shell === 'header' ? '24px' : '28px')
          // this page is a 980px column, so the bar's box stops well short of the screen; the surface does not
          expect(bar.right, shell).toBeLessThan(width - 20)
          expect(await barAt(page, width - 1), `${shell}: right edge`).toBe(true)
          expect(await barAt(page, bar.right + 5), `${shell}: past the page column`).toBe(true)
          // the surface starts at the shell's content edge: the sidebar shell's sidebar is not covered, the top nav's screen edge is reached
          if (shell === 'sidebar') {
            expect(await barAt(page, bar.x + 2), shell).toBe(true)
            expect(await barAt(page, bar.x - 5), 'over the sidebar').toBe(false)
          } else {
            expect(await barAt(page, 1), shell).toBe(true)
          }
          expect(await noSideScroll(page), shell).toBe(true)
        }
      })

      test('the bar`s content sits in the same gutter as the page: the totals at the start, the buttons at the end', async ({ page }) => {
        await form(page)

        const pageBox = await box(page.locator('[data-atom-page]'))
        const start = await box(page.locator('[data-atom-form-actions-start]'))
        const buttons = await box(page.locator('[data-atom-form-actions-buttons]'))
        const heading = await box(page.locator('[data-atom-page-header-title]'))

        expect(Math.abs(start.x - heading.x)).toBeLessThan(1)
        expect(Math.abs(buttons.right - (pageBox.right - 28))).toBeLessThan(1)
        expect((await styles(page.locator('[data-atom-form-actions-start]'), ['font-variant-numeric']))['font-variant-numeric']).toContain('tabular-nums')
        expect((await styles(page.getByTestId('grand-total'), ['font-size', 'font-weight']))).toEqual({ 'font-size': '17px', 'font-weight': '590' })
      })

      test('the submit is the one primary action', async ({ page }) => {
        await form(page)

        await expect(page.locator('[data-atom-button].bg-accent:not(dialog *)')).toHaveCount(1)
        await expect(page.getByTestId('form-primary')).toHaveClass(/(?:^| )bg-accent(?: |$)/)
        expect(await page.getByTestId('form-primary').getAttribute('type')).toBe('submit')
      })

      test('the bar in the dark theme is a dark, translucent surface with readable totals', async ({ page }) => {
        await form(page, '', { dark: true })

        const surface = (await styles(page.locator('[data-atom-page]'), ['background-color']))['background-color']
        const total = (await styles(page.getByTestId('grand-total'), ['color']))['color']
        const canvas = (await styles(page.locator('body'), ['background-color']))['background-color']

        expect(await contrastBetween(page, total, canvas)).toBeGreaterThanOrEqual(7)
        expect(await contrastBetween(page, surface === 'rgba(0, 0, 0, 0)' ? canvas : surface, canvas)).toBeGreaterThan(0)
        await shot(page, `form-savebar-${tw}-1280-dark`)
      })
    })

    test.describe('at 390px', () => {
      test.use({ viewport: { width: 390, height: 800 } })

      test('aside collapses: the heading is above the fields, one field per row, nothing scrolls sideways', async ({ page }) => {
        await form(page)

        const details = section(page, 'Dates and terms')
        const label = await box(details.locator('[data-atom-form-section-label]'))
        const fields = await box(details.locator('[data-atom-form-section-body]'))
        const inputs = details.locator('[data-atom-form-section-fields] > *')
        const [a, b] = [await box(inputs.nth(0)), await box(inputs.nth(1))]

        expect(label.bottom).toBeLessThanOrEqual(fields.y)
        expect(Math.abs(label.x - fields.x)).toBeLessThan(1)
        expect(b.y).toBeGreaterThan(a.bottom)
        expect(Math.abs(a.width - fields.width)).toBeLessThan(1)
        expect(await noSideScroll(page)).toBe(true)
        await shot(page, `form-${tw}-390`, { fullPage: true })
      })

      test('the bar spans the screen: the buttons take the full row and the submit grows', async ({ page }) => {
        await form(page)

        const bar = await box(page.locator('[data-atom-save-bar]'))
        const buttons = await box(page.locator('[data-atom-form-actions-buttons]'))
        const submit = await box(page.getByTestId('form-primary'))

        expect(bar.x).toBe(0)
        expect(Math.round(bar.right)).toBe(390)
        expect(Math.abs(buttons.width - (390 - 32))).toBeLessThan(1.5)
        // the submit takes what the other two leave
        expect(submit.width).toBeGreaterThan(120)
        expect(await noSideScroll(page)).toBe(true)
        await expect(page.locator('[data-atom-save-bar]')).toBeInViewport()
        await shot(page, `form-savebar-${tw}-390`)
      })
    })
  })

  test.describe(`the save bar in and out of a page, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1000, height: 700 } })

    test('outside an <atom:page> it bleeds nowhere: inside its parent, no sideways scroll, a 1rem padding', async ({ page }) => {
      await open(page, '/atom/e2e/save-bar-context?mode=bare', { tw })

      const bar = await box(page.locator('[data-atom-save-bar]'))
      const parent = await box(page.getByTestId('box'))
      const s = await styles(page.locator('[data-atom-save-bar]'), ['margin-left', 'margin-right', 'padding-left', 'padding-right'])

      expect(s['margin-left']).toBe('0px')
      expect(s['margin-right']).toBe('0px')
      expect([s['padding-left'], s['padding-right']]).toEqual(['16px', '16px'])
      expect(bar.x).toBeGreaterThanOrEqual(parent.x)
      expect(bar.right).toBeLessThanOrEqual(parent.right)
      expect(await noSideScroll(page)).toBe(true)
    })

    for (const shell of ['sidebar', 'header']) {
      test.describe(`${shell} shell`, () => {
        test.use({ viewport: { width: 1600, height: 700 } })

        // a 1240px default page, a 980px narrow one and a page with no cap: the content follows the page column, the surface follows the screen
        for (const [name, query, columnMax] of [['default', '', 1240], ['narrow', '&width=narrow', 980], ['full', '&width=full', null]]) {
          test(`${name} page: the start slot and the buttons sit on the page column, the surface runs to the screen edge`, async ({ page }) => {
            await open(page, `/atom/e2e/save-bar-context?shell=${shell}${query}`, { tw })

            const pageBox = await box(page.getByTestId('page'))
            const gutter = parseFloat((await styles(page.getByTestId('page'), ['padding-left']))['padding-left'])
            const bar = await box(page.locator('[data-atom-save-bar]'))
            const start = await box(page.getByTestId('start'))
            const buttons = await box(page.locator('[data-atom-form-actions-buttons]'))
            const width = await clientWidth(page)

            if (columnMax) expect(pageBox.width).toBeLessThanOrEqual(columnMax)
            // no cap: the page is as wide as <main> (the whole screen less the sidebar, or the top nav's centred 1248px)
            else expect(Math.abs(pageBox.width - (await box(page.locator('[data-atom-main]'))).width)).toBeLessThan(1)

            expect(Math.abs(bar.x - pageBox.x)).toBeLessThan(1)
            expect(Math.abs(start.x - (pageBox.x + gutter))).toBeLessThan(1)
            expect(Math.abs(buttons.right - (pageBox.right - gutter))).toBeLessThan(1)
            expect(await barAt(page, width - 1)).toBe(true)
            expect(await barAt(page, pageBox.right + 5) || pageBox.right >= width - 6).toBe(true)
            if (shell === 'sidebar') expect(await barAt(page, pageBox.x - 5)).toBe(false)
            expect(await barAt(page, shell === 'sidebar' ? pageBox.x + 2 : 1)).toBe(true)
            expect(await noSideScroll(page)).toBe(true)
          })
        }

        test('no sideways scroll at 1280 and 390, light and dark', async ({ page }) => {
          for (const size of [1280, 390]) {
            await page.setViewportSize({ width: size, height: 700 })

            for (const dark of [false, true]) {
              await open(page, `/atom/e2e/save-bar-context?shell=${shell}`, { tw, dark })

              expect(await noSideScroll(page), `${size}px, ${dark ? 'dark' : 'light'}`).toBe(true)
              expect(await barAt(page, (await clientWidth(page)) - 1), `${size}px, ${dark ? 'dark' : 'light'}: right edge`).toBe(true)
            }
          }
        })
      })
    }

    test('real sideways overflow on a save-bar page still scrolls: nothing is clipped, and the bleed takes no room', async ({ page }) => {
      for (const shell of ['sidebar', 'header']) {
        await open(page, `/atom/e2e/save-bar-context?shell=${shell}&wide=1`, { tw })

        const dims = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth, overflowX: getComputedStyle(document.documentElement).overflowX, bodyOverflowX: getComputedStyle(document.body).overflowX }))

        expect(dims.sw, shell).toBeGreaterThan(dims.cw)
        expect(dims.overflowX, shell).not.toMatch(/clip|hidden/)
        expect(dims.bodyOverflowX, shell).not.toMatch(/clip|hidden/)

        // and it is reachable: a scroll moves the page
        await page.evaluate(() => window.scrollTo(400, 0))
        expect(await page.evaluate(() => window.scrollX), shell).toBeGreaterThan(0)
      }
    })

    test('without that block, the surface adds nothing to the page width, in both shells', async ({ page }) => {
      for (const shell of ['sidebar', 'header']) {
        await open(page, `/atom/e2e/save-bar-context?shell=${shell}`, { tw })

        const dims = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }))

        expect(dims.sw, shell).toBeLessThanOrEqual(dims.cw)
      }
    })

    for (const dark of [false, true]) {
      test(`the surface past the page column is the same colour as inside it, ${dark ? 'dark' : 'light'}`, async ({ page }) => {
        await page.setViewportSize({ width: 1600, height: 700 })

        for (const shell of ['sidebar', 'header']) {
          await open(page, `/atom/e2e/save-bar-context?shell=${shell}&width=narrow`, { tw, dark })

          const bar = await box(page.locator('[data-atom-save-bar]'))
          const width = await clientWidth(page)
          // low in the bar, in its padding, where no text is; the hairline is at the top
          const y = bar.bottom - 5
          const inside = await pixel(page, bar.x + bar.width * 0.4, y)
          const outside = await pixel(page, width - 4, y)
          const beyondLeft = shell === 'header' ? await pixel(page, 4, y) : null

          // the same surface over the same page: within a rounding step per channel
          for (const [name, sample] of [['right', outside], ['left', beyondLeft]]) {
            if (!sample) continue
            for (let i = 0; i < 3; i++) expect(Math.abs(sample[i] - inside[i]), `${shell}, ${name}, channel ${i}: ${sample} against ${inside}`).toBeLessThanOrEqual(2)
          }

          // and the hairline carries on too: the row of pixels at the top of the bar differs from the surface under it, inside and past the column
          const lineInside = await pixel(page, bar.x + bar.width * 0.4, Math.floor(bar.y + 0.1))
          const lineOutside = await pixel(page, width - 4, Math.floor(bar.y + 0.1))
          expect(Math.abs(lineOutside[0] - lineInside[0]) + Math.abs(lineOutside[1] - lineInside[1]) + Math.abs(lineOutside[2] - lineInside[2]), `${shell}: the hairline past the column`).toBeLessThanOrEqual(6)
          expect(Math.abs(lineInside[0] - inside[0]) + Math.abs(lineInside[1] - inside[1]) + Math.abs(lineInside[2] - inside[2]), `${shell}: a hairline you can see`).toBeGreaterThan(3)
        }
      })
    }

    test('the sidebar shell does not paint the surface over the sidebar', async ({ page }) => {
      await page.setViewportSize({ width: 1600, height: 700 })
      await open(page, '/atom/e2e/save-bar-context?shell=sidebar&width=narrow', { tw })

      const bar = await box(page.locator('[data-atom-save-bar]'))
      const y = bar.bottom - 5
      const sidebar = await pixel(page, bar.x - 20, y)
      const beside = await pixel(page, bar.x - 20, bar.y - 40)

      // the sidebar column under the bar is what it is above it: the bar adds nothing there
      expect(sidebar).toEqual(beside)
      expect((await surfaceReach(page)).left).toBeGreaterThanOrEqual(bar.x - 1)
    })

    test('a page that changes its bottom room through the variable keeps the bar flush with the bottom', async ({ page }) => {
      for (const [query, room] of [['mode=page', 120], ['mode=page&pb=80', 80]]) {
        await open(page, `/atom/e2e/save-bar-context?${query}`, { tw })

        expect((await styles(page.getByTestId('page'), ['padding-bottom']))['padding-bottom'], query).toBe(`${room}px`)

        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))

        const bar = await box(page.locator('[data-atom-save-bar]'))
        const doc = await page.evaluate(() => document.documentElement.scrollHeight)

        // at the very end the bar's bottom is the page's bottom, whatever room the page gives it
        expect(Math.abs(bar.bottom - 700), query).toBeLessThan(1.5)
        expect(doc, query).toBeGreaterThan(1400)
      }
    })
  })

  test.describe(`slide-over form, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1280, height: 700 } })

    // a slide-over slides in: measure it once it has stopped
    // (Tailwind 4.0 moves it with `transform`, newer ones with `translate`, so it is its box that is watched, over a few frames)
    const settled = (dialog) => dialog.evaluate((el) => new Promise((resolve) => {
      let last = null
      let same = 0
      const frame = () => {
        const x = el.getBoundingClientRect().x
        same = x === last ? same + 1 : 0
        last = x
        same >= 8 ? resolve() : requestAnimationFrame(frame)
      }
      frame()
    }))

    // the dialog of a slide-over scrolls; the bar is pinned to its foot
    const pinned = async (page, dialog, bar) => {
      await settled(dialog)
      const d = await box(dialog)

      for (const where of ['top', 'middle', 'end']) {
        await dialog.evaluate((el, where) => { el.scrollTop = where === 'top' ? 0 : where === 'end' ? el.scrollHeight : el.scrollHeight / 2 }, where)
        const b = await box(bar)

        expect(Math.abs(b.bottom - d.bottom), where).toBeLessThan(1.5)
        await expect(bar).toBeInViewport({ ratio: 0.99 })
      }
    }

    test('the pattern: stacked sections, the bar stays at the foot while the slide-over scrolls, and the submit runs', async ({ page }) => {
      await open(page, '/atom/e2e/shell-slide-over?open=1', { tw })

      const dialog = page.getByTestId('slide-over')
      await expect(dialog).toBeVisible()
      await expect(dialog).toHaveAttribute('data-open', '')

      expect(await dialog.evaluate((el) => el.scrollHeight > el.clientHeight)).toBe(true)
      await expect(dialog.locator('[data-atom-form-section]')).toHaveCount(3)
      await expect(dialog.locator('[data-atom-form-section][data-layout="stacked"]')).toHaveCount(3)

      // stacked: each heading is above its fields, in a box narrower than the aside breakpoint
      const section = dialog.locator('[data-atom-form-section]').first()
      expect((await box(section.locator('[data-atom-form-section-label]'))).bottom).toBeLessThanOrEqual((await box(section.locator('[data-atom-form-section-body]'))).y)

      const bar = dialog.locator('[data-atom-form-actions]')
      expect((await styles(bar, ['position', 'border-top-width']))).toEqual({ position: 'sticky', 'border-top-width': '1px' })
      await pinned(page, dialog, bar)
      await shot(page, `slide-over-${tw}`)

      await expect(page.getByTestId('slide-over-saved')).toBeHidden()
      await page.getByTestId('slide-over-submit').click()
      await expect(page.getByTestId('slide-over-saved')).toBeVisible()
    })

    test('the dialog is on the surface and the bar covers what scrolls under it', async ({ page }) => {
      await open(page, '/atom/e2e/shell-slide-over?open=1', { tw })

      const dialog = page.getByTestId('slide-over')
      const bar = dialog.locator('[data-atom-form-actions]')
      await settled(dialog)
      const dialogBg = (await styles(dialog, ['background-color']))['background-color']
      const barBg = (await styles(bar, ['background-color']))['background-color']

      expect(barBg).toBe(dialogBg)
      expect(barBg).not.toBe('rgba(0, 0, 0, 0)')
      // it bleeds to the panel's edges: as wide as the dialog, a hairline above it
      const d = await box(dialog)
      const b = await box(bar)
      expect(Math.abs(b.x - (d.x + 1))).toBeLessThan(2.5)
      expect(Math.abs(b.right - d.right)).toBeLessThan(1.5)
    })

    test('form.modal itself: stacked sections, its actions slot and the pinned bar, and a submit that reaches the component', async ({ page }) => {
      await livewire(page, '/atom/e2e/slide-over-form', { tw })

      await page.getByTestId('open').click()

      const dialog = page.locator('dialog[open]')
      await expect(dialog).toBeVisible()
      await expect(dialog.locator('[data-atom-form-section][data-layout="stacked"]')).toHaveCount(5)
      await expect(page.getByTestId('actions-slot')).toBeVisible()

      const bar = dialog.locator('[data-atom-form-actions]')
      await pinned(page, dialog, bar)
      await shot(page, `slide-over-form-modal-${tw}`)

      await dialog.evaluate((el) => { el.scrollTop = 0 })
      await dialog.getByLabel('Name').fill('Customer A')
      await dialog.getByRole('button', { name: 'Save customer' }).click()

      await expect(page.getByTestId('saves')).toHaveText('1')
    })

    test('the fields are one column in a narrow slide-over and two once their own box is 448px wide, whatever the screen', async ({ page }) => {
      // the pattern's slide-over is 448px wide: its fields box is 398px, so one column
      await open(page, '/atom/e2e/shell-slide-over?open=1', { tw })

      const narrow = page.getByTestId('slide-over')
      const rows = async (dialog) => {
        const inputs = dialog.locator('[data-atom-form-section]').first().locator('[data-atom-form-section-fields] > *')
        const [a, b] = [await box(inputs.nth(0)), await box(inputs.nth(1))]

        return { sameRow: Math.abs(a.y - b.y) < 1, fields: (await box(dialog.locator('[data-atom-form-section-body]').first())).width }
      }

      const small = await rows(narrow)
      expect(small.fields).toBeLessThan(448)
      expect(small.sameRow).toBe(false)

      // form.modal cols=1 is max-w-xl (576px): the fields box is 526px, so two across
      await livewire(page, '/atom/e2e/slide-over-form', { tw })
      await page.getByTestId('open').click()

      const wide = await rows(page.locator('dialog[open]'))
      expect(wide.fields).toBeGreaterThanOrEqual(448)
      expect(wide.sameRow).toBe(true)
    })
  })

  test.describe(`timeline, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1000, height: 1300 } })

    const timelines = (page, opts = {}) => livewire(page, '/atom/e2e/timeline', { tw, ...opts })

    test('event: a 9px dot on a 1px spine, the title at 510, the time and actor 12px under it', async ({ page }) => {
      await timelines(page)

      const item = page.getByTestId('events').locator('li').first()
      const dot = await box(item.locator('[data-atom-timeline-dot]'))
      const dotStyle = await styles(item.locator('[data-atom-timeline-dot]'), ['border-top-left-radius', 'background-color'])
      const title = await styles(item.locator('[data-atom-timeline-title]'), ['font-weight', 'font-size'])
      const meta = await styles(item.locator('[data-atom-timeline-meta]'), ['font-size'])
      const spine = await styles(item, ['position'])

      expect([dot.width, dot.height]).toEqual([9, 9])
      expect(parseFloat(dotStyle['border-top-left-radius'])).toBeGreaterThanOrEqual(4.5)
      expect(title['font-weight']).toBe('510')
      expect(meta['font-size']).toBe('12px')
      expect(spine.position).toBe('relative')

      // the spine is drawn between the dots and not under the last one
      const lines = await page.getByTestId('events').locator('li').evaluateAll((els) => els.map((el) => {
        const s = getComputedStyle(el, '::before')
        return { display: s.display, width: s.width }
      }))
      expect(lines[0]).toEqual({ display: 'block', width: '1px' })
      expect(lines[2].display).toBe('none')
      await shot(page, `timeline-${tw}`, { fullPage: true })
    })

    test('event: the detail is folded behind a button that says whether it is open, and a re-render leaves it open', async ({ page }) => {
      await timelines(page)

      const item = page.getByTestId('events').locator('li').first()
      const toggle = item.getByRole('button', { name: 'Show details' })
      const detail = item.locator('[data-atom-timeline-detail]')

      await expect(detail).toBeHidden()
      await expect(toggle).toHaveAttribute('aria-expanded', 'false')

      await toggle.click()
      await expect(detail).toBeVisible()
      await expect(item.getByRole('button', { name: 'Hide details' })).toHaveAttribute('aria-expanded', 'true')

      await page.getByTestId('bump').click()
      await expect(page.getByTestId('bump')).toContainText('(1)')

      await expect(detail).toBeVisible()
      await expect(item.getByRole('button', { name: 'Hide details' })).toBeVisible()

      await item.getByRole('button', { name: 'Hide details' }).click()
      await expect(detail).toBeHidden()
    })

    test('step: a numbered 24px marker, a check for done, a cross for failed, and each state in its own tone', async ({ page }) => {
      await timelines(page)

      const items = page.getByTestId('steps').locator('li')
      const markers = items.locator('[data-atom-timeline-marker]')
      const m = await box(markers.nth(1))
      const radius = (await styles(markers.nth(1), ['border-top-left-radius']))['border-top-left-radius']

      expect([m.width, m.height]).toEqual([24, 24])
      expect(parseFloat(radius)).toBeGreaterThanOrEqual(12)
      // the number is a CSS counter the list resets and every marker bumps (Chromium reports its generated content unresolved)
      expect((await styles(page.getByTestId('steps').locator('ol'), ['counter-reset']))['counter-reset']).toBe('atom-step 0')
      expect((await styles(markers.nth(1), ['counter-increment']))['counter-increment']).toBe('atom-step 1')
      expect(await markers.nth(1).locator('span[aria-hidden]').evaluate((el) => getComputedStyle(el, '::before').content)).toBe('counter(atom-step)')
      expect(await markers.nth(2).locator('span[aria-hidden]').evaluate((el) => getComputedStyle(el, '::before').content)).toBe('counter(atom-step)')
      await expect(markers.nth(0).locator('svg')).toHaveCount(1)
      await expect(markers.nth(3).locator('svg')).toHaveCount(1)

      const colour = async (i) => (await styles(markers.nth(i), ['background-color', 'color']))
      const [done, current, failed] = [await colour(0), await colour(1), await colour(3)]

      expect(done['background-color']).not.toBe(current['background-color'])
      expect(failed['background-color']).not.toBe(done['background-color'])
      expect(await contrastBetween(page, done.color, done['background-color'])).toBeGreaterThanOrEqual(4.5)
      expect(await contrastBetween(page, failed.color, failed['background-color'])).toBeGreaterThanOrEqual(4.5)
      expect(await contrastBetween(page, current.color, current['background-color'])).toBeGreaterThanOrEqual(4.5)
    })

    test('step: a body folds; the current and failed ones start open, the others closed, and a re-render keeps what the user chose', async ({ page }) => {
      await timelines(page)

      const items = page.getByTestId('steps').locator('li')
      const body = (i) => items.nth(i).locator('[data-atom-timeline-body]')
      const toggle = (i) => items.nth(i).locator('[data-atom-timeline-toggle]')

      await expect(body(0)).toBeHidden()
      await expect(body(1)).toBeVisible()
      await expect(body(2)).toBeHidden()
      await expect(body(3)).toBeVisible()
      await expect(toggle(0)).toHaveAttribute('aria-expanded', 'false')
      await expect(toggle(1)).toHaveAttribute('aria-expanded', 'true')

      await toggle(0).click()
      await toggle(1).click()
      await expect(body(0)).toBeVisible()
      await expect(body(1)).toBeHidden()

      await page.getByTestId('bump').click()
      await expect(page.getByTestId('bump')).toContainText('(1)')

      await expect(body(0)).toBeVisible()
      await expect(body(1)).toBeHidden()
      await expect(body(3)).toBeVisible()
    })

    test('step: every chevron sits in the same column at the far end, whether the step has a time or not, and points down closed and up open', async ({ page }) => {
      await timelines(page)

      const items = page.getByTestId('steps').locator('li')
      const chevrons = items.locator('[data-atom-timeline-chevron]')
      const rights = []

      // measure at rest: a chevron half way through its turn has a wider box
      await page.evaluate(() => Promise.all([...document.querySelectorAll('[data-atom-timeline-chevron]')].flatMap((el) => el.getAnimations().map((a) => a.finished))))

      for (let i = 0; i < await chevrons.count(); i++) rights.push((await box(chevrons.nth(i))).right)

      expect(await chevrons.count()).toBe(4)
      expect(Math.max(...rights) - Math.min(...rights)).toBeLessThan(1)

      // the end of the row: the chevron ends where the toggle does
      const toggle = await box(items.nth(2).locator('[data-atom-timeline-toggle]'))
      expect(Math.abs(rights[2] - toggle.right)).toBeLessThan(1)

      // closed (Queued) is not turned, open (Build) is turned half a turn
      const turn = (i) => chevrons.nth(i).evaluate((el) => getComputedStyle(el).rotate !== 'none' ? getComputedStyle(el).rotate : getComputedStyle(el).transform)
      expect(await turn(0)).toMatch(/^(none|matrix\(1, 0, 0, 1, 0, 0\))$/)
      expect(await turn(1)).not.toMatch(/^(none|matrix\(1, 0, 0, 1, 0, 0\))$/)
    })

    test('step: a screen reader hears the state of every step', async ({ page }) => {
      await timelines(page)

      const items = page.getByTestId('steps').locator('li')
      const words = []

      for (let i = 0; i < 4; i++) words.push((await items.nth(i).locator('[data-atom-timeline-marker] .sr-only').textContent()).trim())

      expect(words).toEqual(['Done', 'Current', 'Pending', 'Failed'])
    })

    test('step: the running status pulses only on the current step', async ({ page }) => {
      await timelines(page)

      await expect(page.getByTestId('steps').locator('[data-atom-status][data-running]')).toHaveCount(1)
      await expect(page.getByTestId('steps').locator('li').nth(1).locator('[data-atom-status][data-running]')).toHaveCount(1)
    })

    test('message: the other speaker at the start, yours at the end, at the 14px reading size, with the composer last', async ({ page }) => {
      await timelines(page)

      const list = page.getByTestId('messages')
      const bubbles = list.locator('[data-atom-timeline-bubble]')
      const box0 = await box(bubbles.nth(0))
      const box1 = await box(bubbles.nth(1))
      const listBox = await box(list.locator('ol'))
      const s = await styles(bubbles.nth(0), ['font-size', 'border-top-left-radius', 'padding-top', 'padding-left'])

      expect(s['font-size']).toBe('14px')
      expect(s['border-top-left-radius']).toBe('10px')
      expect([s['padding-top'], s['padding-left']]).toEqual(['10px', '14px'])
      expect(Math.abs(box0.x - listBox.x)).toBeLessThan(1)
      expect(Math.abs(box1.right - listBox.right)).toBeLessThan(1)
      expect(box0.width).toBeLessThanOrEqual(listBox.width * 0.85 + 1)

      // the composer is the last row, under the thread: the field fills the row and the button ends it
      const composer = await box(list.locator('[data-atom-timeline-composer]'))
      expect(composer.y).toBeGreaterThanOrEqual(box1.bottom)

      const field = await box(list.getByTestId('draft'))
      const send = await box(list.getByRole('button', { name: 'Send' }))
      expect(field.width).toBeGreaterThan(composer.width * 0.6)
      expect(Math.abs(send.right - composer.right)).toBeLessThan(1)
      expect(Math.abs(field.right + 8 - send.x)).toBeLessThan(1.5)

      await list.getByTestId('draft').fill('See you then.')
      await list.getByRole('button', { name: 'Send' }).click()

      await expect(bubbles).toHaveCount(3)
      await expect(bubbles.nth(2)).toHaveText('See you then.')
      expect(Math.abs((await box(bubbles.nth(2))).right - listBox.right)).toBeLessThan(1)
    })

    test('a long message wraps inside its bubble and never widens the page', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 900 })
      await timelines(page)

      await page.getByTestId('messages').getByTestId('draft').fill('x'.repeat(300))
      await page.getByTestId('messages').getByRole('button', { name: 'Send' }).click()

      await expect(page.getByTestId('messages').locator('[data-atom-timeline-bubble]')).toHaveCount(3)
      expect(await noSideScroll(page)).toBe(true)
      expect((await box(page.getByTestId('messages').locator('[data-atom-timeline-bubble]').nth(2))).right).toBeLessThanOrEqual(374 + 1)
    })

    test('the three kinds in the dark theme: the dots, markers and bubbles stay readable', async ({ page }) => {
      await timelines(page, { dark: true })

      const canvas = (await styles(page.locator('body'), ['background-color']))['background-color']
      const title = (await styles(page.getByTestId('events').locator('[data-atom-timeline-title]').first(), ['color']))['color']
      const bubble = await styles(page.getByTestId('messages').locator('[data-atom-timeline-bubble]').first(), ['color', 'background-color'])

      expect(await contrastBetween(page, title, canvas)).toBeGreaterThanOrEqual(4.5)
      expect(await contrastBetween(page, bubble.color, bubble['background-color'])).toBeGreaterThanOrEqual(4.5)
      await shot(page, `timeline-${tw}-dark`, { fullPage: true })
    })
  })

  test.describe(`document page, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1280, height: 900 } })

    const doc = (page, query = '', opts = {}) => open(page, `/atom/e2e/shell-document${query ? `?${query}` : ''}`, { tw, ...opts })

    test('the primary follows the state and is the only one', async ({ page }) => {
      for (const [status, word, action] of [['overdue', 'Overdue · 23 days', 'Record payment'], ['draft', 'Draft', 'Send invoice'], ['paid', 'Paid', 'Download receipt']]) {
        await doc(page, `status=${status}`)

        await expect(page.locator('[data-atom-page-header] [data-atom-status]').first()).toHaveText(word)
        await expect(page.getByTestId('document-primary')).toContainText(action)
        await expect(page.locator('[data-atom-button].bg-accent:not(dialog *)')).toHaveCount(1)
      }
    })

    test('the paper is a document: its own warm white and near-black in the dark theme too, weights 500 and 600', async ({ page }) => {
      await doc(page, '', { dark: true })

      const paper = await styles(page.getByTestId('paper'), ['background-color', 'color', 'border-top-left-radius', 'font-size'])
      const totals = page.getByTestId('totals')

      expect(paper['background-color']).toBe('rgb(255, 254, 252)')
      expect(paper.color).toBe('rgb(29, 28, 25)')
      expect(paper['border-top-left-radius']).toBe('6px')
      expect(paper['font-size']).toBe('12.5px')
      expect(await contrastBetween(page, paper.color, paper['background-color'])).toBeGreaterThanOrEqual(7)
      expect((await styles(totals.locator('div').nth(2), ['font-weight', 'font-size']))).toEqual({ 'font-weight': '590', 'font-size': '15px' })
      await shot(page, `document-${tw}-1280-dark`, { fullPage: true })
    })

    test('the lines and the totals are tabular and end on one right edge', async ({ page }) => {
      await doc(page)

      const amounts = page.getByTestId('paper').locator('tbody tr td:last-child')
      const totals = page.getByTestId('totals').locator('dd')
      const edges = []

      for (let i = 0; i < await amounts.count(); i++) edges.push((await textBox(amounts.nth(i))).right)
      for (let i = 0; i < await totals.count(); i++) edges.push((await textBox(totals.nth(i))).right)

      expect(Math.max(...edges) - Math.min(...edges)).toBeLessThan(1.5)
      expect((await styles(page.getByTestId('paper').locator('table'), ['font-variant-numeric']))['font-variant-numeric']).toContain('tabular-nums')
      await shot(page, `document-${tw}-1280`, { fullPage: true })
    })

    test('the rail sits beside the paper from 1080px and under it below', async ({ page }) => {
      await doc(page)
      const beside = [await box(page.getByTestId('paper')), await box(page.getByTestId('balance-box'))]
      expect(beside[1].x).toBeGreaterThanOrEqual(beside[0].right)
      expect(beside[1].width).toBe(320)

      await page.setViewportSize({ width: 900, height: 900 })
      const under = [await box(page.getByTestId('paper')), await box(page.getByTestId('balance-box'))]
      expect(under[1].y).toBeGreaterThanOrEqual(under[0].bottom)
    })

    test('the balance is a 26px figure in danger while overdue, and in success once paid', async ({ page }) => {
      await doc(page)
      const overdue = await styles(page.getByTestId('balance'), ['font-size', 'color', 'font-variant-numeric'])
      expect(overdue['font-size']).toBe('26px')
      expect(overdue['font-variant-numeric']).toContain('tabular-nums')

      await doc(page, 'status=paid')
      const paid = await styles(page.getByTestId('balance'), ['color'])
      expect(paid.color).not.toBe(overdue.color)
    })

    test('the history is a timeline of events, and the page does not scroll sideways at 390px', async ({ page }) => {
      await doc(page)
      await expect(page.getByTestId('history').locator('[data-atom-timeline][data-kind="event"] li')).toHaveCount(4)

      await page.setViewportSize({ width: 390, height: 900 })
      await doc(page)
      expect(await noSideScroll(page)).toBe(true)
      await shot(page, `document-${tw}-390`, { fullPage: true })
    })
  })
}
