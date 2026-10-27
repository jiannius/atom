import { test, expect } from '@playwright/test'

// <atom:page> is centred in the space its shell gives it: the default (1240px) and `narrow` (980px) columns carry `mx-auto`, `full` has
// no cap and fills <main>. The pages are the shells' pattern pages and the save-bar fixture, on the consumer's Tailwind (the rig has none;
// `mx-auto` is a utility), at the hosts' minimum (floor) and the newest 4.x (latest). What is measured is the page's gap to <main> on each
// side, and that what sits inside the page (the header, the tabs, the table toolbar and its pager) stays on the page column.

const open = async (page, url, tw) => {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`${url}${url.includes('?') ? '&' : '?'}tw=${tw}`)
  await page.evaluate(() => document.fonts.ready)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }
})

// the page's gap to <main> on the left and on the right
const gaps = async (page, selector = '[data-atom-page]') => {
  const main = await box(page.locator('[data-atom-main]'))
  const p = await box(page.locator(selector).first())

  return { left: p.x - main.x, right: main.right - p.right, width: p.width, main }
}

const noSideScroll = (page) => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)

for (const tw of ['floor', 'latest']) {
  test.describe(`a centred page, ${tw} consumer build`, () => {
    for (const width of [1440, 1920]) {
      test.describe(`at ${width}px`, () => {
        test.use({ viewport: { width, height: 900 } })

        for (const shell of ['sidebar', 'header']) {
          test(`${shell} shell: the default page's gap on the left equals the gap on the right; narrow is centred; full fills <main>`, async ({ page }) => {
            // the column the page asks for: the default is 1240px (the top-nav shell's own 1248px, its bar's width), narrow 980px, full has none
            for (const [name, query, cap] of [['default', '', shell === 'header' ? 1248 : 1240], ['narrow', '&width=narrow', 980], ['full', '&width=full', null]]) {
              await open(page, `/atom/e2e/save-bar-context?shell=${shell}${query}`, tw)

              const g = await gaps(page, '[data-testid="page"]')
              const expected = cap ? Math.min(cap, g.main.width) : g.main.width

              expect(Math.abs(g.left - g.right), `${shell} ${name}: ${g.left} against ${g.right}`).toBeLessThanOrEqual(1)
              expect(Math.abs(g.width - expected), `${shell} ${name}: width`).toBeLessThanOrEqual(1)

              // where <main> is wider than the column the page is not stuck to the start of it
              if (g.main.width > expected + 2) expect(g.left, `${shell} ${name}`).toBeGreaterThan(1)

              expect(await noSideScroll(page), `${shell} ${name}`).toBe(true)
            }
          })
        }

        test('top-nav shell: the centred default page starts where the bar and its brand do', async ({ page }) => {
          await open(page, '/atom/e2e/shell-record?shell=header', tw)

          const head = await box(page.locator('[data-atom-page-header]').first())
          const brand = await box(page.locator('[data-atom-header-bar] a').first())

          expect(Math.abs(head.x - brand.x)).toBeLessThanOrEqual(1)
        })

        test('a utility of the host\'s on the page (`[--atom-page-max:1000px]`) beats the top-nav shell\'s own cap', async ({ page }) => {
          for (const shell of ['header', 'sidebar']) {
            await open(page, `/atom/e2e/save-bar-context?shell=${shell}&max=1000`, tw)

            const g = await gaps(page, '[data-testid="page"]')

            expect(Math.round(g.width), shell).toBe(1000)
            expect(Math.abs(g.left - g.right), shell).toBeLessThanOrEqual(1)
          }
        })

        test('what is inside the page stays on its column: header, tabs, and a table with its toolbar and pager', async ({ page }) => {
          for (const url of ['/atom/e2e/shell-record', '/atom/e2e/shell-list']) {
            await open(page, url, tw)

            const p = await box(page.locator('[data-atom-page]').first())
            const pad = parseFloat(await page.locator('[data-atom-page]').first().evaluate((el) => getComputedStyle(el).paddingLeft))
            const g = await gaps(page)

            expect(Math.abs(g.left - g.right), url).toBeLessThanOrEqual(1)

            for (const selector of ['[data-atom-page-header]', '[data-atom-page-tabs]', '[data-atom-table-toolbar]', '[data-atom-table-pager]']) {
              const el = page.locator(selector).first()

              if (! await el.count()) continue

              const b = await box(el)

              expect(b.x, `${url} ${selector}`).toBeGreaterThanOrEqual(p.x + pad - 1)
              expect(b.right, `${url} ${selector}`).toBeLessThanOrEqual(p.right - pad + 1)
            }
          }
        })
      })
    }

    test.describe('at 390px', () => {
      test.use({ viewport: { width: 390, height: 844 } })

      for (const shell of ['sidebar', 'header']) {
        test(`${shell} shell: the page fills the screen less its 16px gutters, nothing scrolls sideways`, async ({ page }) => {
          for (const query of ['', '&width=narrow', '&width=full']) {
            await open(page, `/atom/e2e/save-bar-context?shell=${shell}${query}`, tw)

            const p = await box(page.locator('[data-testid="page"]'))
            const g = await gaps(page, '[data-testid="page"]')

            expect(Math.abs(g.left - g.right), `${shell}${query}`).toBeLessThanOrEqual(1)
            expect(p.width, `${shell}${query}`).toBeGreaterThan(300)
            expect(await noSideScroll(page), `${shell}${query}`).toBe(true)
          }
        })
      }
    })
  })
}
