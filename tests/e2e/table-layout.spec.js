import { test, expect } from '@playwright/test'

// Real <atom:table> markup in fixed-width boxes (resources/views/e2e/table-layout.blade.php).
// The rig has no Tailwind, so the page carries stand-in rules for the classes the
// components use; what these tests pin is the component's class PLACEMENT and the
// cascade between its defaults and a caller's classes, measured in a browser.

const width = (page, selector) =>
  page.locator(selector).evaluate(el => el.getBoundingClientRect().width)
// the height of the TEXT, not the cell: cells of one row are stretched to match
const textHeight = (page, selector) =>
  page.locator(selector).evaluate(el => {
    const range = document.createRange()
    range.selectNodeContents(el)
    return range.getBoundingClientRect().height
  })

test('the checkbox column stays checkbox-sized under auto layout', async ({ page }) => {
  await page.goto('/atom/e2e/table-layout')

  const th = await width(page, '#box-checkbox th:first-child')
  const td = await width(page, '#box-checkbox tbody td:first-child')

  // w-10 (40px) is a floor the checkbox (20px + the cell's own padding) already
  // exceeds, so the column is the checkbox plus padding and nothing more: it must
  // not stretch to share the 624px box with the text columns.
  expect(th).toBeLessThanOrEqual(50)
  expect(th).toBeGreaterThanOrEqual(40)
  // header and body cells of one column are the same width by definition
  expect(Math.abs(th - td)).toBeLessThan(1)
})

test('a header and a cell are nowrap by default', async ({ page }) => {
  await page.goto('/atom/e2e/table-layout')

  const oneLineHeader = await textHeight(page, '#head-nowrap .grow')
  const oneLineCell = await textHeight(page, '#cell-nowrap')

  // one line of text is one 20px line, never a wrapped block
  expect(oneLineHeader).toBeLessThan(30)
  expect(oneLineCell).toBeLessThan(30)
})

test('a caller whitespace-normal beats the nowrap default and the text wraps', async ({ page }) => {
  await page.goto('/atom/e2e/table-layout')

  const nowrapCell = await textHeight(page, '#cell-nowrap')
  const wrapCell = await textHeight(page, '#cell-wrap')
  const nowrapHead = await textHeight(page, '#head-nowrap .grow')
  const wrapHead = await textHeight(page, '#head-wrap .grow')

  await expect(page.locator('#cell-wrap')).toHaveCSS('white-space', /normal/)
  await expect(page.locator('#head-wrap')).toHaveCSS('white-space', /normal/)

  // wrapping makes the box taller than its nowrap sibling holding the same text
  expect(wrapCell).toBeGreaterThan(nowrapCell + 15)
  expect(wrapHead).toBeGreaterThan(nowrapHead + 15)
})

// firmhive #4: a table with one long value stretched past its box. `truncate` on
// the cell lets the value ellipsise instead; the table is NOT given w-full, which
// would squeeze a wide table's nowrap cells below their content.
const tableWidth = (page, box) => width(page, `#${box} table`)
const boxScrolls = (page, box) =>
  page.locator(`#${box} .overflow-x-auto`).evaluate(el => el.scrollWidth > el.clientWidth + 1)

test('a long value stretches the table past its box without truncate', async ({ page }) => {
  await page.goto('/atom/e2e/table-layout')

  expect(await tableWidth(page, 'box-long-plain')).toBeGreaterThan(624)
  expect(await boxScrolls(page, 'box-long-plain')).toBe(true)
})

test('a truncate cell ellipsises inside the box and the table does not grow', async ({ page }) => {
  await page.goto('/atom/e2e/table-layout')

  expect(await tableWidth(page, 'box-long-truncate')).toBeLessThanOrEqual(624)
  expect(await boxScrolls(page, 'box-long-truncate')).toBe(false)

  // the value is cut, not wrapped: still one line, and its content is wider than the cell
  const cell = page.locator('#box-long-truncate [data-address]')
  await expect(cell).toHaveCSS('text-overflow', 'ellipsis')
  expect(await cell.evaluate(el => el.scrollWidth > el.clientWidth + 1)).toBe(true)
  expect(await textHeight(page, '#box-long-truncate [data-address]')).toBeLessThan(30)

  // the short columns stay readable: no neighbouring cell is cut
  for (const text of ['Jane Tan', '012-345 6789', 'Active']) {
    const neighbour = page.locator('#box-long-truncate tbody td', { hasText: text })
    expect(await neighbour.evaluate(el => el.scrollWidth > el.clientWidth + 1)).toBe(false)
  }
})

test('twelve nowrap columns still scroll, with no cell squeezed below its text', async ({ page }) => {
  await page.goto('/atom/e2e/table-layout')

  expect(await tableWidth(page, 'box-wide')).toBeGreaterThan(624)
  expect(await boxScrolls(page, 'box-wide')).toBe(true)

  const squeezed = await page.locator('#box-wide td').evaluateAll(
    cells => cells.filter(td => td.scrollWidth > td.clientWidth + 1).length
  )
  expect(squeezed).toBe(0)
})
