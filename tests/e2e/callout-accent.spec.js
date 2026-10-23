import { test, expect } from '@playwright/test'
import { openDisplay, gate, sweepText, ACCENTS, TEXT, GRAPHIC } from './display-helpers.js'

// <atom:callout variant="accent"> and its `actions` slot (a decision box), on the consumer's Tailwind, floor and latest: every text
// role against the tint it sits on, in both themes, for the default accent (which flips to near-white in dark) and three host accents.

for (const tw of ['floor', 'latest']) {
  test.describe(`callout accent, ${tw} consumer build`, () => {
    for (const dark of [false, true]) {
      for (const accent of [null, ...ACCENTS]) {
        test(`the body, heading, icon and buttons read in ${dark ? 'dark' : 'light'} with ${accent ?? 'the default accent'}`, async ({ page }) => {
          await openDisplay(page, { tw, dark, accent, section: 'panels' })

          const root = page.getByTestId('callout-accent')

          await gate(root.locator('.flex-col > div').nth(1), TEXT, 'text', 'accent callout body')
          await gate(root.locator('[data-atom-callout-heading]'), TEXT, 'text', 'accent callout heading')
          await gate(root.locator('[data-atom-icon]').first(), GRAPHIC, 'text', 'accent callout icon')

          expect(await sweepText(page, '[data-testid="callout-accent"]', TEXT, 4)).toEqual([])
        })
      }
    }

    test('the buttons sit in a row under the text, the primary last, and wrap on a phone', async ({ page }) => {
      await openDisplay(page, { tw, section: 'panels' })

      const row = page.getByTestId('callout-accent').locator('[data-atom-callout-actions]')
      const secondary = await page.getByTestId('callout-accent-secondary').boundingBox()
      const primary = await page.getByTestId('callout-accent-primary').boundingBox()
      const text = await page.getByTestId('callout-accent').locator('[data-atom-callout-heading]').boundingBox()

      await expect(row.locator('[data-atom-button]')).toHaveCount(2)
      expect(secondary.y).toBeCloseTo(primary.y, 0)
      expect(primary.x).toBeGreaterThan(secondary.x)
      expect(secondary.y).toBeGreaterThan(text.y + text.height)

      await page.setViewportSize({ width: 320, height: 800 })
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    })

    test('a callout without the slot draws no action row', async ({ page }) => {
      await openDisplay(page, { tw, section: 'panels' })

      await expect(page.getByTestId('callout-info').locator('[data-atom-callout-actions]')).toHaveCount(0)
    })
  })
}
