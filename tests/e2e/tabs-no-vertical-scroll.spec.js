import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// A tab strip (<atom:page.tabs> and the line <atom:tabs>) never scrolls vertically: no scroll container in it, or around it in the
// places a host puts one (bare, in a card, in a wrapper that scrolls sideways), has more content than box, in either theme, at a
// desktop and a phone width, and also when the host's Tailwind build lacks `overflow-y-hidden` (the strip then relies on atom.css).
// The page's horizontal scroll for many tabs stays.
//
// Which assertion catches what: the defect (a host build without `overflow-y-hidden`) leaves the y axis `visible`, which computes to
// `auto` beside `overflow-x: auto`. Headless Chromium never shows a vertical thumb for it here, because the strip has no more content
// than box, so the GEOMETRY check (scrollHeight against clientHeight) passes with or without the fix: it guards against a future
// change that makes the strip taller than itself. Only the COMPUTED `overflowY` assertion fails without the rule in atom.css, so both stay.
// tabs-scrollbars.spec.js launches Chromium with scrollbars shown and measures the bar itself.

const verticalOverflow = (page) => page.evaluate(() => {
  const found = []

  for (const where of document.querySelectorAll('[data-testid^="where-"]')) {
    const check = (el) => {
      const style = getComputedStyle(el)

      if (style.overflowY !== 'visible' && el.scrollHeight > el.clientHeight) {
        found.push(`${where.dataset.testid}: ${el.tagName.toLowerCase()}.${String(el.className).slice(0, 40)} ${el.scrollHeight} > ${el.clientHeight} (${style.overflowY})`)
      }
    }

    check(where)
    where.querySelectorAll('*').forEach(check)
  }

  return found
})

for (const tw of ['floor', 'latest']) {
  test.describe(`tab strips, ${tw} consumer build`, () => {
    for (const dark of [false, true]) {
      for (const width of [1440, 390]) {
        test(`no vertical overflow in ${dark ? 'dark' : 'light'} at ${width}px`, async ({ page }) => {
          await page.setViewportSize({ width, height: 900 })
          await open(page, { path: 'tabs-overflow', tw, dark })

          expect(await verticalOverflow(page)).toEqual([])

          // both strips are there, drawn in every place, with the current tab underlined
          for (const where of ['bare', 'card', 'scroller']) {
            await expect(page.getByTestId(`page-tabs-${where}`)).toBeVisible()
            await expect(page.getByTestId(`tabs-${where}`)).toBeVisible()
          }

          const ul = page.getByTestId('page-tabs-bare').locator('ul')
          const [x, y] = await ul.evaluate((el) => [getComputedStyle(el).overflowX, getComputedStyle(el).overflowY])

          expect([x, y]).toEqual(['auto', 'hidden'])
        })
      }
    }

    test('a host build without overflow-y-hidden still has none, and the underline keeps its 2px', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 900 })
      await open(page, { path: 'tabs-overflow', tw })
      // what a host build that never compiled the utility would give
      await page.addStyleTag({ content: '.overflow-y-hidden { overflow-y: visible }' })

      expect(await verticalOverflow(page)).toEqual([])
      expect(await page.getByTestId('page-tabs-bare').locator('ul').evaluate((el) => getComputedStyle(el).overflowY)).toBe('hidden')

      const current = page.getByTestId('page-tabs-bare').locator('[aria-current="page"]')
      expect(await current.evaluate((el) => getComputedStyle(el).borderBottomWidth)).toBe('2px')
    })

    test('many tabs still scroll sideways inside the strip, and the strip stays in its column', async ({ page }) => {
      await page.setViewportSize({ width: 320, height: 800 })
      await open(page, { path: 'tabs-overflow', tw })

      const ul = page.getByTestId('page-tabs-bare').locator('ul')

      // five tabs do not fit in 320px minus the gutter
      expect(await ul.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true)
      // the strip is as wide as its column and scrolls inside it (<atom:tabs> is not a scrolling strip, and keeps its natural width)
      expect(await ul.evaluate((el) => el.getBoundingClientRect().width <= 320)).toBe(true)
    })

    test('the bare rig, with no Tailwind at all, has none either', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 900 })
      await page.goto('/atom/e2e/tabs-overflow?bare=1')

      expect(await verticalOverflow(page)).toEqual([])
    })
  })
}
