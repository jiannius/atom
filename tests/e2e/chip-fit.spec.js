import { test, expect, devices } from '@playwright/test'
import { open } from './controls-helpers.js'

// A chip in a field must not make the field taller: one row of chips is as tall as the empty field, and the chip sits centred
// between the field's borders. More chips wrap onto a second row and grow the field, which is fine. Every density, on both
// consumer Tailwind builds, and on a touch device (controls are 40px there) (`node tests/consumer/build.mjs` first).

const { defaultBrowserType, ...ipad } = devices['iPad (gen 7)']

const measure = (page, id, trigger, chip) => page.evaluate(({ id, trigger, chip }) => {
  const root = document.querySelector(`[data-testid="${id}"]`)
  const field = root.querySelector(trigger).getBoundingClientRect()
  const box = chip ? root.querySelector(chip)?.getBoundingClientRect() : null
  return { height: field.height, top: box ? box.top - field.top : null, bottom: box ? field.bottom - box.bottom : null, chipHeight: box?.height ?? null }
}, { id, trigger, chip })

const EMAIL = ['[data-atom-input-email] [data-atom-dropdown-trigger]', '[data-atom-input-email] [data-atom-dropdown-trigger] > div']
const SELECT = ['[data-atom-select-combobox]', '[data-atom-select-combobox] template + div, [data-atom-select-combobox] div.shrink-0']

for (const tw of ['floor', 'latest']) {
  for (const [density, touch] of [['compact', false], ['comfortable', false], ['compact', true]]) {
    test.describe(`${tw}, ${density}${touch ? ', touch' : ''}`, () => {
      if (touch) test.use({ ...ipad })

      test.beforeEach(async ({ page }) => {
        await open(page, { path: 'chip-fit', tw, density })
        await expect(page.locator('[data-testid="email-one"] [data-atom-input-email] [data-atom-dropdown-trigger] > div').first()).toBeVisible()
      })

      test('multi-email: a chip does not grow the field and sits centred', async ({ page }) => {
        const empty = await measure(page, 'email-none', EMAIL[0])
        expect(empty.height, 'the control height of this profile').toBe(touch ? 40 : { compact: 28, comfortable: 32 }[density])
        for (const id of ['email-one', 'email-bad']) {
          const one = await measure(page, id, EMAIL[0], EMAIL[1])
          expect(one.height, `${id}: field height with a chip`).toBeCloseTo(empty.height, 0)
          expect(Math.abs(one.top - one.bottom), `${id}: top ${one.top}, bottom ${one.bottom}`).toBeLessThanOrEqual(1)
          expect(one.top, 'clear of the border').toBeGreaterThanOrEqual(2)
        }
      })

      test('multi-email: chips that do not fit wrap and grow the field', async ({ page }) => {
        const empty = await measure(page, 'email-none', EMAIL[0])
        const many = await measure(page, 'email-many', EMAIL[0], EMAIL[1])
        expect(many.height).toBeGreaterThan(empty.height + 10)
      })

      test('multi-email: a very long address stays inside the field, clear of the clear button, with its full text as the title', async ({ page }) => {
        const got = await page.evaluate(() => {
          const root = document.querySelector('[data-testid="email-long"] [data-atom-input-email]')
          const field = root.querySelector('[data-atom-dropdown-trigger]').getBoundingClientRect()
          const chipEl = root.querySelector('[data-atom-dropdown-trigger] > div')
          const chip = chipEl.getBoundingClientRect()
          const clear = root.querySelector('[aria-label="Clear all"]').getBoundingClientRect()
          const text = chipEl.firstElementChild

          return {
            field: { left: field.left, right: field.right, height: field.height },
            chip: { left: chip.left, right: chip.right, height: chip.height },
            clearLeft: clear.left,
            title: text.getAttribute('title'),
            truncated: text.scrollWidth > text.clientWidth,
            chipOverflows: chipEl.scrollWidth > chipEl.clientWidth + 1,
          }
        })

        expect(got.chip.left, 'inside the field on the left').toBeGreaterThanOrEqual(got.field.left)
        expect(got.chip.right, 'inside the field on the right').toBeLessThanOrEqual(got.field.right)
        expect(got.chip.right, 'clear of the clear button').toBeLessThanOrEqual(got.clearLeft)
        expect(got.truncated, 'the text is cut, not the chip').toBe(true)
        expect(got.chipOverflows, 'the close button is not pushed out of the chip').toBe(false)
        expect(got.title).toContain('a'.repeat(138) + '@example.com')
        expect(got.chip.height, 'a long chip is still one row').toBeCloseTo(touch ? 32 : { compact: 20, comfortable: 24 }[density], 0)
      })

      test('multi-email: the remove button is at least 24px high to hit, without a taller chip', async ({ page }) => {
        const got = await page.evaluate(() => {
          const chipEl = document.querySelector('[data-testid="email-one"] [data-atom-dropdown-trigger] > div')
          const button = chipEl.querySelector('[data-atom-chip-remove]')
          const chip = chipEl.getBoundingClientRect()
          const b = button.getBoundingClientRect()
          const x = b.left + b.width / 2
          let hit = 0
          for (let y = chip.top - 10; y < chip.bottom + 10; y += 0.25) {
            if (button.contains(document.elementFromPoint(x, y))) hit += 0.25
          }
          return { hit, chipHeight: chip.height, width: b.width }
        })

        expect(got.hit, 'the hit area').toBeGreaterThanOrEqual(24)
        expect(got.hit - got.chipHeight, 'reaches past the chip, which keeps its height').toBeGreaterThan(0)
        expect(got.width).toBeGreaterThanOrEqual(24)
      })

      test('multiple listbox: a value does not grow the trigger and sits centred', async ({ page }) => {
        const empty = await measure(page, 'select-none', SELECT[0])
        const one = await measure(page, 'select-one', SELECT[0], SELECT[1])
        expect(one.height, 'trigger height with a value').toBeCloseTo(empty.height, 0)
        expect(Math.abs(one.top - one.bottom), `top ${one.top}, bottom ${one.bottom}`).toBeLessThanOrEqual(1)
      })
    })
  }
}
