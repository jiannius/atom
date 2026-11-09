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

// the eight controls the rows above hold, one each
const eight = [['input', null, true], ['textarea', null, true], ...fields.filter(([pair]) => ['select-native', 'select-listbox', 'date', 'tel', 'otp', 'email'].includes(pair))]

const box = (page, pair, state, selector, direct) => {
  const root = page.getByTestId(`pair-${pair}`).getByTestId(state)
  return (direct ? root : root.locator(selector)).first()
}

/** The computed border colour of a probe carrying one border-colour class, which is the token as this build resolves it. */
const colourOf = (page, cls) => page.evaluate((cls) => {
  const probe = document.createElement('div')
  probe.className = `border ${cls}`
  document.body.append(probe)
  const colour = getComputedStyle(probe).borderTopColor
  probe.remove()
  return colour
}, cls)

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
          const hairline = await colourOf(page, 'border-border')
          expect(disabled.border, 'the border is the faint hairline').toBe(hairline)

          // in dark the strong hairline is 3.4:1 against the fill and the faint one 1.2:1 (in light 1.4 and 1.2): a cue that does not lean on the fill
          const [enabledEdge, disabledEdge] = [(await on.evaluate(contrastOf, { what: 'border' })).ratio, (await off.evaluate(contrastOf, { what: 'border' })).ratio]
          expect(disabledEdge, 'the disabled border is fainter against its own fill').toBeLessThan(enabledEdge)
          if (dark) expect(disabledEdge, 'in dark, by a wide margin').toBeLessThan(enabledEdge / 2)
        })
      }

      // a disabled field is faint whatever else is said about it: `invalid` on it, or an error on the field around it, is out-ranked
      // by the disabled border (the enabled copy beside it is the danger tone, which is what proves the setup)
      for (const mode of ['invalid', 'error']) {
        for (const [name, selector, direct] of eight) {
          test(`${name} in ${mode}: disabled keeps the faint border`, async ({ page }) => {
            await open(page, { path: 'disabled-controls', tw, dark })

            const pair = `${mode}-${name}`
            const [enabled, disabled] = [await read(box(page, pair, 'on', selector, direct)), await read(box(page, pair, 'off', selector, direct))]
            const [danger, hairline] = [await colourOf(page, 'border-danger-fg'), await colourOf(page, 'border-border')]

            expect(enabled.border, 'the enabled control is the danger tone').toBe(danger)
            expect(disabled.border, 'the disabled one is the faint hairline').toBe(hairline)
          })
        }
      }

      test('an enabled native select holding a disabled option is not drawn disabled', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        const wrapper = (id) => page.getByTestId('pair-native-option-disabled').getByTestId(id).locator('[data-atom-select-native] > div:first-of-type')
        const read2 = (locator) => locator.evaluate((el) => {
          const s = getComputedStyle(el)
          return { background: s.backgroundColor, border: s.borderTopColor, cursor: s.cursor }
        })
        const [held, plain] = [await read2(wrapper('with-disabled-option')), await read2(wrapper('plain'))]

        expect(held).toEqual(plain)
        expect(held.border).toBe(await colourOf(page, 'border-control-border'))
        expect(held.cursor).not.toBe('not-allowed')
      })

      test('a filled listbox keeps its value readable and dimmer than the enabled one, as a value and as chips', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        // [test id, what holds the value's own text: the value, not the placeholder]
        for (const [id, selector, text] of [['one', '.group\\/select-selected', 'Draft'], ['many', '[x-text="item.label"]', 'Draft']]) {
          const value = (state) => page.getByTestId(`listbox-${id}-${state}`).locator(`[data-atom-select-combobox] ${selector}`).first()

          await expect(value('on'), `${id} value`).toHaveText(text)
          await expect(value('off'), `${id} value, disabled`).toHaveText(text)

          const [on, off] = [await value('on').evaluate(contrastOf, { what: 'text' }), await value('off').evaluate(contrastOf, { what: 'text' })]
          expect(off.colour, `${id}: the disabled value is the subtle foreground`).not.toBe(on.colour)
          expect(off.ratio, `${id}: and stays readable`).toBeGreaterThanOrEqual(4.5)
        }
      })

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
