import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'
import { ledgerRow, lookOf, lookDiff } from './disabled-helpers.js'
import { LOOK_LEDGER, LOOK_DIFFERS, LOOK_FORM_MATCHES } from './disabled-ledger.js'

// The look of the disabled state beside the enabled one, for the controls tests/e2e/disabled-controls.spec.js does not hold: the choice controls (which dim
// their own box, as they do today), the controls with no disabled look yet, and a field in a disabled <atom:form> against one with its own `disabled`. A file
// of its own so CI's shards, which split by file, can place it apart from the rig's behaviour specs. Run `node tests/consumer/build.mjs` first.

for (const tw of ['floor', 'latest']) {
  for (const dark of [true, false]) {
    test.describe(`on the ${tw} build, ${dark ? 'dark' : 'light'}`, () => {
      // The choice controls dim their own box (a checkbox, a radio, a switch, a slider), and the recipe above is for text fields: this holds that, today.
      for (const pair of ['checkbox', 'radio', 'toggle', 'slider']) {
        test(`${pair}: disabled is told from enabled`, async ({ page }) => {
          await open(page, { path: 'disabled-controls', tw, dark })

          const [on, off] = [await lookOf(page.getByTestId(`pair-${pair}`).getByTestId('on')), await lookOf(page.getByTestId(`pair-${pair}`).getByTestId('off'))]
          expect(on.length, 'the pair rendered').toBeGreaterThan(0)
          expect(off, `${pair}: the disabled one looks different`).not.toEqual(on)
        })
      }

      // Controls with no disabled look yet, and a field in a disabled form against the same field with its own `disabled`. A row is in the ledger
      // (disabled-ledger.js) while it is known to fail, with the pull request that removes it; it must fail in exactly the way the ledger says.
      const looks = [
        ...LOOK_DIFFERS.map((pair) => ({ pair, same: false, name: `${pair}: disabled looks different from enabled` })),
        ...LOOK_FORM_MATCHES.map((pair) => ({ pair: `form-${pair}`, same: true, name: `${pair}: in a disabled form it looks as it does with its own disabled` })),
      ]

      for (const { pair, same, name } of looks) {
        test(name, async ({ page }, info) => {
          await ledgerRow(test, expect, info, pair, async () => {
            await open(page, { path: 'disabled-controls', tw, dark })
            // the editors draw a skeleton until they have started: read them once both have (or one is read mid-start and the pair differs by that)
            if (['tiptap', 'chat'].includes(pair)) {
              await expect(page.getByTestId(`pair-${pair}`).locator('.ProseMirror')).toHaveCount(2)
              await page.waitForTimeout(400)
            }
            await page.evaluate(() => Promise.all(document.getAnimations().map((a) => a.finished.catch(() => null))))

            const [on, off] = [await lookOf(page.getByTestId(`pair-${pair}`).getByTestId('on')), await lookOf(page.getByTestId(`pair-${pair}`).getByTestId('off'))]
            // an empty look is a locator that found nothing, which is an error and not a verdict
            if (!on.length || !off.length) throw new Error(`pair-${pair} did not render`)

            const equal = JSON.stringify(on) === JSON.stringify(off)
            const violation = same ? (equal ? [] : ['looks different']) : (equal ? ['looks enabled'] : [])
            return { violations: new Set(violation), details: same ? lookDiff(on, off) : ['the disabled control looks the same as the enabled one'] }
          }, { ledger: LOOK_LEDGER })
        })
      }
    })
  }
}
