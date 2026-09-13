import { test, expect } from '@playwright/test'

// Drives /atom/e2e/navlist-persist. `persist-key` only means anything across a
// real page load, so this is the test that matters — the blade assertions in
// NavlistTest can only prove the markup carries the key.

const group = (page, name) => page.locator(`[data-group="${name}"]`)
const toggle = (page, name) => group(page, name).locator('button').first()
const body = (page, name) => group(page, name).locator('[data-atom-navlist-item]').first()

const stored = (page, key) =>
  page.evaluate(k => window.localStorage.getItem(k), `atom:navlist-group:${key}`)

test.beforeEach(async ({ page }) => {
  await page.goto('/atom/e2e/navlist-persist')
  await page.evaluate(() => window.localStorage.clear())
  await page.reload()
  await page.waitForLoadState('networkidle')
})

test('a collapsed group is still collapsed after a reload', async ({ page }) => {
  await expect(body(page, 'persisted')).toBeVisible()

  await toggle(page, 'persisted').click()
  await expect(body(page, 'persisted')).toBeHidden()
  expect(await stored(page, 'nav.purchase')).toBe('0')

  await page.reload()
  await page.waitForLoadState('networkidle')

  // KEY ASSERTION: survives a full page load, not just an SPA navigation
  await expect(body(page, 'persisted')).toBeHidden()
  await expect(toggle(page, 'persisted')).toHaveAttribute('aria-expanded', 'false')

  // and re-expanding writes back
  await toggle(page, 'persisted').click()
  expect(await stored(page, 'nav.purchase')).toBe('1')

  await page.reload()
  await page.waitForLoadState('networkidle')
  await expect(body(page, 'persisted')).toBeVisible()
})

test('a group without the prop reads and writes nothing', async ({ page }) => {
  await toggle(page, 'plain').click()
  await expect(body(page, 'plain')).toBeHidden()

  // nothing written under any atom key by the un-keyed group
  const keys = await page.evaluate(() =>
    Object.keys(window.localStorage).filter(k => k.startsWith('atom:navlist-group:')))
  expect(keys).toEqual([])

  await page.reload()
  await page.waitForLoadState('networkidle')

  // back to the server-rendered default — today's behaviour, unchanged
  await expect(body(page, 'plain')).toBeVisible()
})

test('a stored value beats the expanded prop', async ({ page }) => {
  // this group is rendered :expanded="false"
  await expect(body(page, 'collapsed-default')).toBeHidden()

  await toggle(page, 'collapsed-default').click()
  await expect(body(page, 'collapsed-default')).toBeVisible()

  await page.reload()
  await page.waitForLoadState('networkidle')

  // KEY ASSERTION: open, despite the server rendering expanded=false. This is
  // the documented precedence rule and the obvious support question.
  await expect(body(page, 'collapsed-default')).toBeVisible()
})

test('two groups sharing a key sync on load', async ({ page }) => {
  await toggle(page, 'persisted').click()
  await page.reload()
  await page.waitForLoadState('networkidle')

  // documented, not guarded: same key means same state
  await expect(body(page, 'persisted')).toBeHidden()
  await expect(body(page, 'mirror')).toBeHidden()
})

// A group whose stored state disagrees with the server-rendered `expanded` can only be
// corrected once Alpine boots, so until then the markup shows the server's state. If the
// browser paints before that, the user sees the group open and then snap shut.
//
// Holding the script that carries Alpine makes the race deterministic. Without it the
// assertion is decided by whether Alpine happens to win, which it does locally (~17ms vs
// a ~20-48ms first paint) and need not on a slow connection. The earlier version of this
// test sampled computed display every rAF instead, which is not evidence of a paint — a
// rAF callback can run before a deferred script and read a state that is never rendered —
// and it matched `[data-group] [x-show]`, the chevron inside the button rather than the
// disclosure body.
test('a group stored collapsed is not painted open, even when Alpine boots late', async ({ page }) => {
  await toggle(page, 'persisted').click()
  expect(await stored(page, 'nav.purchase')).toBe('0')

  await page.route('**/livewire**', async route => {
    await new Promise(resolve => setTimeout(resolve, 600))
    await route.continue()
  })

  const reloaded = page.reload()

  // the browser has painted, and Alpine is still held: this is the window a user on a
  // slow connection sits in, and the group must already be collapsed in it
  await page.waitForFunction(() => performance.getEntriesByType('paint').length > 0)

  const duringPaint = await page.evaluate(() => {
    const el = document.querySelector('[data-group="persisted"] > [x-show]')

    return { display: el ? getComputedStyle(el).display : 'NOT FOUND', alpine: !!window.Alpine }
  })

  // guards the test itself: if Alpine got through, the reading above proves nothing
  expect(duringPaint.alpine, 'Alpine booted before the paint, so this run tested nothing').toBe(false)
  expect(duringPaint.display, 'the group was painted open before Alpine could collapse it').toBe('none')

  await reloaded
  await page.waitForLoadState('networkidle')
  await expect(body(page, 'persisted')).toBeHidden()
})

test('still toggles when localStorage throws', async ({ page }) => {
  // stand in for Safari private mode / a hardened browser: an uncaught throw in
  // init() would kill the Alpine component and the group would stop toggling
  // entirely, which is much worse than not remembering
  await page.addInitScript(() => {
    const boom = () => { throw new Error('SecurityError: localStorage is not available') }
    Object.defineProperty(window, 'localStorage', {
      configurable: true,
      get: () => ({ getItem: boom, setItem: boom, removeItem: boom, clear: boom }),
    })
  })

  await page.goto('/atom/e2e/navlist-persist')
  await page.waitForLoadState('networkidle')

  await expect(body(page, 'persisted')).toBeVisible()
  await toggle(page, 'persisted').click()
  await expect(body(page, 'persisted')).toBeHidden()
  await toggle(page, 'persisted').click()
  await expect(body(page, 'persisted')).toBeVisible()
})
