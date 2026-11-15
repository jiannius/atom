import { test, expect } from '@playwright/test'
import { open, contrastOf } from './controls-helpers.js'

// <atom:table.totals> (resources/views/e2e/table-totals.blade.php): lines of figures in a table's own tfoot, on the consumer's Tailwind.
// The figure must end on the same edge as the text of the `numeric` last column above it, and keep doing so when the user hides the columns
// to its left: the cell spans more columns than the table has, so it covers whatever is drawn. Measured, not assumed.

const SLACK = 1

const openTotals = (page, options = {}) => open(page, { path: 'table-totals', ...options })

const hide = (page, ...names) => page.evaluate((names) => names.forEach((name) => window.atom.tableColumns.hide(document.querySelector('#lines'), name)), names)
const show = (page, ...names) => page.evaluate((names) => names.forEach((name) => window.atom.tableColumns.show(document.querySelector('#lines'), name)), names)

/**
 * Where the text of an element ends, and where its box does: a range over its contents, so padding and the cell's own width don't count.
 * The missing-value mark's dash is what is drawn (its "Not set" is sr-only, an absolutely positioned box that a range would count).
 */
const edges = (page, selector) => page.locator(selector).evaluateAll((els) => els.map((el) => {
  const range = document.createRange()
  range.selectNodeContents(el.querySelector('[data-atom-missing] > [aria-hidden]') ?? el)
  const text = range.getBoundingClientRect()
  const box = el.getBoundingClientRect()

  return { textRight: text.right, textLeft: text.left, left: box.left, right: box.right, width: box.width }
}))

const lastCell = (page, id) => edges(page, `#${id} tbody tr:last-child td:last-child`).then((all) => all[0])

/** Every figure of a table ends where the last cell above them does. */
async function expectAligned(page, id, label = '') {
  const above = await lastCell(page, id)
  const figures = await edges(page, `#${id} [data-atom-table-totals]:not([style*="none"]) [data-atom-totals-value]`)

  expect(figures.length, `${label} figures drawn`).toBeGreaterThan(0)
  figures.forEach((figure, i) => expect(Math.abs(figure.textRight - above.textRight), `${label} figure ${i}: ${figure.textRight} against ${above.textRight}`).toBeLessThanOrEqual(SLACK))
}

for (const tw of ['floor', 'latest']) {
  for (const density of ['compact', 'comfortable']) {
    test.describe(`${tw}, ${density}`, () => {
      test('every figure ends where the text of the amount column does', async ({ page }) => {
        await openTotals(page, { tw, density })

        await expectAligned(page, 'lines', 'lines')
        await expectAligned(page, 'framed-lines', 'frameless')
        await expectAligned(page, 'plain', 'plain')
      })

      test('it stays aligned as the user hides the columns to its left, and the cell spans only what is drawn', async ({ page }) => {
        await openTotals(page, { tw, density })

        for (const hidden of [['qty'], ['price', 'tax'], ['qty', 'price', 'tax']]) {
          await show(page, 'qty', 'price', 'tax')
          await hide(page, ...hidden)

          await expectAligned(page, 'lines', `hidden ${hidden}`)

          // the cell covers the table and the table has no column of its own to spare: the header's cells end where the line does
          const cell = (await edges(page, '#lines [data-atom-table-totals] td'))[0]
          const heads = (await edges(page, '#lines thead th')).filter((th) => th.width > 0)
          const table = (await edges(page, '#lines table'))[0]

          expect(heads.length, `columns drawn after hiding ${hidden}`).toBe(5 - hidden.length)
          expect(Math.abs(cell.left - heads[0].left)).toBeLessThanOrEqual(SLACK)
          expect(Math.abs(cell.right - heads.at(-1).right), 'no phantom column to the right').toBeLessThanOrEqual(SLACK)
          expect(Math.abs(cell.right - table.right)).toBeLessThanOrEqual(SLACK)
        }
      })

      test('hiding a column to its left does not move the columns that stay or widen the table past its box', async ({ page }) => {
        await openTotals(page, { tw, density })

        const before = await page.locator('#lines table').evaluate((t) => t.getBoundingClientRect().width)
        await hide(page, 'qty', 'price')
        const after = await page.locator('#lines table').evaluate((t) => t.getBoundingClientRect().width)
        const box = await page.locator('#lines [data-atom-table-scroll]').evaluate((el) => el.clientWidth)

        expect(after).toBeLessThanOrEqual(box + SLACK)
        expect(after).toBeCloseTo(before, 0)
      })
    })
  }
}

test('the lines follow the amount column: hide it and they go, show it and they are back and aligned', async ({ page }) => {
  await openTotals(page)

  await expect(page.locator('#lines [data-atom-table-totals] td').first()).toBeVisible()

  await hide(page, 'amount')
  await expect(page.locator('#lines [data-atom-table-totals] td').first()).toBeHidden()
  await expect(page.locator('#lines [data-atom-table-totals] td').nth(2)).toBeHidden()

  await show(page, 'amount')
  await expect(page.locator('#lines [data-atom-table-totals] td').first()).toBeVisible()
  await expectAligned(page, 'lines', 'shown again')
})

test.describe('on a phone', () => {
  test.use({ viewport: { width: 375, height: 800 } })

  test('the wide table scrolls under its lines, and they still end where the amount column does', async ({ page }) => {
    await openTotals(page)

    const scroll = page.locator('#lines [data-atom-table-scroll]')
    const [scrollWidth, clientWidth] = await scroll.evaluate((el) => [el.scrollWidth, el.clientWidth])

    expect(scrollWidth, 'the table is wider than its box').toBeGreaterThan(clientWidth)
    await expectAligned(page, 'lines', 'not scrolled')

    await scroll.evaluate((el) => { el.scrollLeft = el.scrollWidth })
    await expectAligned(page, 'lines', 'scrolled to the end')

    // at the end of the scroll the figures are inside the box, not past its edge
    const box = (await edges(page, '#lines [data-atom-table-scroll]'))[0]
    const figures = await edges(page, '#lines [data-atom-totals-value]')

    figures.forEach((figure) => {
      expect(figure.right).toBeLessThanOrEqual(box.right + SLACK)
      expect(figure.left).toBeGreaterThanOrEqual(box.left - SLACK)
    })
  })

  test('hiding columns on a phone brings the figures into view and keeps them aligned', async ({ page }) => {
    await openTotals(page)
    await hide(page, 'qty', 'price', 'tax')

    const [scrollWidth, clientWidth] = await page.locator('#lines [data-atom-table-scroll]').evaluate((el) => [el.scrollWidth, el.clientWidth])

    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + SLACK)
    await expectAligned(page, 'lines', 'three hidden')
  })

  test('a long label wraps inside its cell and does not make the table scroll', async ({ page }) => {
    await openTotals(page)

    const [scrollWidth, clientWidth] = await page.locator('#plain [data-atom-table-scroll]').evaluate((el) => [el.scrollWidth, el.clientWidth])
    const label = page.locator('#plain [data-atom-totals-label]').last()

    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + SLACK)
    expect(await label.evaluate((el) => el.getClientRects().length && el.getBoundingClientRect().height)).toBeGreaterThan(30)
    await expectAligned(page, 'plain', 'long label')
  })
})

test('the labels of one block end on one edge, whatever width their figures are', async ({ page }) => {
  await openTotals(page)

  const labels = await edges(page, '#lines [data-atom-totals-label]')
  const figures = await edges(page, '#lines [data-atom-totals-value]')

  expect(new Set(figures.map((figure) => Math.round(figure.textLeft))).size, 'the figures are not all the same width').toBeGreaterThan(1)
  labels.forEach((label) => expect(Math.abs(label.textRight - labels[0].textRight)).toBeLessThanOrEqual(SLACK))
})

test('a blank figure draws the muted mark on the same edge, and 0 is a figure', async ({ page }) => {
  await openTotals(page)

  const lines = page.locator('#framed-lines [data-atom-table-totals]')
  const mark = lines.nth(0).locator('[data-atom-missing]')

  await expect(mark).toBeVisible()
  await expect(lines.nth(0).locator('[data-atom-totals-value]')).toContainText('Not set')
  await expect(lines.nth(1).locator('[data-atom-totals-value]')).toHaveText('0')
  await expect(lines.nth(1).locator('[data-atom-missing]')).toHaveCount(0)

  await expectAligned(page, 'framed-lines', 'blank and zero')
})

test('the figures are tabular and right-aligned, the label is muted, and the grand total is semibold under a hairline', async ({ page }) => {
  await openTotals(page)

  const style = (selector, props) => page.locator(selector).first().evaluate((el, props) => {
    const s = getComputedStyle(el)

    return Object.fromEntries(props.map((p) => [p, s.getPropertyValue(p)]))
  }, props)

  const lines = '#lines [data-atom-table-totals]'
  const figure = await style(`${lines}:nth-child(1) [data-atom-totals-value]`, ['text-align', 'font-variant-numeric', 'font-weight'])
  const label = await style(`${lines}:nth-child(1) [data-atom-totals-label]`, ['color', 'text-align'])
  const plainCell = await style(`${lines}:nth-child(1) td`, ['font-weight', 'color', 'border-top-width'])
  const totalCell = await style(`${lines}[data-atom-table-totals-total] td`, ['font-weight', 'color', 'border-top-width'])

  expect(figure['text-align']).toBe('right')
  expect(figure['font-variant-numeric']).toContain('tabular-nums')
  expect(label['text-align']).toBe('right')
  expect(label.color, 'a muted label').not.toBe(plainCell.color)
  expect(Number(plainCell['font-weight'])).toBeLessThan(590)
  expect(totalCell['font-weight']).toBe('590')
  expect(parseFloat(totalCell['border-top-width'])).toBeGreaterThanOrEqual(1)
  expect(parseFloat(plainCell['border-top-width'])).toBe(0)
})

for (const dark of [false, true]) {
  test(`the label and the figure are read at 4.5:1${dark ? ' in dark mode' : ''}`, async ({ page }) => {
    await openTotals(page, { dark })

    for (const selector of ['#lines [data-atom-totals-label]', '#lines [data-atom-totals-value]', '#lines [data-atom-table-totals-total] [data-atom-totals-label]', '#framed-lines [data-atom-missing]']) {
      const found = await page.locator(selector).first().evaluate(contrastOf, { what: 'text' })

      // the mark is decoration (aria-hidden, with an sr-only text), so it is held to the 3:1 of a graphic
      expect(found.ratio, `${selector}: ${found.colour} on ${found.back}`).toBeGreaterThanOrEqual(selector.includes('missing') ? 3 : 4.5)
    }
  })
}

test('the totals print once, not at the foot of every page: in print the footer is an ordinary row group', async ({ page }) => {
  await openTotals(page)

  const display = () => page.locator('#lines tfoot').evaluate((el) => getComputedStyle(el).display)

  // a table-footer-group is repeated on every printed page (a 6-page list printed its totals 6 times: checked with page.pdf())
  expect(await display()).toBe('table-footer-group')

  await page.emulateMedia({ media: 'print' })
  expect(await display()).toBe('table-row-group')
  await expectAligned(page, 'lines', 'in print')

  await page.emulateMedia({ media: 'screen' })
  expect(await display()).toBe('table-footer-group')
})

test('table-layout: fixed with a width is the known limit: the spare columns of the line take the width and every real column collapses', async ({ page }) => {
  await openTotals(page)

  const widths = (id) => page.locator(`#${id} thead th`).evaluateAll((ths) => ths.map((th) => th.getBoundingClientRect().width))
  const layout = (id) => page.locator(`#${id} table`).evaluate((el) => getComputedStyle(el).tableLayout)

  // the same fixed-layout table, with and without a line: the line is the only difference
  expect(await layout('fixed-bare')).toBe('fixed')
  expect(await layout('fixed-with')).toBe('fixed')

  const bare = await widths('fixed-bare')
  const withLine = await widths('fixed-with')

  expect(bare.every((width) => width > 100), `a fixed table without the line shares its width: ${bare}`).toBe(true)
  expect(withLine.every((width) => width < 30), `with the line the columns are down to their padding: ${withLine}`).toBe(true)
})

test('when the amount column is hidden and the footer has nothing else, the divider above it goes too, so a panel keeps one hairline', async ({ page }) => {
  await openTotals(page)

  const divider = () => page.locator('#lines tbody').evaluate((el) => getComputedStyle(el).borderBottomWidth)
  const footer = () => page.locator('#lines tfoot').evaluate((el) => el.getBoundingClientRect().height)

  expect(await divider()).toBe('1px')

  // another column going leaves the lines drawn, and the divider with them
  await hide(page, 'qty')
  expect(await divider()).toBe('1px')

  await hide(page, 'amount')
  expect(await footer()).toBe(0)
  expect(await divider()).toBe('0px')

  await show(page, 'amount')
  expect(await divider()).toBe('1px')
})

test.describe('on a Livewire table', () => {
  const lines = '#lines [data-atom-table-totals]'

  test('hidden totals stay hidden through a re-render, and come back aligned with what the server printed', async ({ page }) => {
    await page.goto('/atom/e2e/table-totals-livewire')
    await page.waitForLoadState('networkidle')
    await hide(page, 'amount')
    await expect(page.locator(lines).first().locator('td')).toBeHidden()

    const round = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST')
    await page.locator('[data-bump]').click()
    await round
    await page.evaluate(() => new Promise((done) => setTimeout(done, 100)))

    await expect(page.locator(lines).first().locator('td')).toBeHidden()
    await expect(page.locator(lines).nth(1).locator('td')).toBeHidden()
    expect(await page.locator('#lines tbody').evaluate((el) => getComputedStyle(el).borderBottomWidth)).toBe('0px')

    await show(page, 'amount')
    await expect(page.locator(lines).first().locator('[data-atom-totals-value]')).toHaveText('1,001.00')
    await expect(page.locator(lines).nth(1).locator('[data-atom-totals-value]')).toHaveText('1,081.00')
    await expectAligned(page, 'lines', 'after the re-render')
  })

  test('a column hidden before the re-render keeps the lines aligned, and the lines still follow', async ({ page }) => {
    await page.goto('/atom/e2e/table-totals-livewire')
    await page.waitForLoadState('networkidle')
    await hide(page, 'qty')

    const round = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST')
    await page.locator('[data-bump]').click()
    await round
    await page.evaluate(() => new Promise((done) => setTimeout(done, 100)))

    await expectAligned(page, 'lines', 'qty hidden, re-rendered')
  })
})

test('a column after the amount (the row\'s ⋯ cell) is the known breaker: the figure ends at the table\'s edge, past the amount, by that column\'s width', async ({ page }) => {
  await openTotals(page)

  const [figure] = await edges(page, '#actions [data-atom-totals-value]')
  const amount = (await edges(page, '#actions tbody tr:last-child td:nth-child(2)'))[0]
  const [actions] = (await edges(page, '#actions thead th')).slice(-1)

  // the width of that column is the engine's (48px in Chromium and 40px in WebKit here), so the test reads it instead of pinning it
  expect(actions.width).toBeGreaterThan(30)
  expect(figure.textRight - amount.textRight, 'past the amount by the actions column').toBeGreaterThan(actions.width - SLACK)
  expect(Math.abs(figure.textRight - amount.textRight - actions.width)).toBeLessThanOrEqual(SLACK)
})
