import { test, expect } from '@playwright/test'
import { engineSkip, standardCases, caseOf, ROUTES, openCase, root, runGestures, walk, saw, resetValue, serverState, observe, domOf, ledgerRow } from './disabled-helpers.js'
import { LEDGER } from './disabled-ledger.js'

for (const route of ROUTES) {
  for (const def of standardCases()) {
    if (def.routes && !def.routes.includes(route)) continue
    const key = `${def.id}/${route}`

    test.describe(`${route}: ${def.id}`, () => {
      // the pair is serial: when the enabled baseline fails, the disabled half is skipped, never reported as "blocked"
      test.describe.configure({ mode: 'serial' })
      test.beforeEach(({ browserName }) => test.skip(!!engineSkip(browserName, def), engineSkip(browserName, def) ?? ''))
      test.setTimeout(70_000)

      test('enabled: every probe changes something', async ({ page, browserName }) => {
        await openCase(page, def, { route })
        await runGestures(page, def, { enabled: true })
        await resetValue(page, def)
        const w = await walk(page, def, { browserName })
        if (def.walk?.stops !== false) expect(w.stops.length, 'the keyboard reaches the control, enabled').toBeGreaterThan(0)
        for (const want of def.walk?.effect ?? []) expect(saw(w.effects, want), `the keyboard walk did not ${want}, enabled (it saw ${[...w.effects].join(', ') || 'nothing'})`).toBe(true)
        expect(page.errors, 'uncaught page errors').toEqual([])

        // the disabled page renders the same case (a fixture that cannot render it would pass every disabled row as "blocked")
        await openCase(page, def, { route, off: true })
        await expect(page.locator('[data-testid="root"]')).toHaveAttribute('data-state', 'disabled')
        expect(await root(page, def).locator('*').count(), 'the disabled page draws the control').toBeGreaterThan(0)
        expect(page.errors, 'uncaught page errors, disabled').toEqual([])
      })

      test('disabled: nothing changes', async ({ page, browserName }, info) => {
        await ledgerRow(test, expect, info, key, async () => {
          await openCase(page, def, { route, off: true })
          const [violations, details] = [new Set(), []]
          const initial = await serverState(page)

          for (const [name, effects] of Object.entries(await runGestures(page, def, { enabled: false }))) {
            for (const e of effects) { violations.add(`${name}: ${e}`); details.push(`gesture "${name}" caused ${e}`) }
          }

          const afterGestures = await serverState(page)
          await resetValue(page, def)

          const w = await walk(page, def, { browserName })
          for (const e of w.effects) { violations.add(`keyboard: ${e}`); details.push(`a key caused ${e}`) }

          const want = def.tabStops?.[route]
          if (want && JSON.stringify(w.stops) !== JSON.stringify(want)) {
            violations.add('tab stops')
            details.push(`tab stops: expected [${want.join(', ')}], got [${w.stops.join(', ')}]`)
          }

          const after = await serverState(page)
          if (JSON.stringify(afterGestures.v) !== JSON.stringify(initial.v)) { violations.add('server: value'); details.push(`after the gestures the server's value went from ${JSON.stringify(initial.v)} to ${JSON.stringify(afterGestures.v)}`) }
          if (JSON.stringify(after.v) !== JSON.stringify(initial.v)) { violations.add('server: value'); details.push(`the server's value went from ${JSON.stringify(initial.v)} to ${JSON.stringify(after.v)}`) }
          if (after.file !== initial.file || afterGestures.file !== initial.file) { violations.add('server: file'); details.push(`the server received a file: ${after.file}`) }
          if (page.errors.length) { violations.add('page error'); details.push(...page.errors) }

          return { violations, details }
        })
      })
    })
  }
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
// Push: a value that arrives from outside while the control is disabled still shows. Disabled stops the CLIENT writing the model; it must not
// freeze what the control displays. Two ways in: the server assigns the property (a real morph of the markup), and a parent's Alpine data that the
// control is `x-model`ed to (the page's own `pv`). Each is proven on an enabled control first, and that the value was not already on screen.

const readOf = async (page, def, bind) => (await observe(page, def, { bind })).dom ?? ''

/** Push a value from outside, the way `how` says, and report whether the control shows it. */
async function pushAndShow(page, def, how) {
  const bind = how === 'x-model' ? 'x' : null
  const before = await readOf(page, def, bind)
  expect(before, 'the pushed value is not on screen already (or the row proves nothing)').not.toContain(def.push.show)

  if (how === 'x-model') await page.evaluate((v) => { window.Alpine.$data(document.querySelector('[data-xmodel]')).pv = v }, def.push.value)
  else await page.evaluate((v) => window.Livewire.first().push(v), def.push.value)

  try {
    await expect.poll(() => readOf(page, def, bind), { timeout: 4000 }).toContain(def.push.show)
    return true
  } catch {
    return false
  }
}

for (const how of ['server', 'x-model']) {
  for (const route of how === 'server' ? ROUTES : ['prop']) {
    for (const def of standardCases()) {
      if (!def.push || (how === 'x-model' && !def.xmodel)) continue
      const key = `push:${def.id}/${route}/${how}`

      test.describe(`push ${route} (${how}): ${def.id}`, () => {
        test.describe.configure({ mode: 'serial' })
        test.beforeEach(({ browserName }) => test.skip(!!engineSkip(browserName, def), engineSkip(browserName, def) ?? ''))
        test.setTimeout(40_000)

        test('enabled: a pushed value shows', async ({ page }) => {
          await openCase(page, def, { route, bind: how === 'x-model' ? 'x' : null })
          expect(await pushAndShow(page, def, how), `${def.id} shows the pushed value, enabled`).toBe(true)
          expect(page.errors).toEqual([])
        })

        test('disabled: a pushed value still shows', async ({ page }, info) => {
          await ledgerRow(test, expect, info, key, async () => {
            await openCase(page, def, { route, off: true, bind: how === 'x-model' ? 'x' : null })
            const shown = await pushAndShow(page, def, how)
            return { violations: new Set(shown ? [] : ['push not shown']), details: [`the pushed value ${JSON.stringify(def.push.value)} did not reach the control`] }
          })
        })
      })
    }
  }
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
// No write on load: a value the control would rewrite into its own canonical form (a slider clamped into its range, a phone number given a dial
// code, a date-time turned into an ISO instant) is rewritten by the browser the moment the page loads, and is sent to the server as an update that
// nobody asked for. A disabled control must send none. `enabled: "writes"` says the enabled control does write it (the row then proves the probe can
// see one); `"quiet"` says it does not, and the row is a regression guard.

for (const route of ROUTES) {
  for (const def of standardCases()) {
    if (!def.noWrite) continue
    const key = `noWrite:${def.id}/${route}`

    test.describe(`no write on load ${route}: ${def.id}`, () => {
      test.describe.configure({ mode: 'serial' })
      test.beforeEach(({ browserName }) => test.skip(!!engineSkip(browserName, def), engineSkip(browserName, def) ?? ''))
      test.setTimeout(40_000)

      test('enabled: the probe sees what an enabled control does', async ({ page }) => {
        await openCase(page, def, { route, value: def.noWrite.value })
        await page.waitForTimeout(1500)
        if (def.noWrite.enabled === 'writes') expect(page.updates.length, 'the enabled control rewrites the value on load, and the probe sees the request').toBeGreaterThan(0)
        else expect(page.updates.length, 'the enabled control leaves the value alone').toBe(0)
        expect((await domOf(root(page, def))).length, 'the control is drawn').toBeGreaterThan(0)
      })

      test('disabled: it sends no update', async ({ page }, info) => {
        await ledgerRow(test, expect, info, key, async () => {
          await openCase(page, def, { route, off: true, value: def.noWrite.value })
          await page.waitForTimeout(1500)
          const [violations, details] = [new Set(), []]
          if (page.updates.length) { violations.add('writes on load'); details.push(`${page.updates.length} update request(s) went to the server on load: ${page.updates.join(' ')}`) }
          const state = await serverState(page)
          if (JSON.stringify(state.v) !== JSON.stringify(def.noWrite.value)) { violations.add('server value rewritten'); details.push(`the server's value went from ${JSON.stringify(def.noWrite.value)} to ${JSON.stringify(state.v)}`) }
          return { violations, details }
        })
      })
    })
  }
}

// ---------------------------------------------------------------------------------------------------------------------------------------------
// A Livewire child inside a disabled <atom:form>. A form hands its disabled state to a field by the view factory's component stack, which is there
// while the PARENT renders the child for the first time and is not there when the child updates and renders on its own. So: does the child keep its
// read-only state after an update of its own? (Section 11 of the plan; the answer is in the ledger when it does not.)

const child = {
  id: () => document.querySelector('[data-child]').closest('[wire\\:id]').getAttribute('wire:id'),
}

async function childProbe(page, { update }) {
  const id = await page.evaluate(child.id)
  const valueOf = () => page.evaluate((id) => window.Livewire.find(id).get('v'), id)
  const input = page.locator('[data-child-input]')

  if (update) {
    await page.locator('[data-child-bump]').click()
    await expect(page.locator('[data-child-renders]')).toHaveText('1')
    await page.waitForTimeout(250)
  }

  const readonly = (await input.getAttribute('readonly')) !== null || (await input.isDisabled())
  const before = await valueOf()
  await input.click({ force: true })
  await page.keyboard.type('zq7')
  await page.waitForTimeout(250)
  return { readonly, changed: before !== (await valueOf()) }
}

for (const update of [false, true]) {
  const def = caseOf('livewire_child')
  const when = update ? 'after an update of its own' : 'on its first render'

  test.describe(`a Livewire child in a disabled form, ${when}`, () => {
    test.describe.configure({ mode: 'serial' })
    test.beforeEach(({ browserName }) => test.skip(!!engineSkip(browserName, def), engineSkip(browserName, def) ?? ''))
    test.setTimeout(40_000)

    test('enabled: the child is editable', async ({ page }) => {
      await openCase(page, def, { route: 'form' })
      const result = await childProbe(page, { update })
      expect(result.readonly, 'the child is not read-only, enabled').toBe(false)
      expect(result.changed, 'typing changes the child, enabled').toBe(true)
    })

    test('disabled: the child keeps the form\'s read-only state', async ({ page }, info) => {
      await ledgerRow(test, expect, info, `child/${update ? 'update' : 'first'}`, async () => {
        await openCase(page, def, { route: 'form', off: true })
        const result = await childProbe(page, { update })
        const violations = new Set()
        if (!result.readonly) violations.add('child is editable')
        if (result.changed) violations.add('child value changed')
        return { violations, details: [`read-only: ${result.readonly}, typing changed it: ${result.changed}`] }
      })
    })
  })
}
