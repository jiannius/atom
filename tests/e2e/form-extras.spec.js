import { test, expect } from '@playwright/test'
import { open, contrastOf } from './controls-helpers.js'
import { sweepText, ACCENTS, TEXT, GRAPHIC } from './display-helpers.js'

// The danger section (`form.section variant="danger"`) and the cleared-queue empty state (`empty kind="done"`), on the consumer's
// Tailwind, floor and latest: each text role on its own surface, the icon at 3:1, in both themes, for the default accent and three
// host accents.

for (const tw of ['floor', 'latest']) {
  test.describe(`form extras, ${tw} consumer build`, () => {
    for (const dark of [false, true]) {
      for (const accent of [null, ...ACCENTS]) {
        test(`the danger section and the done state read in ${dark ? 'dark' : 'light'} with ${accent ?? 'the default accent'}`, async ({ page }) => {
          await open(page, { path: 'form-extras', tw, dark, accent })

          const heading = page.getByTestId('danger-section').locator('[data-atom-form-section-heading]')
          const found = await heading.evaluate(contrastOf, { what: 'text' })
          expect(found.ratio, `the danger heading: ${found.colour} on ${found.back}`).toBeGreaterThanOrEqual(TEXT)

          expect(await sweepText(page, '[data-testid="danger-section"]', TEXT, 3)).toEqual([])
          expect(await sweepText(page, '[data-testid="empty-done"], [data-testid="empty-done-sm"]', TEXT, 2)).toEqual([])

          for (const id of ['empty-done', 'empty-done-sm']) {
            const icon = await page.getByTestId(id).locator('svg').first().evaluate(contrastOf, { what: 'svg-stroke' })
            expect(icon.ratio, `${id} icon: ${icon.colour} on ${icon.back}`).toBeGreaterThanOrEqual(GRAPHIC)
          }
        })
      }
    }

    test('the danger section is boxed with a danger hairline and its heading is the danger ink', async ({ page }) => {
      await open(page, { path: 'form-extras', tw })

      const body = page.getByTestId('danger-section').locator('[data-atom-form-section-body]')
      const borders = await body.evaluate((el) => { const s = getComputedStyle(el); return [s.borderTopWidth, s.borderTopStyle, s.borderTopLeftRadius] })

      expect(borders).toEqual(['1px', 'solid', '10px'])
      expect(await page.getByTestId('danger-section').locator('[data-atom-form-section-heading]').evaluate((el) => getComputedStyle(el).color))
        .toBe(await page.evaluate(() => { const i = document.createElement('i'); i.style.color = 'var(--atom-danger-fg)'; document.body.append(i); const c = getComputedStyle(i).color; i.remove(); return c }))
    })
  })
}
