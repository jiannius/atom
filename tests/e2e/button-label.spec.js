import { test, expect } from '@playwright/test'

// A block button with a long label (a workspace switcher in the sidebar's 228px context slot): the label truncates with an
// ellipsis and the suffix icon stays inside the button. On the consumer Tailwind build (`node tests/consumer/build.mjs` first).

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height }
})

for (const tw of ['floor', 'latest']) {
  test.describe(`button label, ${tw} consumer build`, () => {
    test.beforeEach(async ({ page }) => {
      const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
      expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

      await page.goto(`/atom/e2e/button-label?tw=${tw}`)
      await page.evaluate(() => document.fonts.ready)
    })

    test('a long label truncates with an ellipsis and the suffix icon stays inside the button', async ({ page }) => {
      const button = page.getByTestId('long')
      const label = button.locator('[data-atom-button-label]')

      expect(await label.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true)
      expect(await label.evaluate((el) => getComputedStyle(el).textOverflow)).toBe('ellipsis')

      const b = await box(button)
      const column = await box(page.getByTestId('column'))
      expect(b.width).toBeLessThanOrEqual(column.width)
      expect(b.right).toBeLessThanOrEqual(column.right + 0.5)

      const icon = await box(button.locator('svg').last())
      expect(icon.width).toBeGreaterThan(0)
      expect(icon.right).toBeLessThanOrEqual(b.right)
      expect(icon.left).toBeGreaterThanOrEqual(b.left)
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => window.innerWidth))

      await page.screenshot({ path: `${process.env.B7_SHOTS ?? 'test-results'}/h1-long-${tw}.png`, clip: { x: 0, y: 0, width: 300, height: 400 } })
    })

    test('justify-between puts the label left and the icon at the right edge', async ({ page }) => {
      for (const id of ['between', 'between-long']) {
        const button = page.getByTestId(id)
        const b = await box(button)
        const icon = await box(button.locator('svg').last())
        const label = await box(button.locator('[data-atom-button-label]'))

        // the button has a 1px border and 12px padding: the icon sits against the padding, the label against the other
        expect(b.right - icon.right, id).toBeLessThanOrEqual(14)
        expect(label.left - b.left, id).toBeLessThanOrEqual(14)
      }

      const short = await box(page.getByTestId('between').locator('[data-atom-button-label]'))
      const shortButton = await box(page.getByTestId('between'))
      expect(short.right, 'a short label leaves the gap between it and the icon').toBeLessThan(shortButton.right - 40)
    })

    test('a short label of a block button stays centred, as before', async ({ page }) => {
      const button = page.getByTestId('short')
      const b = await box(button)
      const content = await box(button.locator('[data-atom-button-label]'))
      const icon = await box(button.locator('svg').last())
      const middle = (content.left + icon.right) / 2

      expect(Math.abs(middle - (b.left + b.right) / 2)).toBeLessThanOrEqual(3)
    })

    test('a non-block button shrinks to its column and keeps its icon; a short one is its natural width', async ({ page }) => {
      const long = page.getByTestId('inline-long')
      const column = await box(page.getByTestId('column-inline'))
      const b = await box(long)
      expect(b.right).toBeLessThanOrEqual(column.right + 0.5)
      expect((await box(long.locator('svg').last())).right).toBeLessThanOrEqual(b.right)
      expect(await long.locator('[data-atom-button-label]').evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true)

      const short = await box(page.getByTestId('inline-short'))
      expect(short.width).toBeLessThan(100)
    })

    test('icon-only, icon + label and a slot holding markup keep their shape', async ({ page }) => {
      const iconOnly = await box(page.getByTestId('icon-only'))
      expect(Math.abs(iconOnly.width - iconOnly.height)).toBeLessThanOrEqual(0.5)
      expect(await page.getByTestId('icon-only').locator('[data-atom-button-label]').count()).toBe(0)

      const iconLabel = page.getByTestId('icon-label')
      expect(await iconLabel.locator('[data-atom-button-label]').evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true)

      // markup in the slot is left as flex items, with no label wrapper
      const rich = page.getByTestId('rich')
      expect(await rich.locator('[data-atom-button-label]').count()).toBe(0)
      const badge = await box(rich.locator('[data-atom-badge]').first())
      expect(badge.width).toBeGreaterThan(0)
    })
  })
}
