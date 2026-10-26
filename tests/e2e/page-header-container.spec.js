import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// page.header: where the actions go is decided by the header's own width (a container query at 48rem, 768px), never by how long the
// title or the meta line is. At or above it the actions sit beside the title block, bottom-aligned; below it they are on a row of their
// own under it, at the start, whatever the content. On the consumer's Tailwind, floor (4.0.7) and latest.

const THRESHOLD = 768
const rect = (locator) => locator.evaluate((el) => { const r = el.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width } })

for (const tw of ['floor', 'latest']) {
  test.describe(`page.header container, ${tw} consumer build`, () => {
    for (const width of [480, 700, 767, 768, 800, 1100]) {
      for (const kind of ['short', 'long']) {
        const beside = width >= THRESHOLD

        test(`a ${width}px header with a ${kind} meta line: the actions are ${beside ? 'beside the title block' : 'on their own row below'}`, async ({ page }) => {
          await page.setViewportSize({ width: 1300, height: 900 })
          await open(page, { path: 'page-header-container', tw })

          const box = page.getByTestId(`box-${width}-${kind}`)
          const header = await rect(box.locator('[data-atom-page-header]'))
          const block = await rect(box.locator('[data-atom-page-header] > div').first())
          const actions = await rect(box.locator('[data-atom-page-actions]'))

          expect(header.width).toBe(width)

          if (beside) {
            // one row: the actions are to the right of the title block, which wraps inside its own column; bottoms line up
            expect(actions.left).toBeGreaterThanOrEqual(block.right - 1)
            expect(Math.abs(actions.bottom - block.bottom)).toBeLessThan(2)
            expect(actions.right).toBeLessThanOrEqual(header.right + 1)
          }
          else {
            // a row of their own: under the title block, at the start, however short the content is
            expect(actions.top).toBeGreaterThanOrEqual(block.bottom - 1)
            expect(Math.abs(actions.left - header.left)).toBeLessThan(2)
          }

          // nothing overflows its header sideways, long meta line or not
          expect(await box.locator('[data-atom-page-header]').evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true)
        })
      }
    }

    test('a header\'s own width is what counts, not the screen\'s: 700px in a 1300px window stacks, 800px in a 1000px window sits beside', async ({ page }) => {
      await page.setViewportSize({ width: 1000, height: 900 })
      await open(page, { path: 'page-header-container', tw })

      const narrow = page.getByTestId('box-700-short')
      const wide = page.getByTestId('box-800-short')

      expect((await rect(narrow.locator('[data-atom-page-actions]'))).top).toBeGreaterThanOrEqual((await rect(narrow.locator('[data-atom-page-header] > div').first())).bottom - 1)
      expect((await rect(wide.locator('[data-atom-page-actions]'))).left).toBeGreaterThanOrEqual((await rect(wide.locator('[data-atom-page-header] > div').first())).right - 1)
    })

    test('size="sm" keeps the old rule: the actions share the row when they fit', async ({ page }) => {
      await page.setViewportSize({ width: 1300, height: 900 })
      await open(page, { path: 'page-header-container', tw })

      const box = page.getByTestId('box-small')
      const block = await rect(box.locator('[data-atom-page-header] > div').first())
      const actions = await rect(box.locator('[data-atom-page-actions]'))

      expect(actions.left).toBeGreaterThanOrEqual(block.right - 1)
      expect(await box.locator('[data-atom-page-header]').evaluate((el) => el.classList.contains('@container'))).toBe(false)
    })
  })
}
