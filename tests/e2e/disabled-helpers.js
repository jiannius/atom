import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { expect } from '@playwright/test'
import { appendFileSync } from 'node:fs'
import { open } from './controls-helpers.js'
import { LEDGER } from './disabled-ledger.js'

// The probes behind disabled-behaviour.spec.js and disabled-morph.spec.js. The corpus (tests/Fixtures/disabled-controls-cases.json) is also read by
// tests/Feature/DisabledGuardTest.php, so a control exists in one place. The page is tests/Fixtures/DisabledBehaviourFixture.php: ONE case per page.
//
// What a probe sees (an "effect"): the model changing (`value`: the client's, then the server's after a round trip), the control's own DOM value changing
// (`dom`: it catches a write the model never hears of), a popup opening (`popup`), a file chooser (`chooser`) and a submit reaching the server (`submit`) and the chat's own `input` event (`sent`).
// Every probe is run ENABLED first and must show the effect it is written for. A gesture that does nothing enabled is a broken probe, and would read as
// "blocked" once the control is disabled, so the specs fail the row instead.

export const CORPUS = JSON.parse(readFileSync(join(process.cwd(), 'tests/Fixtures/disabled-controls-cases.json'), 'utf8'))
export const ROUTES = ['prop', 'form']

/**
 * Where an engine legitimately cannot run a row, and why: `{ <browserName>: { <case id>: reason } }`. A skip is a statement about the ENGINE, never a way to
 * hide a control's failure (that is the ledger's); each reason says what the engine does.
 */
export const ENGINE_SKIPS = {
  webkit: {
    time_picker: 'the picker builds dayjs("1970-01-01 7:30 AM") for every edit, which WebKit\'s Date cannot parse: it shows "--" for its own value and writes "Invalid Date" to the model on the first edit, a Safari bug in the picker (not in the rig) to be fixed on its own',
    date_picker_time: 'the date picker with a time hands Pikaday "YYYY-MM-DD HH:mm:ss", which WebKit\'s Date rejects: "RangeError: Invalid Date" on load, a Safari bug in the picker (not in the rig) to be fixed on its own',
  },
}

/** The reason a case cannot run in this engine, or null. */
export const engineSkip = (browserName, def) => ENGINE_SKIPS[browserName]?.[def.id] ?? null

/** A case by id. */
export const caseOf = (id) => CORPUS.find((c) => c.id === id)

/** The cases that go through the standard gestures (the special ones have a spec of their own). */
export const standardCases = () => CORPUS.filter((c) => !c.special)

/**
 * Open one case on the consumer's Tailwind: `route` (`prop`: the control carries the disabled prop; `form`: it inherits `<atom:form disabled>`),
 * `off` (render it disabled), `bind` (`x`: the value lives in the page's Alpine data and the control is `x-model`ed to it), `value` (a JSON value
 * instead of the case's own). Resolves once Livewire and Alpine are up and the page has settled; collects uncaught page errors on `page.errors`.
 */
export async function openCase(page, def, { route = 'prop', off = false, bind = null, value = undefined, tw = 'latest' } = {}) {
  // the page's listeners are added once, however many times a row opens a case; what they collect starts again with each open
  page.errors = []
  page.updates = []
  page.choosers = 0
  if (!page.__disabledRig) {
    page.__disabledRig = true
    // a request cut off by a navigation is reported by the old page as this in WebKit (its wording), and only there: not an error of the case
    const webkit = page.context().browser()?.browserType().name() === 'webkit'
    page.on('pageerror', (e) => { if (!(webkit && /Fetch API cannot load .* due to access control checks|Load failed/.test(String(e)))) page.errors.push(String(e)) })
    // a Livewire update request is a POST to the component update endpoint
    page.on('request', (r) => {
      if (r.method() === 'POST' && /\/livewire[^/]*\/update/.test(r.url())) page.updates.push(r.postData() ?? '')
    })
    page.on('filechooser', () => page.choosers++)
    // the chat composer tells its host by an `input` event carrying { body, files }, and writes no model of its own
    await page.addInitScript(() => {
      window.__sent = 0
      document.addEventListener('input', (e) => { if (e.detail && typeof e.detail === 'object' && 'body' in e.detail) window.__sent++ }, true)
    })
  }

  const extra = `&case=${encodeURIComponent(def.id)}&route=${route}${off ? '&off=1' : ''}${bind ? `&bind=${bind}` : ''}${value !== undefined ? `&value=${encodeURIComponent(JSON.stringify(value))}` : ''}`
  await open(page, { path: 'disabled-behaviour', tw, extra })
  // a page that did not render (a 500, a case the fixture refuses) fails here, in seconds, with the row's name: it must not read as a control that does nothing
  await page.waitForFunction(() => window.Livewire && window.Livewire.first() && window.Alpine, null, { timeout: 15_000 })
  await page.waitForSelector('[data-testid="root"]', { timeout: 5_000 })
  // Alpine's init of the controls, then the first paint
  await page.waitForTimeout(350)
}

export const root = (page, def) => page.locator(`[data-case="${def.id}"]`)

/** What the server holds, after a round trip. */
export const serverState = (page) => page.evaluate(() => window.Livewire.first().snapshot())

/** Flip disabled on the server and wait for the morph to land. */
export async function flip(page) {
  const was = await page.locator('[data-testid="root"]').getAttribute('data-state')
  await page.evaluate(() => window.Livewire.first().flip())
  await expect(page.locator('[data-testid="root"]')).not.toHaveAttribute('data-state', was)
  await page.waitForTimeout(250)
}

/** How a case's DOM value is read: `{ selector, prop }` with prop `value`, `checked`, `text` or `attr:<name>`. */
const READ = ({ read, id }) => {
  if (!read) return null
  const el = document.querySelector(`[data-case="${id}"] ${read.selector}`)
  if (!el) return '<absent>'
  if (read.prop === 'value') return el.value
  if (read.prop === 'checked') return String(el.checked)
  if (read.prop === 'text') return el.innerText.trim()
  if (read.prop.startsWith('attr:')) return el.getAttribute(read.prop.slice(5))
  return null
}

/** One look at everything a gesture can change: the model as the client holds it, the control's DOM value and the popups that are open. */
export async function observe(page, def, { bind = null } = {}) {
  return page.evaluate(({ def, bind, read }) => {
    const reader = new Function('arg', `return (${read})(arg)`)
    let value
    if (bind) value = window.Alpine.$data(document.querySelector('[data-xmodel]')).pv
    else value = window.Livewire.first().get('v')
    const popups = document.querySelectorAll(':popover-open').length + document.querySelectorAll('dialog[open]').length + (CSS.supports('selector(:open)') ? document.querySelectorAll('select:open').length : 0)
    // a file model counts as the value too
    const file = bind ? null : window.Livewire.first().get('file')
    return { value: JSON.stringify([value ?? null, file ?? null]), dom: reader({ read: def.read, id: def.id }), popups, submits: Number(document.querySelector('[data-submits]').textContent), sent: window.__sent }
  }, { def: { id: def.id, read: def.read ?? null }, bind, read: READ.toString() })
}

/** A step of a gesture: a real mouse click at a point of a target, keys typed or pressed, or a drop of a file on a target. */
async function step(page, def, st, { enabled }) {
  const scope = root(page, def)

  if (st.click !== undefined || st.dblclick !== undefined) {
    const loc = scope.locator(st.click ?? st.dblclick).nth(st.nth ?? 0)
    const there = (await loc.count()) > 0 && (await loc.isVisible())
    if (!there) {
      // enabled, a target that is not there is a broken probe. Disabled, only a step the corpus marks `mayHide` may find its target gone (a control may hide
      // what it must not offer, or the popup it sits in did not open); any other absence is a selector that matches nothing and would read as "blocked"
      if (enabled) throw new Error(`probe target "${st.click ?? st.dblclick}" is not on the page, enabled`)
      if (!st.mayHide) throw new Error(`probe target "${st.click ?? st.dblclick}" is not on the page, disabled, and its step is not marked mayHide in the corpus`)
      return 'absent'
    }
    await loc.scrollIntoViewIfNeeded()
    // a person's pointer arrives before the click, and what a hover opens (a tooltip, a hover state) can move the target: aim at where it ends up
    let box = await loc.boundingBox()
    await page.mouse.move(box.x + box.width * (st.pos ?? 0.5), box.y + box.height / 2)
    await page.waitForTimeout(120)
    box = await loc.boundingBox()
    const [x, y] = [box.x + box.width * (st.pos ?? 0.5), box.y + box.height / 2]
    if (st.dblclick !== undefined) await page.mouse.dblclick(x, y)
    else await page.mouse.click(x, y)
  } else if (st.type !== undefined) {
    await page.keyboard.type(st.type, { delay: 15 })
  } else if (st.press !== undefined) {
    await page.keyboard.press(st.press)
  } else if (st.drop !== undefined) {
    const loc = scope.locator(st.drop).first()
    if (!(await loc.count())) throw new Error(`probe target "${st.drop}" is not on the page`)
    // a real drag from the desktop cannot be driven: the event a browser fires for it, with a file in it
    await loc.evaluate((el) => {
      const dt = new DataTransfer()
      dt.items.add(new File(['abc'], 'a.txt', { type: 'text/plain' }))
      for (const type of ['dragenter', 'dragover', 'drop']) el.dispatchEvent(new DragEvent(type, { dataTransfer: dt, bubbles: true, cancelable: true }))
    })
  } else {
    throw new Error(`unknown probe step ${JSON.stringify(st)}`)
  }

  await page.waitForTimeout(st.wait ?? 90)
  return 'done'
}

/** Close whatever a gesture opened, so the next one starts from the page and not from a popup. */
async function tidy(page) {
  await page.keyboard.press('Escape')
  await page.mouse.click(2, 2)
  await page.waitForTimeout(60)
}

/** Whether the effects seen include `want`: one effect, or a list of which any one will do (a Mac opens a native select on ArrowDown, Linux changes its value). */
export const saw = (effects, want) => (Array.isArray(want) ? want.some((w) => effects.has(w)) : effects.has(want))

/** What changed between two looks, as effects. */
const effectsBetween = (before, after, page, choosersBefore) => {
  const out = new Set()
  if (before.value !== after.value) out.add('value')
  if (before.dom !== after.dom) out.add('dom')
  if (after.popups > before.popups) out.add('popup')
  if (after.submits > before.submits) out.add('submit')
  if (after.sent > before.sent) out.add('sent')
  if (page.choosers > choosersBefore) out.add('chooser')
  return out
}

/**
 * Run every mouse gesture of a case once. Returns `{ name: Set<effect> }`. In the enabled run it throws if a gesture did not do what the corpus says
 * it does (so a probe that lands on nothing cannot pass as "blocked" later).
 */
export async function runGestures(page, def, { enabled, bind = null, keepOpen = false }) {
  const out = {}

  for (const g of def.gestures ?? []) {
    const before = await observe(page, def, { bind })
    const choosers = page.choosers
    for (const st of g.steps) await step(page, def, st, { enabled })
    const after = await observe(page, def, { bind })
    const effects = effectsBetween(before, after, page, choosers)
    out[g.name] = effects

    if (enabled) {
      for (const want of g.effect) {
        expect(saw(effects, want), `the probe "${g.name}" did not ${want}, enabled (it saw ${[...effects].join(', ') || 'nothing'}): a broken probe reads as "blocked"`).toBe(true)
      }
    }

    // a popup the gesture left open would colour the next one
    if (after.popups > 0 && !keepOpen) await tidy(page)
    else await page.waitForTimeout(40)
  }

  return out
}

/** The same descriptor for a tab stop in every engine. */
const DESCRIBE = (el) => {
  const attr = (n) => el.getAttribute(n)
  return el.tagName.toLowerCase() + (attr('type') ? `:${attr('type')}` : '') + (attr('role') ? `[${attr('role')}]` : '') + (attr('aria-label') ? `(${attr('aria-label')})` : '')
}

/**
 * Walk the keyboard through the case: Tab from the sentinel before it to the sentinel after, noting every stop inside, then press each key of
 * the battery on each stop. WebKit's Tab skips buttons and links, so it walks with Alt+Tab there (and reaches the same controls).
 * Returns `{ stops: [descriptor], effects: Set<effect> }`. A key that moves focus off the stop is followed by walking back to it.
 */
export async function walk(page, def, { browserName, bind = null }) {
  const TAB = browserName === 'webkit' ? 'Alt+Tab' : 'Tab'
  const keys = def.walk?.keys ?? ['x', '5', 'ArrowDown', 'Enter', 'Space', 'ArrowDown', 'ArrowUp', 'ArrowRight', 'Backspace', 's', 'Escape']
  const scope = root(page, def)
  const effects = new Set()

  const where = () => page.evaluate((id) => {
    const a = document.activeElement
    const inside = !!a && !!document.querySelector(`[data-case="${id}"]`)?.contains(a)
    return { inside, after: a?.dataset?.sentinel === 'after', stamp: a?.__atomStop ?? null }
  }, def.id)

  // discover the stops
  await page.locator('[data-sentinel="before"]').focus()
  const stops = []
  for (let i = 0; i < 14; i++) {
    await page.keyboard.press(TAB)
    const at = await where()
    if (at.after || (!at.inside && i > 0)) break
    if (at.inside) {
      stops.push(await page.evaluate((fnSrc) => {
        const a = document.activeElement
        a.__atomStop = true
        return new Function('el', `return (${fnSrc})(el)`)(a)
      }, DESCRIBE.toString()))
    }
  }

  // press the battery on each stop
  const reach = async (index) => {
    await page.locator('[data-sentinel="before"]').focus()
    for (let i = 0; i <= index; i++) await page.keyboard.press(TAB)
  }

  for (let index = 0; index < stops.length; index++) {
    await reach(index)

    for (const key of keys) {
      // WebKit goes Back on a Backspace nobody is editing with, and takes the page (and the rest of the row) with it: Backspace is only pressed where it edits
      if (key === 'Backspace' && !(await page.evaluate((webkit) => {
        const a = document.activeElement
        // a read-only field edits nothing, so Backspace there is Back as well
        return !!a && !(webkit && a.readOnly) && (a.isContentEditable || a.tagName === 'TEXTAREA' || (a.tagName === 'INPUT' && ['text', 'search', 'email', 'number', 'password', 'tel', 'url', ''].includes(a.type)))
      }, browserName === 'webkit'))) continue

      const before = await observe(page, def, { bind })
      const choosers = page.choosers
      await page.keyboard.press(key)
      await page.waitForTimeout(60)
      const after = await observe(page, def, { bind })
      for (const e of effectsBetween(before, after, page, choosers)) effects.add(e)

      if (process.env.DISABLED_DEBUG && (after.popups || after.value !== before.value || after.dom !== before.dom)) console.log(`  [walk ${def.id}] ${key}: popups ${before.popups}->${after.popups} value ${before.value}->${after.value} dom ${before.dom}->${after.dom}`)

      const at = await where()
      if (!at.inside || at.stamp !== true) {
        await reach(index)
      }
    }
  }

  return { stops, effects }
}

/** Put the server's value back to the case's own, between the mouse gestures and the keyboard walk (a gesture that picked "Paid" must not make the key that picks it a no-op). */
export async function resetValue(page, def) {
  if (def.model === 'file') return
  await page.evaluate((v) => window.Livewire.first().push(v), def.value ?? null)
  await page.waitForTimeout(250)
}

/** The server's value as a JSON string, after a round trip. */
export async function serverValue(page) {
  return JSON.stringify((await serverState(page)).v ?? null)
}

/**
 * Run a row's contract probe, ordinary or in the ledger.
 *
 * `probe()` returns `{ violations: Set<string>, details: string[] }`: what the control still does, or still lacks, while disabled. An ordinary row must
 * have none. A ledger row (an `entry` in disabled-ledger.js: `{ pr, violations, why }`) is today's known failure: it is marked `test.fail()`, and it
 * passes only when `probe()` finds EXACTLY the violations the ledger names. Anything else makes the marked test pass its body, which `test.fail()`
 * reports as "unexpectedly passed", and so red:
 *   - the fix landed, or part of it: the violations are fewer than the ledger says, so the mark (or a name) must come out of the ledger with the fix;
 *   - the row broke some other way (a fixture that no longer renders, a locator that timed out, a hang): an error or a violation the ledger does
 *     not name. A plain `test.fail()` would count that as the expected failure and hide it; here it is red, with the reason in the row's annotations.
 * The enabled baseline is a test of its own beside this one (see the spec), so a fixture that is broken for the ENABLED control fails it directly.
 */
export async function ledgerRow(test, expect, info, key, probe, { ledger = LEDGER, deadline = 50_000 } = {}) {
  const entry = ledger[key]

  // `DISABLED_LEDGER_DUMP=<file>` writes what every row finds instead of judging it: the way a ledger entry is drafted for a new row, never on CI
  if (process.env.DISABLED_LEDGER_DUMP) {
    if (process.env.CI) throw new Error('DISABLED_LEDGER_DUMP judges nothing, so it must never be set on CI')
    const { violations } = await probe()
    appendFileSync(process.env.DISABLED_LEDGER_DUMP, `${JSON.stringify({ key, ledger: ledger === LEDGER ? 'behaviour' : 'look', violations: [...violations].sort() })}\n`)
    return
  }

  // an engine that differs from Chromium in a way that is its own has its own list: `engines: { webkit: { violations: [...] } }` (empty: it keeps the contract there)
  const engine = info.project.use.browserName ?? info.project.use.defaultBrowserType ?? 'chromium'
  const expected = entry ? [...(entry.engines?.[engine]?.violations ?? entry.violations)].sort() : []

  if (!expected.length) {
    const { violations, details } = await probe()
    expect([...violations].sort(), details.join('\n')).toEqual([])
    return
  }

  test.fail(true, `known failure, PR ${entry.pr}: ${entry.why}`)

  let found
  try {
    found = await Promise.race([probe(), new Promise((_, reject) => setTimeout(() => reject(new Error(`the probe did not finish in ${deadline} ms`)), deadline))])
  } catch (e) {
    info.annotations.push({ type: 'wrong-reason', description: `the ledger row failed for a reason that is not a violation: ${e.message}` })
    console.error(`[ledger] ${info.title}: ${e.message}`)
    return
  }

  const got = [...found.violations].sort()
  if (JSON.stringify(got) !== JSON.stringify(expected)) {
    info.annotations.push({ type: 'ledger-mismatch', description: `ledger names ${JSON.stringify(expected)}, the control shows ${JSON.stringify(got)}:\n${found.details.join('\n')}` })
    console.error(`[ledger] ${info.title}: ledger names ${JSON.stringify(expected)}, the control shows ${JSON.stringify(got)}`)
    return
  }

  // the expected failure: the contract is not kept, in exactly the way the ledger says
  throw new Error(`known failure (PR ${entry.pr}): ${got.join('; ')}\n${found.details.join('\n')}`)
}

/**
 * What a control looks like, as a list: for the element and every element under it, its text colour, fill, border colour, cursor and the opacity it is
 * painted at (its own times every ancestor's). Two controls with the same signature look the same, whatever classes made them so.
 */
export const lookOf = (locator) => locator.evaluate((root) => {
  const opacityOf = (el) => {
    let o = 1
    for (let n = el; n && n !== document.documentElement; n = n.parentElement) o *= parseFloat(getComputedStyle(n).opacity)
    return +o.toFixed(3)
  }
  const rows = []
  for (const el of [root, ...root.querySelectorAll('*')]) {
    if (['SCRIPT', 'TEMPLATE', 'STYLE', 'PATH', 'CIRCLE', 'SVG', 'G', 'LINK'].includes(el.tagName.toUpperCase())) continue
    const s = getComputedStyle(el)
    if (s.display === 'none') continue
    rows.push([el.tagName.toLowerCase(), s.color, s.backgroundColor, parseFloat(s.borderTopWidth) > 0 ? s.borderTopColor : '', s.cursor, opacityOf(el)].join(' | '))
  }
  return rows
})

/** Where two looks differ, for a failure message. */
export const lookDiff = (a, b) => {
  const out = []
  for (let i = 0; i < Math.max(a.length, b.length); i++) if (a[i] !== b[i]) out.push(`${a[i] ?? '(none)'}   vs   ${b[i] ?? '(none)'}`)
  return out.slice(0, 4)
}

/**
 * Take the focus and the pointer off the case, and let its transitions finish, so a read of its DOM sees state and not interaction (see domOf).
 */
export async function calm(page) {
  // a focused field draws its focus border and a hovered one its hover state: neither is a disabled state, and a static render has neither
  await page.evaluate(() => document.activeElement?.blur?.())
  await page.mouse.move(2, 2)
  await page.evaluate(() => Promise.all(document.getAnimations().map((a) => a.finished.catch(() => null))))
  await page.waitForTimeout(80)
}

/**
 * The state of a case's DOM that says whether it is disabled, as a list: for every element that is drawn, the attributes that carry a disabled state
 * and what the browser computes (colours, cursor, the opacity it is painted at, whether the mouse can reach it). A morph into a state must give the
 * list a static render of that state gives.
 */
export const domOf = (locator) => locator.evaluate((root) => {
  const opacityOf = (el) => {
    let o = 1
    for (let n = el; n && n !== document.documentElement; n = n.parentElement) o *= parseFloat(getComputedStyle(n).opacity)
    return +o.toFixed(3)
  }
  const reachable = (el) => {
    for (let n = el; n && n !== document.documentElement; n = n.parentElement) if (getComputedStyle(n).pointerEvents === 'none') return false
    return true
  }
  const rows = []
  for (const el of [root, ...root.querySelectorAll('*')]) {
    if (['SCRIPT', 'TEMPLATE', 'STYLE', 'LINK'].includes(el.tagName.toUpperCase())) continue
    const s = getComputedStyle(el)
    if (s.display === 'none' || s.visibility === 'hidden') continue
    const attrs = ['disabled', 'readonly', 'aria-disabled', 'aria-readonly', 'tabindex', 'contenteditable', 'type'].filter((a) => el.hasAttribute(a)).map((a) => `${a}=${el.getAttribute(a)}`)
    // an input's own value is state, not a disabled mark: it is left out so a flip that keeps the typed text is not reported
    rows.push([el.tagName.toLowerCase(), attrs.join(','), s.color, s.backgroundColor, parseFloat(s.borderTopWidth) > 0 ? s.borderTopColor : '', s.cursor, opacityOf(el), reachable(el) ? 'mouse' : 'no-mouse'].join(' | '))
  }
  return rows
})
