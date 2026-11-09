import { test, expect } from '@playwright/test'
import { open, contrastOf } from './controls-helpers.js'

// Issue #178: in dark a disabled field was told from an enabled one by its fill alone (#111110 against #1c1b19, 1.1:1), the same
// border and nothing to show in an empty one. Every text control's disabled state now also drops its strong hairline
// (--atom-control-border) for the faint one (--atom-border), and keeps the sunken fill. Run `node tests/consumer/build.mjs` first.
//
// [pair, what draws the box, whether the pair's test id is on the box itself]. Time-picker and date-range have no disabled state.
const fields = [
  ['input-empty', null, true],
  ['input-filled', null, true],
  ['password', null, true],
  ['textarea-empty', null, true],
  ['textarea-filled', null, true],
  ['select-native', '[data-atom-select-native] > div:first-of-type'],
  ['select-listbox', '[data-atom-select-combobox]'],
  ['date', 'input'],
  ['tel', 'input[type=tel]'],
  ['otp', '[data-atom-input-otp-box]'],
  ['email', '[data-atom-dropdown-trigger]'],
]

const box = (page, pair, state, selector, direct) => {
  const root = page.getByTestId(`pair-${pair}`).getByTestId(state)
  return (direct ? root : root.locator(selector)).first()
}

const read = (locator) => locator.evaluate((el) => {
  const s = getComputedStyle(el)
  return { background: s.backgroundColor, border: s.borderTopColor, color: s.color }
})

for (const tw of ['floor', 'latest']) {
  for (const dark of [true, false]) {
    test.describe(`on the ${tw} build, ${dark ? 'dark' : 'light'}`, () => {
      for (const [pair, selector, direct] of fields) {
        test(`${pair}: disabled differs from enabled by its border as well as its fill`, async ({ page }) => {
          await open(page, { path: 'disabled-controls', tw, dark })

          const on = box(page, pair, 'on', selector, direct)
          const off = box(page, pair, 'off', selector, direct)
          const [enabled, disabled] = [await read(on), await read(off)]

          // the fill is sunken, as it was
          expect(disabled.background, 'fill').not.toBe(enabled.background)
          // and the border is no longer the enabled one: the faint hairline, which is the colour of the --atom-border token
          expect(disabled.border, 'border').not.toBe(enabled.border)
          const hairline = await page.evaluate(() => {
            const probe = document.createElement('div')
            probe.className = 'border border-border'
            document.body.append(probe)
            const colour = getComputedStyle(probe).borderTopColor
            probe.remove()
            return colour
          })
          expect(disabled.border, 'the border is the faint hairline').toBe(hairline)

          // in dark the strong hairline is 3.4:1 against the fill and the faint one 1.2:1 (in light 1.4 and 1.2): a cue that does not lean on the fill
          const [enabledEdge, disabledEdge] = [(await on.evaluate(contrastOf, { what: 'border' })).ratio, (await off.evaluate(contrastOf, { what: 'border' })).ratio]
          expect(disabledEdge, 'the disabled border is fainter against its own fill').toBeLessThan(enabledEdge)
          if (dark) expect(disabledEdge, 'in dark, by a wide margin').toBeLessThan(enabledEdge / 2)
        })
      }

      test('a disabled value stays readable', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        // the text is the subtle foreground (a value, a placeholder or a chip alike); it was 4.7:1 in light and 5.8:1 in dark, and the change leaves it so
        for (const [pair, selector, direct] of fields) {
          const text = (await box(page, pair, 'off', selector, direct).evaluate(contrastOf, { what: 'text' })).ratio
          expect(text, `${pair} text`).toBeGreaterThanOrEqual(4.5)
        }
      })
    })
  }
}
