import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// A slide-over's pinned bar (form.actions sticky) sits at the foot of the panel whatever the content's height: with short content it is
// at the bottom, not mid-panel above an empty block; with long content it still sticks while the panel scrolls. Both themes, floor and
// latest, on a desktop and a phone.

const settle = (locator) => locator.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished.catch(() => null))))

for (const tw of ['floor', 'latest']) {
  test.describe(`slide-over footer, ${tw} consumer build`, () => {
    for (const dark of [false, true]) {
      for (const width of [1440, 390]) {
        test(`short content: the bar is at the bottom of the panel, ${dark ? 'dark' : 'light'} at ${width}px`, async ({ page }) => {
          await page.setViewportSize({ width, height: 800 })
          await open(page, { path: 'slide-over-short', tw, dark })

          const dialog = page.getByTestId('slide-over')
          await expect(dialog).toHaveAttribute('open', '')
          await settle(dialog)

          const panel = await dialog.boundingBox()
          const bar = await dialog.locator('[data-atom-form-actions]').boundingBox()
          const field = await dialog.getByLabel('Subject').boundingBox()

          // the panel is the whole height, and the bar's bottom edge is the panel's (the 1px border aside)
          expect(Math.round(panel.height)).toBe(800)
          expect(Math.abs((panel.y + panel.height) - (bar.y + bar.height))).toBeLessThanOrEqual(2)
          // and it is well below the content, not right under it
          expect(bar.y).toBeGreaterThan(field.y + field.height + 100)
        })

        test(`long content: the bar still sticks to the foot while the panel scrolls, ${dark ? 'dark' : 'light'} at ${width}px`, async ({ page }) => {
          await page.setViewportSize({ width, height: 800 })
          await open(page, { path: 'slide-over-short', tw, dark, extra: '&long=1' })

          const dialog = page.getByTestId('slide-over')
          await expect(dialog).toHaveAttribute('open', '')
          await settle(dialog)

          const barBottom = async () => { const b = await dialog.locator('[data-atom-form-actions]').boundingBox(); return b.y + b.height }

          expect(await dialog.evaluate((el) => el.scrollHeight > el.clientHeight)).toBe(true)
          expect(Math.abs(await barBottom() - 800)).toBeLessThanOrEqual(2)

          await dialog.evaluate((el) => { el.scrollTop = el.scrollHeight })
          expect(Math.abs(await barBottom() - 800)).toBeLessThanOrEqual(2)

          await dialog.evaluate((el) => { el.scrollTop = 0 })
          expect(Math.abs(await barBottom() - 800)).toBeLessThanOrEqual(2)
        })
      }
    }

    test('a closed slide-over is not displayed (the flex column applies only while it is open)', async ({ page }) => {
      await open(page, { path: 'slide-over-short', tw })
      await page.getByTestId('slide-over').evaluate((el) => el.close())

      expect(await page.getByTestId('slide-over').evaluate((el) => getComputedStyle(el).display)).toBe('none')
    })
  })
}
