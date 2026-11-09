import { test, expect } from '@playwright/test'
import { standardCases, ROUTES, openCase, root, flip, domOf, calm, lookDiff, observe, runGestures, resetValue, ledgerRow, saw } from './disabled-helpers.js'
import { LEDGER } from './disabled-ledger.js'

// A Livewire morph that flips a control disabled, then enabled again, through the server (`flip()` on the fixture): the control must end up exactly as
// a page that was rendered in that state, behave as it does in that state, and an open popup must close when it is disabled under it.
// Findings 11 (the autoresize textarea does not follow) and 12 (an open popover stays open) were found here by hand.
//
// The pair is serial like disabled-behaviour.spec.js's: the first test proves the fixture can flip the case at all; the second, in the ledger while
// it is known to fail, asserts the three things above.

for (const route of ROUTES) {
  for (const def of standardCases()) {
    if (def.routes && !def.routes.includes(route)) continue
    const key = `morph:${def.id}/${route}`
    // the mouse gestures a static disabled render of this row is already known to fail (disabled-behaviour's ledger): a morph may not add to them
    const known = new Set((LEDGER[`${def.id}/${route}`]?.violations ?? []))
    const popupGesture = (def.gestures ?? []).find((g) => g.effect.includes('popup'))

    test.describe(`morph ${route}: ${def.id}`, () => {
      test.describe.configure({ mode: 'serial' })
      test.setTimeout(70_000)

      test('enabled: the fixture flips it on, off and on', async ({ page }) => {
        await openCase(page, def, { route })
        await expect(page.locator('[data-testid="root"]')).toHaveAttribute('data-state', 'enabled')
        await flip(page)
        await expect(page.locator('[data-testid="root"]')).toHaveAttribute('data-state', 'disabled')
        expect((await domOf(root(page, def))).length, 'the case is still drawn after the morph').toBeGreaterThan(0)
        await flip(page)
        await expect(page.locator('[data-testid="root"]')).toHaveAttribute('data-state', 'enabled')
        expect(page.errors, 'uncaught page errors').toEqual([])

        // and the same gesture that works on a static enabled page works after a round trip through disabled
        if (def.gestures?.length) await runGestures(page, { ...def, gestures: def.gestures.slice(0, 1) }, { enabled: true })
      })

      test('disabled: it follows the morph', async ({ page }, info) => {
        await ledgerRow(test, expect, info, key, async () => {
          const [violations, details] = [new Set(), []]

          // the reference renders: a page loaded enabled and one loaded disabled
          await openCase(page, def, { route })
          await calm(page)
          const staticOn = await domOf(root(page, def))
          await openCase(page, def, { route, off: true })
          await calm(page)
          const staticOff = await domOf(root(page, def))
          if (!staticOn.length || !staticOff.length) throw new Error('the static render drew nothing')

          // 1. a popup that is open when the control is disabled closes
          await openCase(page, def, { route })
          if (popupGesture) {
            const g = { ...popupGesture, effect: [] }
            await runGestures(page, { ...def, gestures: [g] }, { enabled: false, keepOpen: true })
            if ((await observe(page, def)).popups > 0) {
              await flip(page)
              if ((await observe(page, def)).popups > 0) { violations.add('popup stays open'); details.push('a popup that was open stayed open when a morph disabled the control') }
            }
            await openCase(page, def, { route })
          }

          // 2. the DOM after the morph is the DOM of a static render of that state, and again after the way back
          await flip(page)
          await calm(page)
          const morphedOff = await domOf(root(page, def))
          const offDiff = lookDiff(morphedOff, staticOff)
          if (JSON.stringify(morphedOff) !== JSON.stringify(staticOff)) { violations.add('disabled DOM differs from a static render'); details.push(...offDiff) }

          // 3. what a mouse can do to it follows: no effect a static disabled render does not already have in the ledger
          const effects = await runGestures(page, def, { enabled: false })
          for (const [name, set] of Object.entries(effects)) for (const e of set) {
            if (!known.has(`${name}: ${e}`)) { violations.add(`after the morph: ${name}: ${e}`); details.push(`gesture "${name}" caused ${e} after the morph`) }
          }

          await resetValue(page, def)
          await flip(page)
          await calm(page)
          const morphedOn = await domOf(root(page, def))
          if (JSON.stringify(morphedOn) !== JSON.stringify(staticOn)) { violations.add('re-enabled DOM differs from a static render'); details.push(...lookDiff(morphedOn, staticOn)) }

          if (page.errors.length) { violations.add('page error'); details.push(...page.errors) }

          return { violations, details }
        })
      })
    })
  }
}
