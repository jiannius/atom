import { test, expect } from '@playwright/test'

// The toolbar gives <atom:table.search> 240px from `sm` and the full row below it, at zero specificity (`:where()`), so a width class on the
// search wins (#208). It has to land on the element that carries data-atom-table-search: `sm:w-[28rem]` on the tag is that box's width.
// The page is resources/views/e2e/table-search-width.blade.php, on the consumer's Tailwind, for both builds (floor, latest).

const search = (page, testid) => page.getByTestId(testid).locator('[data-atom-table-search]')
const width = async (locator) => Math.round((await locator.boundingBox()).width)
const toolbar = async (page, testid) => Math.round((await page.getByTestId(testid).locator('[data-atom-table-toolbar-start]').boundingBox()).width)

for (const tw of ['floor', 'latest']) {
  test.describe(`on the ${tw} build`, () => {
    test.beforeEach(async ({ page }) => {
      await page.goto(`/atom/e2e/table-search-width?tw=${tw}`)
    })

    test('the width rules compile to a zero-specificity selector', async ({ page }) => {
      const css = await (await page.request.get(`/atom/e2e/consumer-css/${tw}`)).text()

      // floor writes the rule nested (`.x { :where(& [data-atom-table-search]) {..} }`), latest flattens it (`:where(.x [data-atom-table-search])`):
      // either way nothing but :where() holds the search, so the selector adds no specificity of its own
      expect(css).toMatch(/:where\(&[ _]\[data-atom-table-search\]\)|:where\(\.\\\[\\:where\\\(\\&_\\\[data-atom-table-search\\\]\\\)\\\]\\:w-full \[data-atom-table-search\]\)/)
      expect(css).not.toMatch(/\.\\\[\\&_\\\[data-atom-table-search\\\]\\\]\\:w-full/)
    })

    test('a default search is 240px from sm up and the full row below it', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 700 })
      expect(await width(search(page, 'default'))).toBe(240)

      await page.setViewportSize({ width: 639, height: 700 })
      const row = await toolbar(page, 'default')
      expect(await width(search(page, 'default'))).toBe(row)
      expect(row).toBeGreaterThan(240)
    })

    test('a width class on the search wins from sm up, and the default still holds below it', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 700 })
      expect(await width(search(page, 'wide'))).toBe(448)

      await page.setViewportSize({ width: 500, height: 700 })
      expect(await width(search(page, 'wide'))).toBe(await toolbar(page, 'wide'))
    })

    test('a base width class on the search is its width at every size', async ({ page }) => {
      for (const viewport of [1024, 500]) {
        await page.setViewportSize({ width: viewport, height: 700 })
        expect(await width(search(page, 'base'))).toBe(288)
      }
    })

    test('only layout classes go to the root: a background, border or font class stays on the input and still draws there', async ({ page }) => {
      await page.setViewportSize({ width: 1024, height: 700 })
      const input = search(page, 'mixed').locator('input')

      await expect(search(page, 'mixed')).toHaveClass(/sm:w-\[28rem\]/)
      await expect(search(page, 'mixed')).not.toHaveClass(/bg-red-100|border-red-500|font-mono/)
      await expect(input).toHaveClass(/font-mono/)
      await expect(input).toHaveClass(/bg-red-100/)
      await expect(input).toHaveClass(/border-red-500/)
      await expect(input).not.toHaveClass(/sm:w-\[28rem\]/)
      expect(await width(search(page, 'mixed'))).toBe(448)
      expect(await input.evaluate((el) => getComputedStyle(el).fontFamily)).toMatch(/mono/i)
    })

    test('the class sits on the search root, not on the input', async ({ page }) => {
      await expect(search(page, 'wide')).toHaveClass(/sm:w-\[28rem\]/)
      await expect(search(page, 'wide').locator('input')).not.toHaveClass(/sm:w-\[28rem\]/)
    })
  })
}
