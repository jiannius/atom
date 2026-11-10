import { test, expect } from '@playwright/test'
import { open, contrastOf } from './controls-helpers.js'
import { lookOf } from './disabled-helpers.js'

// What `<atom:form disabled>` draws once its `opacity-70` is gone: the form dims nothing itself, a field in it is drawn as that field with its own
// `disabled` (the tokens-disabled-looks.spec.js "form-*" rows hold that), a focused one shows the focus border, its prefix box agrees with it, and
// a host's own `readonly` is not a disabled state. Run `node tests/consumer/build.mjs` first.

const colourOf = (page, cls) => page.evaluate((cls) => {
  const probe = document.createElement('div')
  probe.className = `border ${cls}`
  document.body.append(probe)
  const colour = getComputedStyle(probe).borderTopColor
  probe.remove()
  return colour
}, cls)

const border = (locator) => locator.evaluate((el) => getComputedStyle(el).borderTopColor)

for (const tw of ['floor', 'latest']) {
  for (const dark of [true, false]) {
    test.describe(`on the ${tw} build, ${dark ? 'dark' : 'light'}`, () => {
      test('the form dims nothing: no opacity on it, a label and a caption as strong as an enabled field\'s', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        const form = page.getByTestId('pair-form-label').locator('form')
        expect(await form.evaluate((el) => getComputedStyle(el).opacity), 'the form').toBe('1')
        // a stale consumer build has no `opacity-70` rule, so the computed value alone would not tell
        expect(await form.evaluate((el) => [...el.classList].filter((c) => c.startsWith('opacity-'))), 'no opacity class on the form').toEqual([])

        const [on, off] = [await lookOf(page.getByTestId('pair-form-label').getByTestId('on')), await lookOf(page.getByTestId('pair-form-label').getByTestId('off'))]
        // everything but the field itself (the input's own row differs, so it is held by the look rows): label, caption and wrappers
        const nonField = (rows) => rows.filter((row) => !row.startsWith('input |'))
        expect(nonField(off), 'the label and caption look as they do in an enabled field').toEqual(nonField(on))
      })

      test('a host\'s readonly looks as an enabled field does', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        for (const pair of ['readonly-input', 'readonly-textarea']) {
          const [on, off] = [await lookOf(page.getByTestId(`pair-${pair}`).getByTestId('on')), await lookOf(page.getByTestId(`pair-${pair}`).getByTestId('off'))]
          expect(on.length, `${pair} rendered`).toBeGreaterThan(0)
          expect(off, pair).toEqual(on)
        }
      })

      // #187: the transparent variant has no border or fill to show it, so its text is the cue
      test('a disabled transparent textarea has dimmer text than an enabled one, in its own `disabled` and in a disabled form', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        const text = (pair, state) => page.getByTestId(`pair-${pair}`).getByTestId(state).evaluate(contrastOf, { what: 'text' })
        const enabled = await text('textarea-transparent', 'on')
        const own = await text('textarea-transparent', 'off')
        const inherited = await text('form-textarea-transparent', 'off')

        expect(own.colour, 'its own disabled').not.toBe(enabled.colour)
        expect(inherited.colour, 'in a disabled form').toBe(own.colour)
        expect(own.ratio, 'and stays readable').toBeGreaterThanOrEqual(4.5)
      })

      test('a focused field of a disabled form shows the focus border and the ring, not the faint one', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        // the focus colour is what an enabled field of the same kind shows when focused (there is no `border-focus` utility to probe)
        for (const [pair, reference] of [['form-input', 'readonly-input'], ['form-textarea', 'readonly-textarea']]) {
          const enabled = page.getByTestId(`pair-${reference}`).getByTestId('on')
          await enabled.focus()
          const focus = await border(enabled)
          await enabled.evaluate((e) => e.blur())
          expect(focus, 'the focus border is not the faint hairline').not.toBe(await colourOf(page, 'border-border'))

          // the test id is on the field itself
          const el = page.getByTestId(`pair-${pair}`).getByTestId('off')
          await el.focus()
          await expect(el, `${pair} is focused`).toBeFocused()
          expect(await border(el), `${pair}: the border`).toBe(focus)
          expect(await el.evaluate((e) => getComputedStyle(e).outlineStyle), `${pair}: the ring`).not.toBe('none')
          await el.evaluate((e) => e.blur())
          expect(await border(el), `${pair}: faint again when blurred`).toBe(await colourOf(page, 'border-border'))
        }
      })

      test('its text stays readable', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        for (const pair of ['form-input', 'form-textarea']) {
          const text = (await page.getByTestId(`pair-${pair}`).getByTestId('off').evaluate(contrastOf, { what: 'text' })).ratio
          expect(text, `${pair} text`).toBeGreaterThanOrEqual(4.5)
        }
      })

      test('the prefix and suffix boxes take the field\'s faint border, and its focus border when it is focused', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        const faint = await colourOf(page, 'border-border')
        const reference = page.getByTestId('pair-readonly-input').getByTestId('on')
        await reference.focus()
        const focus = await border(reference)
        await reference.evaluate((e) => e.blur())
        for (const state of ['on', 'off']) {
          const root = page.getByTestId('pair-prefix').getByTestId(state)
          const [prefix, suffix, field] = [root.locator('.rounded-l-control'), root.locator('.rounded-r-control'), root.locator('input')]

          expect(await border(prefix), `${state}: the prefix`).toBe(faint)
          expect(await border(suffix), `${state}: the suffix`).toBe(faint)
          // a form's field is focusable (it is read-only); its own `disabled` is not
          if (state === 'off') {
            await field.focus()
            expect(await border(prefix), 'the prefix, field focused').toBe(focus)
            expect(await border(suffix), 'the suffix, field focused').toBe(focus)
          }
        }

      })

      test('an enabled prefixed field keeps the strong border inside a host group that holds a disabled field', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        const strong = await colourOf(page, 'border-control-border')
        const box = page.getByTestId('pair-prefix-leak').getByTestId('on').locator('.rounded-l-control')
        await expect(box).toHaveCount(1)
        expect(await border(box), 'the prefix box').toBe(strong)
      })

      test('a submit button in a disabled form is disabled, another button is not, and its own :disabled="false" wins', async ({ page }) => {
        await open(page, { path: 'disabled-controls', tw, dark })

        const form = page.getByTestId('form-buttons')
        await expect(form.getByTestId('submit')).toBeDisabled()
        await expect(form.getByTestId('plain')).toBeEnabled()
        await expect(form.getByTestId('own')).toBeEnabled()
      })
    })
  }
}
