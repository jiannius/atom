import { test, expect } from '@playwright/test'

// The page save bar (<atom:form.actions bar>) is a card: sticky 0.75rem above the bottom of the screen, as wide as the form's sections,
// a solid surface with a 1px border, a 10px radius and the pop shadow, and nothing painted outside its own box. At the end of the page it
// takes the page's bottom room as its own (a negative bottom margin), so it sits right under the last section in the place it holds while
// stuck. The pages are the form pattern (an invoice, in both shells) and the save-bar fixture, on the consumer's Tailwind (the rig has none),
// at the hosts' minimum (floor) and the newest 4.x (latest).

const GAP = 12 // 0.75rem: how far above the bottom of the screen the card floats

const open = async (page, url, tw, dark = false) => {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.emulateMedia({ colorScheme: dark ? 'dark' : 'light' })
  await page.goto(`${url}${url.includes('?') ? '&' : '?'}tw=${tw}${dark ? '&dark=1' : ''}`)
  await page.evaluate(() => document.fonts.ready)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }
})

const styles = (locator, props, pseudo = null) => locator.evaluate((el, [props, pseudo]) => {
  const s = getComputedStyle(el, pseudo)
  return Object.fromEntries(props.map((p) => [p, s.getPropertyValue(p)]))
}, [props, pseudo])

const viewportHeight = (page) => page.evaluate(() => document.documentElement.clientHeight)
const scrollTo = (page, y) => page.evaluate((y) => window.scrollTo(0, y), y)
const maxScroll = (page) => page.evaluate(() => document.documentElement.scrollHeight - document.documentElement.clientHeight)
const noSideScroll = (page) => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)

// The colour of one pixel of the screen, read back from a screenshot
const pixel = async (page, x, y) => {
  const png = await page.screenshot({ clip: { x: Math.round(x), y: Math.round(y), width: 1, height: 1 } })

  return page.evaluate(async (base64) => {
    const image = new Image()
    image.src = `data:image/png;base64,${base64}`
    await image.decode()
    const cv = document.createElement('canvas')
    cv.width = cv.height = 1
    const ctx = cv.getContext('2d')
    ctx.drawImage(image, 0, 0)

    return [...ctx.getImageData(0, 0, 1, 1).data].slice(0, 3)
  }, png.toString('base64'))
}

const bar = (page) => page.locator('[data-atom-save-bar]')

for (const tw of ['floor', 'latest']) {
  test.describe(`the save bar card, ${tw} consumer build`, () => {
    test.describe('at 1440px', () => {
      test.use({ viewport: { width: 1440, height: 900 } })

      for (const shell of ['sidebar', 'header']) {
        test.describe(`${shell} shell`, () => {
          test.beforeEach(async ({ page }) => open(page, `/atom/e2e/shell-form?shell=${shell}`, tw))

          test('mid-scroll: the card floats 12px above the bottom of the screen, as wide as the sections, with its own radius, border and shadow', async ({ page }) => {
            await scrollTo(page, 300)

            const b = await box(bar(page))
            const vh = await viewportHeight(page)
            const section = await box(page.locator('[data-atom-form-section]').first())
            const s = await styles(bar(page), ['border-top-width', 'border-top-left-radius', 'border-top-color', 'box-shadow', 'background-color', 'backdrop-filter', 'position', 'z-index', 'padding-top', 'padding-left', 'row-gap', 'column-gap'])

            await expect(bar(page)).toBeInViewport()
            expect(Math.abs(vh - GAP - b.bottom), 'bottom edge').toBeLessThan(1.5)
            expect(Math.abs(b.x - section.x), 'left edge').toBeLessThan(1)
            expect(Math.abs(b.right - section.right), 'right edge').toBeLessThan(1)
            expect(s['border-top-width']).toBe('1px')
            expect(s['border-top-left-radius']).toBe('10px')
            expect(s['box-shadow']).not.toBe('none')
            // a solid surface: opaque, no blur
            expect(s['background-color']).toMatch(/^(rgb\([^/]*\)|color\([^/]*\))$/)
            expect(s['backdrop-filter']).toBe('none')
            expect(s.position).toBe('sticky')
            expect(s['z-index']).toBe('10')
            expect([s['padding-top'], s['padding-left']]).toEqual(['12px', '16px'])
            expect([s['row-gap'], s['column-gap']]).toEqual(['8px', '8px'])
          })

          test('it paints nothing outside its own box: no surface, hairline or shadow strip out to the screen edge', async ({ page }) => {
            await scrollTo(page, 300)

            for (const pseudo of ['::before', '::after']) {
              expect((await styles(bar(page), ['content'], pseudo)).content, pseudo).toBe('none')
            }

            const b = await box(bar(page))
            const width = await page.evaluate(() => document.documentElement.clientWidth)
            // far from the card (its shadow reaches ~28px), at its height and above it, past the page column on the right: the same page background
            const at = await pixel(page, width - 4, b.y + b.height / 2)
            const above = await pixel(page, width - 4, b.y - 120)

            for (let i = 0; i < 3; i++) expect(Math.abs(at[i] - above[i]), `${shell}: channel ${i}, ${at} against ${above}`).toBeLessThanOrEqual(2)

            if (shell === 'header') {
              const left = await pixel(page, 4, b.y + b.height / 2)
              const leftAbove = await pixel(page, 4, b.y - 120)

              for (let i = 0; i < 3; i++) expect(Math.abs(left[i] - leftAbove[i]), `left, channel ${i}`).toBeLessThanOrEqual(2)
            }

            expect(await noSideScroll(page)).toBe(true)
          })

          test('at the end of the page it sits in flow below the last section: no 120px hole, and it does not jump while the page scrolls to the end', async ({ page }) => {
            // a screen the page is clearly taller than
            await page.setViewportSize({ width: 1440, height: 500 })

            const vh = await viewportHeight(page)
            const max = await maxScroll(page)

            expect(max, 'a page taller than the screen').toBeGreaterThan(200)

            // every scroll position over the last 200px, in steps: the card's bottom edge is at the same place all the way
            for (let y = Math.max(0, max - 200); y <= max; y += 20) {
              await scrollTo(page, y)
              expect(Math.abs(vh - GAP - (await box(bar(page))).bottom), `scrolled to ${y} of ${max}`).toBeLessThan(1.5)
            }

            await scrollTo(page, max)

            const b = await box(bar(page))
            const last = await box(page.locator('[data-atom-form-section]').last())
            const pageBox = await box(page.locator('[data-atom-page]'))

            // right below the last section, one form gap (24px) beneath it
            expect(b.y - last.bottom, 'under the last section').toBeGreaterThanOrEqual(0)
            expect(b.y - last.bottom, 'under the last section').toBeLessThan(40)
            // and 12px above the bottom of the page, not 120px
            expect(Math.abs(pageBox.bottom - b.bottom - GAP), 'page bottom to card bottom').toBeLessThan(1.5)
          })

          test('light and dark: the card is the surface colour, a border colour apart from the page', async ({ page }) => {
            for (const dark of [false, true]) {
              await open(page, `/atom/e2e/shell-form?shell=${shell}`, tw, dark)
              await scrollTo(page, 300)

              const s = await styles(bar(page), ['background-color', 'border-top-color'])
              const surface = await page.evaluate(() => getComputedStyle(document.querySelector('[data-atom-main]') ?? document.body).getPropertyValue('--atom-surface'))
              const b = await box(bar(page))
              const inside = await pixel(page, b.x + b.width * 0.4, b.bottom - 6)
              const outside = await pixel(page, b.x + b.width * 0.4, b.bottom + 6)

              expect(s['background-color'], dark ? 'dark' : 'light').not.toBe('rgba(0, 0, 0, 0)')
              expect(surface.trim().length).toBeGreaterThan(0)
              // the card is not the page's own background colour: you can tell it from what is under it
              expect(Math.abs(inside[0] - outside[0]) + Math.abs(inside[1] - outside[1]) + Math.abs(inside[2] - outside[2]), `${dark ? 'dark' : 'light'}: card against page`).toBeGreaterThan(3)
            }
          })
        })
      }

      test('the start slot: muted text, a <b> as the figure (semibold, foreground, tabular), the buttons at the end in the order written', async ({ page }) => {
        await open(page, '/atom/e2e/shell-form', tw)

        const start = page.locator('[data-atom-form-actions-start]')
        const figure = start.locator('b').first()
        const muted = await styles(start, ['color', 'column-gap', 'flex-wrap', 'font-size'])
        const strong = await styles(figure, ['color', 'font-weight', 'font-variant-numeric'])
        const buttons = await box(page.locator('[data-atom-form-actions-buttons]'))
        const barBox = await box(bar(page))
        const labels = await page.locator('[data-atom-form-actions-buttons] button').allInnerTexts()

        expect(muted['column-gap']).toBe('14px')
        expect(muted['flex-wrap']).toBe('wrap')
        expect(muted.color).not.toBe(strong.color)
        expect(strong['font-weight']).toBe('590')
        expect(strong['font-variant-numeric']).toContain('tabular-nums')
        expect(muted['font-size']).toBe((await styles(page.locator('body'), ['font-size']))['font-size'])
        expect(Math.abs(buttons.right - (barBox.right - 17))).toBeLessThan(1)
        expect(labels.map((label) => label.trim())).toEqual(['Cancel', 'Save draft', 'Save and send'])
      })

      test('a page with its own bottom room (--atom-page-pb) is followed: the card still ends the page 12px above the bottom', async ({ page }) => {
        for (const query of ['mode=page', 'mode=page&pb=80', 'shell=sidebar&pb=80']) {
          await open(page, `/atom/e2e/save-bar-context?${query}`, tw)
          await scrollTo(page, await maxScroll(page))

          const b = await box(bar(page))
          const pageBox = await box(page.getByTestId('page'))

          expect(Math.abs(pageBox.bottom - b.bottom - GAP), query).toBeLessThan(1.5)
          expect(Math.abs((await viewportHeight(page)) - GAP - b.bottom), query).toBeLessThan(1.5)
        }
      })
    })

    test.describe('on a phone', () => {
      test.use({ viewport: { width: 390, height: 844 } })

      for (const shell of ['sidebar', 'header']) {
        test(`${shell} shell: still a card, inside the page's 16px gutter; the buttons take the row and the submit grows`, async ({ page }) => {
          await open(page, `/atom/e2e/shell-form?shell=${shell}`, tw)
          await scrollTo(page, 300)

          const b = await box(bar(page))
          const vh = await viewportHeight(page)
          const buttons = await box(page.locator('[data-atom-form-actions-buttons]'))
          const submit = await box(page.getByTestId('form-primary'))
          const section = await box(page.locator('[data-atom-form-section]').first())

          expect(Math.abs(b.x - 16)).toBeLessThan(1)
          expect(Math.abs(b.right - 374)).toBeLessThan(1)
          expect(Math.abs(b.x - section.x)).toBeLessThan(1)
          expect(Math.abs(vh - GAP - b.bottom)).toBeLessThan(1.5)
          expect((await styles(bar(page), ['border-top-left-radius']))['border-top-left-radius']).toBe('10px')
          expect(Math.abs(buttons.width - (b.width - 34))).toBeLessThan(1.5)
          expect(submit.width).toBeGreaterThan(120)
          expect(await noSideScroll(page)).toBe(true)
        })
      }
    })

    test.describe('outside a page', () => {
      test.use({ viewport: { width: 1000, height: 700 } })

      test('in a plain box: inside its parent, no sideways scroll, nothing bleeding out of it', async ({ page }) => {
        await open(page, '/atom/e2e/save-bar-context?mode=bare', tw)

        const b = await box(bar(page))
        const parent = await box(page.getByTestId('box'))
        const s = await styles(bar(page), ['margin-left', 'margin-right', 'padding-left', 'padding-right'])

        expect([s['margin-left'], s['margin-right']]).toEqual(['0px', '0px'])
        expect([s['padding-left'], s['padding-right']]).toEqual(['16px', '16px'])
        expect(b.x).toBeGreaterThanOrEqual(parent.x)
        expect(b.right).toBeLessThanOrEqual(parent.right)
        expect(await noSideScroll(page)).toBe(true)
      })
    })
  })
}
