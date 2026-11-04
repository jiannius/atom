import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// <atom:prose> (issue #160) on the consumer's Tailwind, floor and latest: markdown that tries to run script comes out as text, the small
// size is smaller, the first and last block sit flush against the bubble, and a message over the limit is shown as it is, never blank.

for (const tw of ['floor', 'latest']) {
  test.describe(`prose, ${tw} consumer build`, () => {
    test.beforeEach(async ({ page }) => {
      await page.addInitScript(() => { window.__pwned = 0 })
    })

    test('markdown that carries script, a handler and an unsafe link runs nothing and prints no image', async ({ page }) => {
      await open(page, { path: 'prose', tw })
      await page.waitForTimeout(300)

      expect(await page.evaluate(() => window.__pwned)).toBe(0)

      for (const id of ['prose-sm', 'prose-base']) {
        const prose = page.getByTestId(id)

        await expect(prose.locator('img, script, iframe')).toHaveCount(0)
        await expect(prose.locator('a')).toHaveCount(1)
        await expect(prose.locator('a')).toHaveAttribute('href', 'https://example.com')
        await expect(prose.locator('h2')).toHaveText('Plan')
        await expect(prose.locator('table td')).toHaveCount(2)
        await expect(prose.locator('pre code.language-php')).toHaveCount(1)
        // the escaped tag is text, and so is the dead link
        await expect(prose).toContainText('<img src=x onerror="window.__pwned=1">')
        await expect(prose).toContainText('bad link')
        await expect(prose).toContainText('tracking pixel')
      }
    })

    test('the small size reads smaller, and the first and last block sit flush against the bubble', async ({ page }) => {
      await open(page, { path: 'prose', tw })

      const metrics = (id) => page.getByTestId(id).evaluate((el) => {
        const first = el.firstElementChild
        const last = el.lastElementChild
        return {
          size: parseFloat(getComputedStyle(el).fontSize),
          h2: parseFloat(getComputedStyle(el.querySelector('h2')).fontSize),
          top: parseFloat(getComputedStyle(first).marginTop),
          bottom: parseFloat(getComputedStyle(last).marginBottom),
          // the bubble's padding is the only space around the text
          gapTop: first.getBoundingClientRect().top - el.parentElement.getBoundingClientRect().top,
          gapBottom: el.parentElement.getBoundingClientRect().bottom - last.getBoundingClientRect().bottom,
          pad: parseFloat(getComputedStyle(el.parentElement).paddingTop),
        }
      })

      const sm = await metrics('prose-sm')
      const base = await metrics('prose-base')

      expect(sm.size).toBeLessThan(base.size)
      expect(sm.h2).toBeLessThan(base.h2)

      for (const m of [sm, base]) {
        expect(m.bottom).toBe(0)
        expect(m.top).toBe(0)
        expect(Math.abs(m.gapTop - m.pad)).toBeLessThan(1.5)
        expect(Math.abs(m.gapBottom - m.pad)).toBeLessThan(1.5)
      }
    })

    test('a message over the limit is shown as the text it is, with its line breaks, not blank and not run', async ({ page }) => {
      await open(page, { path: 'prose', tw })

      const long = page.getByTestId('prose-long')

      await expect(long).toHaveAttribute('data-fallback', '')
      await expect(long).toContainText('# Heading stays text')
      await expect(long).toContainText('<script>window.__pwned=1</script>')
      await expect(long.locator('h1, script')).toHaveCount(0)
      expect(await long.evaluate((el) => getComputedStyle(el).whiteSpace)).toBe('pre-line')
      expect(await page.evaluate(() => window.__pwned)).toBe(0)
    })

    test('stored content and the slot render in the same wrapper, and a timeline message holds prose', async ({ page }) => {
      await open(page, { path: 'prose', tw })

      await expect(page.getByTestId('prose-content')).toHaveText('Stored content')
      await expect(page.getByTestId('prose-slot').locator('b')).toHaveText('markup')
      await expect(page.getByTestId('message').locator('[data-atom-timeline-bubble] [data-atom-prose] h2')).toHaveText('Plan')
    })
  })
}
