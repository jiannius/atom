import { test, expect } from '@playwright/test'

// Hiding the columns of a table (table-columns P3): the stylesheet, the token list on the scroll box, the choices in localStorage or
// in the host's `columns-state`, the repaint after a morph and the change event. On a Livewire table (tests/Fixtures/TableColumnsFixture.php):
// a checkbox column, `name` (locked), `email`, `phone` (hidden by default), `city` (host: max-sm:hidden), `plan` (host: hidden lg:table-cell).
// There is no picker yet: the tests drive the API a picker will call (window.atom.tableColumns).

const KEY = 'atom:table-columns:people'
const table = '[data-atom-table]'

const cells = (page, name) => page.locator(`${table} [data-atom-column="${name}"]`)
// every cell of the column is drawn (display is not none), and there is at least one
const shown = (page, name) => page.evaluate((name) => {
  const all = [...document.querySelectorAll(`[data-atom-table] [data-atom-column="${name}"]`)]
  return all.length > 0 && all.every((el) => getComputedStyle(el).display !== 'none')
}, name)
const hidden = (page, name) => page.evaluate((name) => {
  const all = [...document.querySelectorAll(`[data-atom-table] [data-atom-column="${name}"]`)]
  return all.length > 0 && all.every((el) => getComputedStyle(el).display === 'none')
}, name)

const api = (page, method, name = undefined) => page.evaluate(([method, name]) => window.atom.tableColumns[method](document.querySelector('[data-atom-table]'), name), [method, name])
const stored = (page) => page.evaluate((key) => window.localStorage.getItem(key), KEY)
const hideList = (page) => page.locator('[data-atom-column-scope]').getAttribute('data-atom-column-hide')

const roundTrip = async (page, action) => {
  const response = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST')
  await action()
  await response
  await page.evaluate(() => new Promise((done) => setTimeout(done, 80)))
}

// open the fixture with localStorage seeded; Playwright's context is fresh per test, so seed on a first visit and load again
async function open(page, { stored: seed = null, tw = 'latest', query = '', rows = true } = {}) {
  await page.goto(`/atom/e2e/table-columns?tw=${tw}${query}`)
  await page.evaluate(([key, seed]) => {
    window.localStorage.clear()
    if (seed) window.localStorage.setItem(key, JSON.stringify(seed))
  }, [KEY, seed])
  await page.goto(`/atom/e2e/table-columns?tw=${tw}${query}`)
  await page.waitForLoadState('networkidle')
  if (rows) await expect(cells(page, 'name').first()).toBeVisible()
}

const choices = (columns) => ({ v: 1, columns })

// Sample, on every frame, whether a column is drawn the way it should be. The sample runs before the frame is painted, so a column
// that is wrong for a moment is only caught if the browser would actually have shown it.
const watchFrames = (page, expected) => page.evaluate((expected) => {
  window.__wrong = []
  window.__frames = 0
  const loop = () => {
    window.__frames++
    for (const [name, wantShown] of Object.entries(expected)) {
      document.querySelectorAll(`[data-atom-table] [data-atom-column="${name}"]`).forEach((el) => {
        if ((getComputedStyle(el).display !== 'none') !== wantShown) window.__wrong.push(name)
      })
    }
    requestAnimationFrame(loop)
  }
  requestAnimationFrame(loop)
}, expected)
const wrongFrames = (page) => page.evaluate(() => ({ wrong: window.__wrong, frames: window.__frames }))

test.describe('the stylesheet and the defaults', () => {
  test('a column hidden by default is hidden in th, td and the footer; the others are drawn', async ({ page }) => {
    await open(page)

    expect(await hidden(page, 'phone')).toBe(true)
    expect(await shown(page, 'email')).toBe(true)
    expect(await shown(page, 'name')).toBe(true)
    await expect(page.locator(`${table} th[data-atom-column="phone"]`)).toBeHidden()
    await expect(page.locator(`${table} tfoot [data-atom-column="email"]`)).toBeVisible()
    expect(await hideList(page)).toBe('phone')
  })

  test('the checkbox column and the actions of a row are never touched', async ({ page }) => {
    await open(page, { stored: choices({ email: false }) })

    await expect(page.locator(`${table} th [data-atom-table-checkbox]`)).toBeVisible()
    await expect(page.locator(`${table} tbody [data-atom-table-checkbox]`).first()).toBeVisible()
  })

  test('hiding wins over a host display utility, and a locked column ignores a stored choice', async ({ page }) => {
    // plan is `hidden lg:table-cell` (shown from lg up); the user hid it. name is locked: a stored false does nothing
    await open(page, { stored: choices({ plan: false, name: false }) })

    expect(await hidden(page, 'plan')).toBe(true)
    expect(await shown(page, 'name')).toBe(true)
  })

  test('the stale-looking hidden list carries only names that are in the markup', async ({ page }) => {
    await open(page, { stored: choices({ email: false, ghost: false }) })

    // the script wrote what was stored; the engine then writes what the page has
    await expect.poll(() => hideList(page)).toMatch(/^(phone email|email phone)$/)
  })
})

test.describe('first paint', () => {
  // Alpine and Livewire are held, so what the test reads is what the browser paints before either starts
  async function holdScripts(page) {
    let release
    const gate = new Promise((resolve) => { release = resolve })

    await page.route((url) => /\/atom\/atom-.*\.js$/.test(url.pathname) || /livewire.*\.js$/.test(url.pathname), async (route) => {
      await gate
      await route.continue()
    })

    return release
  }

  async function paintedWithoutJs(page, query, names) {
    const release = await holdScripts(page)
    const loaded = page.goto(`/atom/e2e/table-columns?tw=latest${query}`)

    await page.waitForFunction(() => performance.getEntriesByType('paint').length > 0)

    const during = await page.evaluate((names) => Object.fromEntries(names.map((name) => [
      name,
      [...document.querySelectorAll(`[data-atom-table] [data-atom-column="${name}"]`)].map((el) => getComputedStyle(el).display),
    ])), names)

    expect(await page.evaluate(() => ({ alpine: !!window.Alpine, atom: !!window.atom })), 'the scripts got through, so this run tested nothing').toEqual({ alpine: false, atom: false })

    release()
    await loaded

    return during
  }

  test('a column hidden by default is hidden with no script running at all', async ({ page }) => {
    await page.goto('/atom/e2e/table-columns')
    await page.evaluate(() => window.localStorage.clear())

    const during = await paintedWithoutJs(page, '', ['phone', 'email'])

    expect(during.phone.length).toBeGreaterThan(0)
    expect(during.phone.every((d) => d === 'none')).toBe(true)
    expect(during.email.every((d) => d !== 'none')).toBe(true)
  })

  test('a stored hidden column is hidden, and a stored shown default-hidden one is drawn, before Alpine boots', async ({ page }) => {
    await page.goto('/atom/e2e/table-columns')
    await page.evaluate(([key, value]) => window.localStorage.setItem(key, JSON.stringify(value)), [KEY, choices({ email: false, phone: true })])

    const during = await paintedWithoutJs(page, '', ['phone', 'email'])

    expect(during.email.every((d) => d === 'none')).toBe(true)
    expect(during.phone.length).toBeGreaterThan(0)
    expect(during.phone.every((d) => d !== 'none')).toBe(true)
  })

  test('a value in storage that is not a choice is ignored, never thrown on', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))

    await page.goto('/atom/e2e/table-columns')
    await page.evaluate((key) => window.localStorage.setItem(key, '{"v":1,"columns":{"email":"no","</style>":false,"phone":null,"city":true}}'), KEY)

    const during = await paintedWithoutJs(page, '', ['phone', 'email'])

    expect(during.phone.every((d) => d === 'none')).toBe(true)
    expect(during.email.every((d) => d !== 'none')).toBe(true)

    await page.evaluate((key) => window.localStorage.setItem(key, 'not json'), KEY)
    await page.reload()
    await page.waitForLoadState('networkidle')
    expect(await hidden(page, 'phone')).toBe(true)
    expect(errors).toEqual([])
  })

  test('choices stored as a list, not an object, are ignored by the inline script as the engine ignores them', async ({ page }) => {
    await page.goto('/atom/e2e/table-columns')
    await page.evaluate((key) => window.localStorage.setItem(key, '{"v":1,"columns":[false,false]}'), KEY)

    const release = await holdScripts(page)
    const loaded = page.goto('/atom/e2e/table-columns?tw=latest')
    await page.waitForFunction(() => performance.getEntriesByType('paint').length > 0)

    expect(await page.evaluate(() => window.Alpine === undefined)).toBe(true)
    expect(await page.locator('[data-atom-column-scope]').getAttribute('data-atom-column-hide')).toBe('phone')

    release()
    await loaded
  })

  test('the host\'s columns-state is drawn at first paint with no script, and localStorage is not read', async ({ page }) => {
    await page.goto('/atom/e2e/table-columns')
    await page.evaluate(([key, value]) => window.localStorage.setItem(key, JSON.stringify(value)), [KEY, choices({ email: true, phone: true })])

    // the host says email is hidden and phone stays at its default; the stored choices say the opposite
    const during = await paintedWithoutJs(page, '&state=' + encodeURIComponent('{"email":false}'), ['phone', 'email'])

    expect(during.email.every((d) => d === 'none')).toBe(true)
    expect(during.phone.every((d) => d === 'none')).toBe(true)
  })
})

test.describe('the choices survive a morph', () => {
  // email is hidden by the user, phone shown: both go against what the server renders, so every morph undoes them until the engine writes them again
  const stored = choices({ email: false, phone: true })

  test('a next page, a search and a rows-per-page change: no frame draws a column wrongly', async ({ page }) => {
    await open(page, { stored })
    await watchFrames(page, { email: false, phone: true })

    await roundTrip(page, () => page.getByRole('button', { name: 'Go to page 2' }).click())
    await expect(page.locator(`${table} tbody tr`).first()).toContainText('Person 6')

    await roundTrip(page, () => page.getByTestId('search').fill('Person 1'))
    await expect(page.locator(`${table} tbody tr`).first()).toContainText('Person 1')

    await roundTrip(page, () => page.evaluate(() => window.Livewire.first().$set('_table.max_rows', 10)))

    const { wrong, frames } = await wrongFrames(page)
    expect(frames).toBeGreaterThan(5)
    expect(wrong).toEqual([])
    expect(await hidden(page, 'email')).toBe(true)
    expect(await shown(page, 'phone')).toBe(true)
  })

  // Livewire wraps the morph in a view transition when the component holds a wire:transition element. The morph is the update callback
  // of document.startViewTransition(), and `morphed` fires when that callback is done, before the transition takes its new snapshot.
  test('the same under wire:transition, which morphs inside document.startViewTransition()', async ({ page }) => {
    await open(page, { stored, query: '&transition=1' })
    await page.evaluate(() => {
      window.__transitions = 0
      const start = document.startViewTransition.bind(document)
      document.startViewTransition = (...args) => { window.__transitions++; return start(...args) }
    })
    await watchFrames(page, { email: false, phone: true })

    await roundTrip(page, () => page.getByRole('button', { name: 'Go to page 2' }).click())
    await expect(page.locator(`${table} tbody tr`).first()).toContainText('Person 6')
    await page.waitForTimeout(400)

    expect(await page.evaluate(() => window.__transitions), 'Livewire did not use a view transition, so this run tested nothing').toBeGreaterThan(0)
    expect((await wrongFrames(page)).wrong).toEqual([])
    expect(await hidden(page, 'email')).toBe(true)
    expect(await shown(page, 'phone')).toBe(true)
  })

  test('a skeleton that gives way to rows keeps the column hidden', async ({ page }) => {
    await open(page, { stored, query: '&skeleton=1', rows: false })

    await expect(page.locator(`${table} [data-atom-table-skeleton]`)).toBeVisible()
    expect(await api(page, 'list')).toHaveLength(5)

    await watchFrames(page, { email: false, phone: true })
    await roundTrip(page, () => page.locator('[data-load]').click())
    await expect(cells(page, 'name').first()).toBeVisible()

    const { wrong } = await wrongFrames(page)
    expect(wrong).toEqual([])
    expect(await hidden(page, 'email')).toBe(true)
  })

  test('a table with no persist-key keeps what the user chose across a morph, and loses it on a reload', async ({ page }) => {
    await open(page, { query: '&nokey=1' })

    await api(page, 'hide', 'email')
    expect(await hidden(page, 'email')).toBe(true)

    await roundTrip(page, () => page.getByRole('button', { name: 'Go to page 2' }).click())
    await expect(page.locator(`${table} tbody tr`).first()).toContainText('Person 6')
    expect(await hidden(page, 'email')).toBe(true)

    expect(await page.evaluate(() => Object.keys(window.localStorage).filter((key) => key.startsWith('atom:table-columns:')))).toEqual([])

    await page.reload()
    await page.waitForLoadState('networkidle')
    expect(await shown(page, 'email')).toBe(true)
  })

  test('an empty table still has the columns listed, and rows that arrive are hidden by the stylesheet', async ({ page }) => {
    await open(page, { stored, query: '&empty=1', rows: false })

    const list = await api(page, 'list')
    expect(list.map((c) => c.name)).toEqual(['name', 'email', 'phone', 'city', 'plan'])
  })
})

test.describe('the choices survive a navigation and a reload', () => {
  test('hidden, then a reload: still hidden, from storage', async ({ page }) => {
    await open(page)

    await api(page, 'hide', 'email')
    expect(JSON.parse(await stored(page))).toEqual({ v: 1, columns: { email: false } })

    await page.reload()
    await page.waitForLoadState('networkidle')
    expect(await hidden(page, 'email')).toBe(true)
  })

  test('wire:navigate away and back draws the table again with the choices, written by the inline script alone', async ({ page }) => {
    await open(page, { stored: choices({ email: false, phone: true }) })

    // from here only the inline script can write the list: the engine's sync (the x-init on the source) does nothing
    await page.evaluate(() => { window.atom.tableColumns.sync = () => {} })

    await page.locator('[data-go-other]').click()
    await expect(page.locator('[data-go-back]')).toBeVisible()
    await page.locator('[data-go-back]').click()
    await expect(cells(page, 'name').first()).toBeVisible()

    expect(await page.evaluate(() => window.__noReload ?? 'navigated')).toBe('navigated')
    expect(await hideList(page)).toMatch(/^email$/)
    expect(await hidden(page, 'email')).toBe(true)
    expect(await shown(page, 'phone')).toBe(true)
  })

  test('a throwing localStorage leaves the defaults, no error, and the API still works in memory', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()) })

    await page.addInitScript(() => {
      const boom = () => { throw new Error('SecurityError: localStorage is not available') }
      Object.defineProperty(window, 'localStorage', {
        configurable: true,
        get: () => ({ getItem: boom, setItem: boom, removeItem: boom, clear: boom }),
      })
    })

    await page.goto('/atom/e2e/table-columns')
    await page.waitForLoadState('networkidle')

    expect(await hidden(page, 'phone')).toBe(true)
    expect(await shown(page, 'email')).toBe(true)

    expect(await api(page, 'hide', 'email')).toBe(true)
    expect(await hidden(page, 'email')).toBe(true)

    await roundTrip(page, () => page.getByRole('button', { name: 'Go to page 2' }).click())
    expect(await hidden(page, 'email')).toBe(true)
    expect(errors).toEqual([])
  })
})

test.describe('a renamed or removed column', () => {
  test('a renamed column starts at its default, and the next write prunes the old name', async ({ page }) => {
    await open(page, { stored: choices({ email: false }), query: '&renamed=1' })

    expect(await shown(page, 'mail')).toBe(true)
    expect(await hideList(page)).toBe('phone')

    await api(page, 'show', 'phone')
    expect(JSON.parse(await stored(page))).toEqual({ v: 1, columns: { phone: true } })
  })

  test('a removed column leaves its choice doing nothing, and the next write prunes it', async ({ page }) => {
    await open(page, { stored: choices({ phone: true, email: false }), query: '&removed=1' })

    expect(await cells(page, 'phone').count()).toBe(0)
    expect(await hidden(page, 'email')).toBe(true)

    await api(page, 'hide', 'city')
    expect(JSON.parse(await stored(page))).toEqual({ v: 1, columns: { email: false, city: false } })
  })

  test('a choice that equals the default is not kept, and an empty map removes the key', async ({ page }) => {
    await open(page)

    await api(page, 'hide', 'email')
    expect(JSON.parse(await stored(page)).columns).toEqual({ email: false })

    await api(page, 'show', 'email')
    expect(await stored(page)).toBeNull()

    // phone is hidden by default: hiding it is the default, so nothing changes
    expect(await api(page, 'hide', 'phone')).toBe(false)
    expect(await stored(page)).toBeNull()
  })
})

for (const tw of ['floor', 'latest']) {
  test.describe(`next to a host's responsive classes, ${tw} consumer build`, () => {
    test('a column shown by the user is still hidden by the host below its breakpoint, and drawn above it', async ({ page }) => {
      await open(page, { tw, stored: choices({ city: true }) })

      await page.setViewportSize({ width: 390, height: 800 })
      expect(await hidden(page, 'city'), 'max-sm:hidden stays in charge on a phone').toBe(true)

      await page.setViewportSize({ width: 1440, height: 900 })
      expect(await shown(page, 'city')).toBe(true)
    })

    test('a column hidden by the user is hidden at 1440px although the host shows it from lg up', async ({ page }) => {
      await open(page, { tw, stored: choices({ plan: false }) })
      await page.setViewportSize({ width: 1440, height: 900 })

      expect(await hidden(page, 'plan')).toBe(true)
      expect(await shown(page, 'city')).toBe(true)

      await api(page, 'show', 'plan')
      expect(await shown(page, 'plan')).toBe(true)

      await page.setViewportSize({ width: 390, height: 800 })
      expect(await hidden(page, 'plan'), 'hidden lg:table-cell stays in charge on a phone').toBe(true)
    })
  })
}

test.describe('the host\'s columns-state', () => {
  const state = '&state=' + encodeURIComponent('{"email":false,"phone":true}')

  test('is used instead of localStorage, which is neither read nor written', async ({ page }) => {
    await open(page, { stored: choices({ email: true, plan: false }), query: state })

    expect(await hidden(page, 'email')).toBe(true)
    expect(await shown(page, 'phone')).toBe(true)
    expect(await shown(page, 'plan')).toBe(true)

    await api(page, 'hide', 'city')
    expect(JSON.parse(await stored(page))).toEqual(choices({ email: true, plan: false }))
    expect((await api(page, 'state')).columns).toEqual({ email: false, phone: true, city: false })
  })

  test('survives a morph, and the page then holds what the user chose, not the old value', async ({ page }) => {
    await open(page, { query: state })
    await watchFrames(page, { email: false, phone: true })

    await api(page, 'show', 'email')
    await roundTrip(page, () => page.getByRole('button', { name: 'Go to page 2' }).click())

    // the event handler saved {phone: true, email shown}: the server renders that state back, and the user's choice stays
    expect(await shown(page, 'email')).toBe(true)
    expect(await shown(page, 'phone')).toBe(true)
  })

  test('an empty state is a state: nothing from localStorage applies', async ({ page }) => {
    await open(page, { stored: choices({ email: false }), query: '&state=%7B%7D' })

    expect(await shown(page, 'email')).toBe(true)
    expect(await hidden(page, 'phone')).toBe(true)
  })
})

test.describe('state pushed from the server', () => {
  const state = '&state=' + encodeURIComponent('{"email":false}')

  test('a changed columns-state is ignored by the table already on the page', async ({ page }) => {
    await open(page, { query: state })
    await api(page, 'hide', 'city')
    await expect(page.getByTestId('saved')).toHaveText('[{"email":false,"city":false}]')

    // the server now says {phone: shown}, in the same table (same wire:key): the user's choices stand
    await roundTrip(page, () => page.locator('[data-push-state]').click())

    expect(await hidden(page, 'email')).toBe(true)
    expect(await hidden(page, 'city')).toBe(true)
    expect(await hidden(page, 'phone')).toBe(true)
  })

  test('a new wire:key draws a new table, which takes the server\'s state again', async ({ page }) => {
    await open(page, { query: state })
    await api(page, 'hide', 'city')
    await expect(page.getByTestId('saved')).toHaveText('[{"email":false,"city":false}]')

    await roundTrip(page, () => page.locator('[data-reset-layout]').click())

    expect(await shown(page, 'email')).toBe(true)
    expect(await shown(page, 'city')).toBe(true)
    expect(await shown(page, 'phone')).toBe(true)
    expect((await api(page, 'state')).columns).toEqual({ phone: true })

    // and the new table is the user's again: a change keeps working, and reaches the host
    await api(page, 'hide', 'plan')
    expect(await hidden(page, 'plan')).toBe(true)
    await roundTrip(page, () => page.getByRole('button', { name: 'Go to page 2' }).click())
    expect(await hidden(page, 'plan')).toBe(true)
    expect(await shown(page, 'phone')).toBe(true)
  })
})

test.describe('several tables on one page', () => {
  // the cells a table owns (not those of a table nested in one of its cells)
  const own = (page, id, name) => page.evaluate(([id, name]) => {
    const root = document.querySelector(`[data-testid="${id}"]`)
    // the header, the cells and any footer of the column agree: [true] drawn, [false] hidden
    return [...new Set([...root.querySelectorAll(`[data-atom-column="${name}"]`)]
      .filter((el) => el.closest('[data-atom-table]') === root)
      .map((el) => getComputedStyle(el).display !== 'none'))]
  }, [id, name])
  const call = (page, id, method, name) => page.evaluate(([id, method, name]) => window.atom.tableColumns[method](document.querySelector(`[data-testid="${id}"]`), name), [id, method, name])
  const storedKeys = (page) => page.evaluate(() => Object.keys(window.localStorage).filter((key) => key.startsWith('atom:table-columns:')).sort())

  test.beforeEach(async ({ page }) => {
    await page.goto('/atom/e2e/table-columns-pair')
    await page.evaluate(() => window.localStorage.clear())
    await page.reload()
    await page.waitForLoadState('networkidle')
  })

  test('two tables with a column of one name and different defaults and keys stay apart', async ({ page }) => {
    expect(await own(page, 'a', 'phone')).toEqual([false])
    expect(await own(page, 'b', 'phone')).toEqual([true])

    expect(await call(page, 'a', 'show', 'phone')).toBe(true)
    expect(await own(page, 'a', 'phone')).toEqual([true])
    expect(await own(page, 'b', 'phone')).toEqual([true])

    expect(await call(page, 'b', 'hide', 'phone')).toBe(true)
    expect(await own(page, 'a', 'phone')).toEqual([true])
    expect(await own(page, 'b', 'phone')).toEqual([false])

    expect(await storedKeys(page)).toEqual(['atom:table-columns:pair-a', 'atom:table-columns:pair-b'])

    // and a reload draws each as chosen
    await page.reload()
    await page.waitForLoadState('networkidle')
    expect(await own(page, 'a', 'phone')).toEqual([true])
    expect(await own(page, 'b', 'phone')).toEqual([false])
  })

  test('two tables sharing a key share their choices, and say so once', async ({ page }) => {
    await page.evaluate(() => {
      window.__events = []
      window.addEventListener('atom-table-columns-changed', (e) => window.__events.push(e.detail))
    })

    await call(page, 'c', 'hide', 'email')

    expect(await own(page, 'c', 'email')).toEqual([false])
    expect(await own(page, 'd', 'email')).toEqual([false])
    expect(await page.evaluate(() => window.__events)).toEqual([{ key: 'pair-shared', columns: { email: false } }])
    expect(await storedKeys(page)).toEqual(['atom:table-columns:pair-shared'])

    await call(page, 'd', 'show', 'email')
    expect(await own(page, 'c', 'email')).toEqual([true])
  })

  test('a table nested in a cell is not reached by the outer table\'s hidden column of the same name', async ({ page }) => {
    expect(await own(page, 'outer', 'status')).toEqual([true])
    expect(await own(page, 'inner', 'status')).toEqual([true])

    await call(page, 'outer', 'hide', 'status')
    expect(await own(page, 'outer', 'status')).toEqual([false])
    expect(await own(page, 'inner', 'status'), 'the outer table hid the inner one\'s cells').toEqual([true])

    await call(page, 'inner', 'hide', 'status')
    await call(page, 'outer', 'show', 'status')
    expect(await own(page, 'outer', 'status')).toEqual([true])
    expect(await own(page, 'inner', 'status')).toEqual([false])
  })

  test('the same on first paint, from storage', async ({ page }) => {
    await page.evaluate(() => window.localStorage.setItem('atom:table-columns:pair-outer', '{"v":1,"columns":{"status":false}}'))
    await page.reload()
    await page.waitForLoadState('networkidle')

    expect(await own(page, 'outer', 'status')).toEqual([false])
    expect(await own(page, 'inner', 'status')).toEqual([true])
  })
})

test.describe('the change event', () => {
  async function listen(page) {
    await page.evaluate(() => {
      window.__events = []
      window.addEventListener('atom-table-columns-changed', (e) => window.__events.push({ detail: e.detail, target: e.target.matches('[data-atom-column-source]') }))
    })
  }
  const events = (page) => page.evaluate(() => window.__events)

  test('fires on the window with the key and the whole map of choices, once per change', async ({ page }) => {
    await open(page)
    await listen(page)

    await api(page, 'hide', 'email')
    await api(page, 'show', 'phone')
    await api(page, 'toggle', 'city')

    expect(await events(page)).toEqual([
      { detail: { key: 'people', columns: { email: false } }, target: true },
      { detail: { key: 'people', columns: { email: false, phone: true } }, target: true },
      { detail: { key: 'people', columns: { email: false, phone: true, city: false } }, target: true },
    ])
  })

  test('does not fire for a change that changes nothing, a locked or unknown column, a morph or a reload', async ({ page }) => {
    await open(page, { stored: choices({ email: false }) })
    await listen(page)

    expect(await api(page, 'hide', 'email')).toBe(false)
    expect(await api(page, 'hide', 'name')).toBe(false)
    expect(await api(page, 'hide', 'ghost')).toBe(false)
    expect(await api(page, 'toggle', 'name')).toBe(false)
    await roundTrip(page, () => page.getByRole('button', { name: 'Go to page 2' }).click())

    expect(await events(page)).toEqual([])
  })

  test('reset fires with an empty map and clears storage; a table with no key reports a null key', async ({ page }) => {
    await open(page, { stored: choices({ email: false, phone: true }) })
    await listen(page)

    expect(await api(page, 'reset')).toBe(true)
    expect(await events(page)).toEqual([{ detail: { key: 'people', columns: {} }, target: true }])
    expect(await stored(page)).toBeNull()
    expect(await shown(page, 'email')).toBe(true)
    expect(await hidden(page, 'phone')).toBe(true)
    expect(await api(page, 'reset')).toBe(false)

    await open(page, { query: '&nokey=1' })
    await listen(page)
    await api(page, 'hide', 'email')
    expect((await events(page))[0].detail).toEqual({ key: null, columns: { email: false } })
  })

  test('a Livewire handler gets the map and can keep it', async ({ page }) => {
    await open(page)

    await roundTrip(page, () => api(page, 'hide', 'email'))
    await expect(page.getByTestId('saved')).toHaveText('[{"email":false}]')
  })

  test('the API reads the columns and the state back', async ({ page }) => {
    await open(page, { stored: choices({ email: false }) })

    expect(await api(page, 'list')).toEqual([
      { name: 'name', label: 'Name', hideable: false, hidden: false, visible: true },
      { name: 'email', label: 'Email', hideable: true, hidden: false, visible: false },
      { name: 'phone', label: 'Phone', hideable: true, hidden: true, visible: false },
      { name: 'city', label: 'City', hideable: true, hidden: false, visible: true },
      { name: 'plan', label: 'Plan', hideable: true, hidden: false, visible: true },
    ])
    expect(await api(page, 'state')).toEqual({ key: 'people', columns: { email: false } })
  })
})
