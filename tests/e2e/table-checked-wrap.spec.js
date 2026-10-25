import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// The table's checked (bulk) bar wraps: the count comes first and the actions follow, on a second line when they do not fit,
// instead of running past the table's right edge and scrolling the page sideways. Three `size="sm"` actions (the fixture's
// `?buttons=3`) at 390px, framed, frameless and tinted, on the consumer's Tailwind (floor and latest); at 1440 the bar is one line.

const bar = (page) => page.locator('[data-atom-table-checked]')

const show = async (page, { tw, width, frameless = false, tone = null, gap = null }) => {
  await page.setViewportSize({ width, height: 900 })
  await open(page, { path: 'table-checked', tw, extra: `&buttons=3${frameless ? '&frameless=1' : ''}${tone ? `&tone=${tone}` : ''}${gap === null ? '' : `&gap=${gap}`}` })
  await expect(bar(page)).toBeVisible()
  await expect(page.getByTestId('bulk-action')).toHaveCount(3)
}

/** What the bar measures, in the page: does it fit, does anything pass the table's edge, and which line each action is on. */
const measure = (page) => bar(page).evaluate((el) => {
  const table = el.closest('[data-atom-table]').getBoundingClientRect()
  const right = (n) => n.getBoundingClientRect().right
  const tops = [...el.querySelectorAll('[data-testid="bulk-action"]')].map((n) => Math.round(n.getBoundingClientRect().top))
  const first = el.firstElementChild.getBoundingClientRect()

  return {
    fits: el.scrollWidth <= el.clientWidth,
    overflow: [...el.querySelectorAll('*')].filter((n) => right(n) > table.right + 0.5).length,
    barRight: el.getBoundingClientRect().right <= table.right + 0.5,
    page: document.documentElement.scrollWidth <= window.innerWidth,
    tops,
    lines: new Set(tops).size,
    countFirst: first.left <= el.getBoundingClientRect().left + 24 && first.top <= Math.min(...tops),
    height: el.getBoundingClientRect().height,
  }
})

for (const tw of ['floor', 'latest']) {
  test.describe(`table checked bar wraps, ${tw} consumer build`, () => {
    for (const [shape, options] of [
      ['framed', {}],
      ['frameless', { frameless: true }],
      ['accent', { tone: 'accent' }],
      ['accent, frameless', { tone: 'accent', frameless: true }],
    ]) {
      test(`${shape}: three small actions wrap inside the table at 390px`, async ({ page }) => {
        await show(page, { tw, width: 390, ...options })

        const m = await measure(page)

        expect(m.fits, 'the bar is not wider than itself (no clipped overflow)').toBe(true)
        expect(m.overflow, 'no element of the bar passes the table\'s right edge').toBe(0)
        expect(m.barRight).toBe(true)
        expect(m.page, 'the page does not scroll sideways').toBe(true)
        expect(m.lines, 'the actions wrap onto a second line').toBeGreaterThanOrEqual(2)
        expect(m.countFirst, 'the count stays first').toBe(true)
      })

      test(`${shape}: at 1440px the bar stays one line`, async ({ page }) => {
        await show(page, { tw, width: 1440, ...options })

        const m = await measure(page)

        expect(m.lines).toBe(1)
        expect(m.fits).toBe(true)
        // one line is one strip: a tinted, padded bar is 56px at the most, the plain one 40px or the strip padding on top
        expect(m.height).toBeLessThan(64)
      })
    }

    test('a tinted bar is one strip when it wraps: the fill covers every line', async ({ page }) => {
      await show(page, { tw, width: 390, tone: 'accent' })

      const fill = await bar(page).evaluate((el) => {
        const box = el.getBoundingClientRect()
        const last = [...el.querySelectorAll('[data-testid="bulk-action"]')].map((n) => n.getBoundingClientRect().bottom).pop()

        return { bg: getComputedStyle(el).backgroundColor, covers: last <= box.bottom + 0.5, radius: parseFloat(getComputedStyle(el).borderTopLeftRadius) }
      })

      expect(fill.bg).not.toBe('rgba(0, 0, 0, 0)')
      expect(fill.covers).toBe(true)
      expect(fill.radius).toBeGreaterThan(0)
    })

    // the gap is atom's `gap-3` unless the host puts a larger one on `<x-slot:checked>`; that wins on both axes. (A smaller one, `gap-0`,
    // does not: both are the same `gap` property and Tailwind writes the smaller value first, as it did before the bar wrapped.)
    for (const [gap, expected] of [[null, 12], ['6', 24]]) {
      test(`the actions' gap is ${expected}px both ways with ${gap === null ? 'no host gap class' : `a host gap-${gap}`}`, async ({ page }) => {
        const gaps = () => page.getByTestId('bulk-action').evaluateAll((all) => {
          const boxes = all.map((n) => n.getBoundingClientRect())

          return {
            column: boxes[1].left - boxes[0].right,
            // the distance between the first line's buttons and a button on the line below, when they wrap
            row: boxes.find((b) => b.top > boxes[0].bottom - 1)?.top - boxes[0].bottom,
          }
        })

        await show(page, { tw, width: 1440, gap })
        expect((await gaps()).column).toBeCloseTo(expected, 0)

        await show(page, { tw, width: 390, gap })
        expect((await gaps()).row).toBeCloseTo(expected, 0)
      })
    }
  })
}
