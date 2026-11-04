import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// page.header: the title block has a fixed flex basis (22rem) and the actions do not shrink, so the actions sit beside the title block
// when both fit the header's own width and drop to a row of their own below it, at the start, when they do not. What decides it is the
// width of the header and of the actions, never how long the title or the meta line is. On the consumer's Tailwind, floor (4.0.7) and latest.
// No text width is asserted (CI draws a wider font than a Mac): whether a box "fits" is computed from the actions' measured width, and a
// box within MARGIN of the edge is not asserted.

const BASIS = 22 * 16
const MARGIN = 12
const rect = (locator) => locator.evaluate((el) => { const r = el.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width } })

/** Where the actions are in a box, and whether the title block and they fit the header's width side by side. */
async function measure(page, testid) {
  const box = page.getByTestId(testid)
  const header = await rect(box.locator('[data-atom-page-header]'))
  const block = await rect(box.locator('[data-atom-page-header] > div').first())
  const actions = await rect(box.locator('[data-atom-page-actions]'))
  const gap = await box.locator('[data-atom-page-header]').evaluate((el) => parseFloat(getComputedStyle(el).columnGap))

  return { header, block, actions, beside: actions.left >= block.right - 1, room: header.width - (BASIS + gap + actions.width) }
}

for (const tw of ['floor', 'latest']) {
  test.describe(`page.header actions, ${tw} consumer build`, () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 1300, height: 900 })
      await open(page, { path: 'page-header-container', tw })
    })

    for (const width of [480, 700, 767, 768, 800, 1100]) {
      for (const kind of ['short', 'long']) {
        test(`a ${width}px header with a ${kind} meta line: beside the title when the title basis and the actions fit, below it when they do not`, async ({ page }) => {
          const m = await measure(page, `box-${width}-${kind}`)

          expect(m.header.width).toBe(width)

          if (m.room >= MARGIN) {
            // one row: the actions are to the right of the title block, which wraps inside its own column; bottoms line up
            expect(m.beside, `room ${m.room}`).toBe(true)
            expect(Math.abs(m.actions.bottom - m.block.bottom)).toBeLessThan(2)
            expect(m.actions.right).toBeLessThanOrEqual(m.header.right + 1)
          }
          else if (m.room <= -MARGIN) {
            // a row of their own: under the title block, at the start
            expect(m.beside, `room ${m.room}`).toBe(false)
            expect(m.actions.top).toBeGreaterThanOrEqual(m.block.bottom - 1)
            expect(Math.abs(m.actions.left - m.header.left)).toBeLessThan(2)
          }

          // nothing overflows its header sideways, long meta line or not
          expect(await page.getByTestId(`box-${width}-${kind}`).locator('[data-atom-page-header]').evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true)
        })
      }
    }

    test('the same header lays out alike with a short and a long meta line, at every width', async ({ page }) => {
      for (const width of [480, 700, 767, 768, 800, 1100]) {
        const short = await measure(page, `box-${width}-short`)
        const long = await measure(page, `box-${width}-long`)

        expect(long.beside, `${width}px`).toBe(short.beside)
      }
    })

    test('480px with three controls stacks and 1100px sits beside, on any font', async ({ page }) => {
      expect((await measure(page, 'box-480-short')).beside).toBe(false)
      expect((await measure(page, 'box-1100-short')).beside).toBe(true)
    })

    test('how much the actions need decides: one small action sits beside the title at 480px, five do not fit at 800px', async ({ page }) => {
      const one = await measure(page, 'box-480-one')
      const five = await measure(page, 'box-800-five')

      expect(one.beside).toBe(true)
      expect(five.beside).toBe(false)
      expect(five.actions.top).toBeGreaterThanOrEqual(five.block.bottom - 1)
      expect(Math.abs(five.actions.left - five.header.left)).toBeLessThan(2)
    })

    test('a header\'s own width is what counts, not the screen\'s: the same box lays out the same in a narrower window', async ({ page }) => {
      const before = await measure(page, 'box-800-short')
      await page.setViewportSize({ width: 1000, height: 900 })
      const after = await measure(page, 'box-800-short')

      expect(after.beside).toBe(before.beside)
    })

    test('size="sm" keeps the old rule: the actions share the row when they fit', async ({ page }) => {
      const box = page.getByTestId('box-small')
      const block = await rect(box.locator('[data-atom-page-header] > div').first())
      const actions = await rect(box.locator('[data-atom-page-actions]'))

      expect(actions.left).toBeGreaterThanOrEqual(block.right - 1)
    })

    test('the header is no longer a size container: it takes its width from its content like any flex row', async ({ page }) => {
      expect(await page.getByTestId('box-800-short').locator('[data-atom-page-header]').evaluate((el) => getComputedStyle(el).containerType)).toBe('normal')
    })
  })
}
