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

      test('multiple listbox: a value does not grow the trigger and sits centred', async ({ page }) => {
        const empty = await measure(page, 'select-none', SELECT[0])
        const one = await measure(page, 'select-one', SELECT[0], SELECT[1])
        expect(one.height, 'trigger height with a value').toBeCloseTo(empty.height, 0)
        expect(Math.abs(one.top - one.bottom), `top ${one.top}, bottom ${one.bottom}`).toBeLessThanOrEqual(1)
      })
    })
  }
}
