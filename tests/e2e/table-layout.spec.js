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
