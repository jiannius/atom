import { test, expect } from '@playwright/test'
import { open, contrastOf } from './controls-helpers.js'
import { sweepText, ACCENTS, TEXT, GRAPHIC } from './display-helpers.js'

// <atom:timeline kind="path">: the horizontal path of stages, on the consumer's Tailwind (`node tests/consumer/build.mjs`), floor and
// latest. Every text role is read against ITS OWN surface (the tinted current segment included) in both themes, for the default accent
// and three host accents; the markers are graphics at 3:1; the strip is one row from 42rem of its own box and a column below it.

const path = (page) => page.getByTestId('path')

for (const tw of ['floor', 'latest']) {
  test.describe(`timeline path, ${tw} consumer build`, () => {
    for (const dark of [false, true]) {
      for (const accent of [null, ...ACCENTS]) {
        test(`every text and marker reads in ${dark ? 'dark' : 'light'} with ${accent ?? 'the default accent'}`, async ({ page }) => {
          await open(page, { path: 'timeline', tw, dark, accent })

          expect(await sweepText(page, '[data-testid="path"]', TEXT, 8)).toEqual([])

          // the current segment in the status's tone reads too, whichever the tone
          for (const tone of ['info', 'warning']) {
            await page.goto(page.url().replace(/&tone=\w+/, '') + `&tone=${tone}`)
            expect(await sweepText(page, '[data-testid="path"]', TEXT, 8), tone).toEqual([])

            const ring = await page.getByTestId('path').locator('[data-state="current"] [data-atom-timeline-marker]').evaluate(contrastOf, { what: 'border' })
            expect(ring.ratio, `the ${tone} current marker: ${ring.colour} on ${ring.back}`).toBeGreaterThanOrEqual(GRAPHIC)
          }

          await page.goto(page.url().replace(/&tone=\w+/, ''))

          // a pending ring, and the filled done and failed discs, against the surface the segment sits on
          for (const [state, what] of [['pending', 'border'], ['done', 'fill'], ['failed', 'fill']]) {
            const marker = path(page).locator(`[data-state="${state}"] [data-atom-timeline-marker]`).first()
            const found = await marker.evaluate(contrastOf, { what })

            expect(found.ratio, `the ${state} marker: ${found.colour} on ${found.back}`).toBeGreaterThanOrEqual(GRAPHIC)
          }

          // the current marker's ring is the accent ink, on the tinted segment
          const ring = await path(page).locator('[data-state="current"] [data-atom-timeline-marker]').evaluate(contrastOf, { what: 'border' })
          expect(ring.ratio, `the current marker: ${ring.colour} on ${ring.back}`).toBeGreaterThanOrEqual(GRAPHIC)
        })
      }
    }

    test('the segments sit side by side in one strip on a wide screen, the current one tinted', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'timeline', tw, accent: '#4c55c9' })

      const boxes = await path(page).locator('> ol > li').evaluateAll((els) => els.map((el) => {
        const r = el.getBoundingClientRect()
        return { x: r.x, y: r.y, width: r.width, height: r.height }
      }))

      expect(boxes).toHaveLength(5)
      for (const [i, box] of boxes.entries()) {
        expect(box.y, `segment ${i} shares the first one's top`).toBeCloseTo(boxes[0].y, 0)
        if (i) expect(box.x, `segment ${i} sits after segment ${i - 1}`).toBeGreaterThan(boxes[i - 1].x + boxes[i - 1].width - 1)
      }
      // equal columns
      expect(Math.abs(boxes[0].width - boxes[4].width)).toBeLessThan(2)

      const current = path(page).locator('[data-state="current"]')
      expect(await current.evaluate((el) => getComputedStyle(el).backgroundColor)).not.toBe('rgba(0, 0, 0, 0)')
      expect(await path(page).locator('[data-state="done"]').evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgba(0, 0, 0, 0)')
      await expect(current).toHaveAttribute('aria-current', 'step')
    })

    test('the segments stack as a column at phone width, and the page does not scroll sideways', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 900 })
      await open(page, { path: 'timeline', tw, extra: '&shell=header' })

      const boxes = await path(page).locator('> ol > li').evaluateAll((els) => els.map((el) => {
        const r = el.getBoundingClientRect()
        return { x: r.x, y: r.y, width: r.width }
      }))

      for (const [i, box] of boxes.entries()) {
        expect(box.x, `segment ${i} shares the first one's left`).toBeCloseTo(boxes[0].x, 0)
        if (i) expect(box.y, `segment ${i} sits under segment ${i - 1}`).toBeGreaterThan(boxes[i - 1].y)
      }

      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    })

    test('a path in a narrow column stacks even on a wide screen (its own box decides)', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path: 'timeline', tw })
      await path(page).evaluate((el) => { el.style.width = '360px' })

      const [a, b] = await path(page).locator('> ol > li').evaluateAll((els) => els.slice(0, 2).map((el) => el.getBoundingClientRect()).map((r) => ({ x: r.x, y: r.y })))

      expect(b.y).toBeGreaterThan(a.y)
      expect(b.x).toBeCloseTo(a.x, 0)
    })
  })
}

// Long unbroken text (a URL, a hash, an id) must wrap inside a narrow box, in every kind, and the path too: a 400-character string used
// to turn a 172px box several thousand pixels wide.
for (const tw of ['floor', 'latest']) {
  test.describe(`timeline long text, ${tw} consumer build`, () => {
    for (const width of [390, 1440]) {
      test(`nothing grows past its box at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 })
        await open(page, { path: 'timeline-wrap', tw })

        // open the folded things, so their text is laid out too
        for (const toggle of await page.locator('[data-atom-timeline-toggle]').all()) {
          if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click()
        }

        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'the page does not scroll sideways').toBe(true)

        for (const id of ['wrap-event', 'wrap-step', 'wrap-path', 'wrap-message']) {
          const overflow = await page.getByTestId(id).evaluate((root) => {
            const box = root.getBoundingClientRect()
            return [...root.querySelectorAll('*')].filter((el) => !el.closest('.sr-only') && el.getBoundingClientRect().right > box.right + 1).map((el) => el.tagName + '.' + el.className.toString().slice(0, 40))
          })

          expect(overflow, `${id} has content wider than itself`).toEqual([])
        }
      })
    }
  })
}
