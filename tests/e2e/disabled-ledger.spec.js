import { test, expect } from '@playwright/test'
import { CORPUS, ROUTES, ENGINE_SKIPS, ENGINE_GESTURE_SKIPS } from './disabled-helpers.js'
import { LEDGER, LOOK_LEDGER, LOOK_DIFFERS, LOOK_FORM_MATCHES } from './disabled-ledger.js'

// The ledger names rows by string. A case renamed in the corpus, a route dropped from it, or a row that stops existing would leave an entry that no row
// reads: the row would then run as an ordinary one and go red for a reason that points nowhere, or (worse, for a row that no longer exists) the entry would
// sit in the ledger forever. This holds every key, every engine and every skip to a row that exists. It needs no browser.

const byId = Object.fromEntries(CORPUS.map((c) => [c.id, c]))
const routesOf = (def) => def.routes ?? ROUTES

/** The row a ledger key names: null if there is one, otherwise what is wrong. */
function problemWith(key) {
  if (key === 'child/first' || key === 'child/update') return byId.livewire_child ? null : 'the case livewire_child is gone'

  const m = key.match(/^(?:(morph|noWrite|push):)?([a-z0-9_]+)\/(prop|form)(?:\/(server|x-model))?$/)
  if (!m) return 'not a key of any row'

  const [, kind, id, route, how] = m
  const def = byId[id]
  if (!def) return `there is no case "${id}"`
  if (def.special) return `"${id}" is a special case: it has no standard rows`
  if (!routesOf(def).includes(route)) return `the case "${id}" has no ${route} route`
  if (kind === 'noWrite' && !def.noWrite) return `the case "${id}" has no noWrite`
  if (kind === 'push' && !def.push) return `the case "${id}" has no push`
  if (kind === 'push' && !how) return 'a push key ends in /server or /x-model'
  if (kind === 'push' && how === 'x-model' && (!def.xmodel || route !== 'prop')) return `the case "${id}" has no x-model push row on the ${route} route`
  if (kind !== 'push' && how) return 'only a push key has a /server or /x-model'

  return null
}

test.describe('the ledger names rows that exist', () => {
  test('every behaviour, morph, no-write, push and child key is a row', () => {
    const bad = Object.keys(LEDGER).map((key) => [key, problemWith(key)]).filter(([, p]) => p)
    expect(bad, 'ledger keys that name no row').toEqual([])
  })

  test('every look key is a look row', () => {
    const rows = [...LOOK_DIFFERS, ...LOOK_FORM_MATCHES.map((p) => `form-${p}`)]
    expect(Object.keys(LOOK_LEDGER).filter((key) => !rows.includes(key)), 'look ledger keys that name no row').toEqual([])
  })

  test('every entry names a pull request, a reason and what it expects, in a known engine', () => {
    const engines = ['chromium', 'webkit', 'firefox']

    for (const [key, entry] of [...Object.entries(LEDGER), ...Object.entries(LOOK_LEDGER)]) {
      expect(entry.pr, `${key}: pr`).toBeTruthy()
      expect(entry.why, `${key}: why`).toBeTruthy()
      expect(Array.isArray(entry.violations), `${key}: violations is a list`).toBe(true)

      const overrides = Object.entries(entry.engines ?? {})
      // an entry that expects no failure anywhere is not a ledger entry
      expect(entry.violations.length + overrides.reduce((n, [, e]) => n + e.violations.length, 0), `${key}: expects some failure`).toBeGreaterThan(0)

      for (const [engine, e] of overrides) {
        expect(engines, `${key}: engine ${engine}`).toContain(engine)
        expect(Array.isArray(e.violations), `${key}: ${engine} violations is a list`).toBe(true)
      }
    }
  })

  test('every engine gesture skip names a gesture that exists', () => {
    for (const [engine, cases] of Object.entries(ENGINE_GESTURE_SKIPS)) {
      for (const [id, gestures] of Object.entries(cases)) {
        const names = (byId[id]?.gestures ?? []).map((g) => g.name)
        for (const name of Object.keys(gestures)) expect(names, `${engine} skips "${id}" / "${name}", which is not a gesture of a case`).toContain(name)
      }
    }
  })

  test('every engine skip names a case that exists', () => {
    for (const [engine, skips] of Object.entries(ENGINE_SKIPS)) {
      for (const id of Object.keys(skips)) expect(byId[id], `${engine} skips "${id}", which is not a case`).toBeTruthy()
    }
  })
})
