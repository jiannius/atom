import { test, expect } from '@playwright/test'

// An in-field loader or icon is centred in a standard-height control; a control made taller keeps it at the same top offset (it is not
// re-centred). The loader is <atom:input>'s `loading` slot, which <atom:table.search> fills. The e2e rig has no Tailwind, so the page opts in to
// the consumer build (`node tests/consumer/build.mjs` first): `flex` and `h-control` do not exist without it.
// Fixture: tests/Fixtures/InputLoaderFixture.php and input-loader-fixture.blade.php.

const BOX = 'input:not([type=hidden])'
const rect = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { top: r.top, bottom: r.bottom, right: r.right, height: r.height, mid: r.top + r.height / 2 }
})

// Hold every Livewire update until released, so the loading state stays up long enough to measure.
async function hold(page) {
  let release
  const gate = new Promise((resolve) => { release = resolve })
  await page.route('**/livewire*/update', async (route) => {
    await gate
    await route.continue()
  })
  return release
}

async function open(page, tw, density = 'comfortable') {
  await page.goto(`/atom/e2e/input-loader?tw=${tw}&density=${density}`)
  await expect(page.locator('[data-case]').first()).toBeVisible()
  await page.waitForFunction(() => window.Livewire && document.querySelector('[wire\\:id]'))
}

for (const tw of ['latest', 'floor']) {
  test.describe(`${tw} consumer build`, () => {
    test.beforeEach(async ({ page, request }) => {
      const css = await request.get(`/atom/e2e/consumer-css/${tw}`)
      test.skip(css.status() !== 200, `no ${tw} consumer build: run \`node tests/consumer/build.mjs ${tw}\``)
    })

    for (const density of ['comfortable', 'compact']) {
      for (const id of ['search-default', 'search-labelled', 'search-full']) {
        test(`the search spinner is centred in the input: ${id}, ${density}`, async ({ page }) => {
          await open(page, tw, density)
          const release = await hold(page)
          const box = page.locator(`[data-case="${id}"] ${BOX}`)
          const loader = page.locator(`[data-case="${id}"] [data-atom-input-loading]`)

          await box.fill('apple')
          await box.press('Enter')
          await expect(loader).toBeVisible()

          // a bare wire:loading shows as inline-block and drops `flex items-center`
          expect(await loader.evaluate((el) => getComputedStyle(el).display)).toBe('flex')

          const input = await rect(box)
          const spinner = await rect(loader.locator('svg'))
          expect(Math.abs(spinner.mid - input.mid)).toBeLessThanOrEqual(0.5)
          expect(input.right - spinner.right).toBeCloseTo(10, 0)

          release()
          await expect(loader).toBeHidden()
        })
      }
    }

    test('a taller search keeps the spinner at the standard top offset', async ({ page }) => {
      await open(page, tw)
      const release = await hold(page)
      const standard = page.locator('[data-case="search-default"]')
      const tall = page.locator('[data-case="search-tall"]')

      await standard.locator(BOX).press('Enter')
      await expect(standard.locator('[data-atom-input-loading]')).toBeVisible()
      await expect(tall.locator('[data-atom-input-loading]')).toBeVisible()

      const std = await rect(standard.locator(BOX))
      const stdSpinner = await rect(standard.locator('[data-atom-input-loading] svg'))
      const tl = await rect(tall.locator(BOX))
      const tallSpinner = await rect(tall.locator('[data-atom-input-loading] svg'))

      expect(tl.height).toBeGreaterThan(std.height + 8)
      expect(stdSpinner.top - std.top).toBeCloseTo(8, 0)
      expect(tallSpinner.top - tl.top).toBeCloseTo(stdSpinner.top - std.top, 1)
      // and it is not centred
      expect(Math.abs(tallSpinner.mid - tl.mid)).toBeGreaterThan(4)

      release()
    })

    test('the spinner replaces the clear button while it shows', async ({ page }) => {
      await open(page, tw)
      const release = await hold(page)
      const field = page.locator('[data-case="search-clearable"]')
      const clear = field.getByRole('button', { name: 'Clear' })
      const loader = field.locator('[data-atom-input-loading]')

      await expect(clear).toBeHidden()
      await field.locator(BOX).fill('apple')
      await expect(clear).toBeVisible()

      await field.locator(BOX).press('Enter')
      await expect(loader).toBeVisible()
      await expect(clear).toBeHidden()

      release()
      await expect(loader).toBeHidden()
      await expect(clear).toBeVisible()
    })

    // [name, standard case, taller case or null, icon (picked from the case), field box (picked from the case)]. Each icon is centred in the standard
    // control and, where there is a taller twin, sits at the same top offset in it.
    const SVG = (c) => c.locator('svg:visible')
    const FIRST = (c) => SVG(c).first()
    const LAST = (c) => SVG(c).last()
    const INPUT = (c) => c.locator(BOX).first()
    const WRAP = (c) => c.locator('div[class*="rounded-control"]').first()
    const TRIGGER = (c) => c.locator('button[aria-haspopup]').first()
    const icons = [
      ['input leading icon', 'input-icon', 'input-icon-tall', FIRST, INPUT],
      ['password eye', 'input-password', 'input-password-tall', LAST, INPUT],
      ['copy button', null, 'input-copy-tall', FIRST, INPUT],
      ['native select leading icon', 'select-native', 'select-native-tall', FIRST, WRAP],
      ['native select chevron', 'select-native', 'select-native-tall', LAST, WRAP],
      ['listbox leading icon', 'select-listbox', 'select-listbox-tall', FIRST, TRIGGER],
      ['listbox chevron', 'select-listbox', 'select-listbox-tall', LAST, TRIGGER],
      ['filter select icon', 'select-filter', null, FIRST, TRIGGER],
      ['date picker calendar icon', 'date', 'date-tall', FIRST, INPUT],
      ['date range calendar icon', 'date-range', null, FIRST, INPUT],
      ['time picker clock icon', 'time', null, FIRST, WRAP],
      ['tel country chevron', 'tel', 'tel-tall', FIRST, (c) => c.locator(BOX).last()],
    ]

    for (const [name, standard, taller, icon, field] of icons) {
      test(`${name}: centred at standard height${taller ? ', pinned when taller' : ''}`, async ({ page }) => {
        await open(page, tw)
        let stdOffset = 8

        if (standard) {
          const std = page.locator(`[data-case="${standard}"]`)
          const box = await rect(field(std))
          const glyph = await rect(icon(std))

          expect(Math.abs(glyph.mid - box.mid)).toBeLessThanOrEqual(0.5)
          stdOffset = glyph.top - box.top
        }

        if (taller) {
          const tall = page.locator(`[data-case="${taller}"]`)
          const box = await rect(field(tall))
          const glyph = await rect(icon(tall))

          expect(box.height).toBeGreaterThan(40)
          expect(glyph.top - box.top).toBeCloseTo(stdOffset, 1)
        }
      })
    }
  })
}
