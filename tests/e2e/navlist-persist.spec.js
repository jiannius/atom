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

test('a group stored collapsed never paints open', async ({ page }) => {
  await toggle(page, 'persisted').click()
  expect(await stored(page, 'nav.purchase')).toBe('0')

  // sample the disclosure body's computed display on every frame from document
  // start. If Alpine corrected `open` only after a paint, at least one frame
  // would catch the group rendered open — which is the flash x-cloak would exist
  // to hide.
  await page.addInitScript(() => {
    window.__frames = []

    const tick = () => {
      const el = document.querySelector('[data-group="persisted"] [x-show]')
      if (el) window.__frames.push(getComputedStyle(el).display)
      if (window.__frames.length < 20) requestAnimationFrame(tick)
    }

    requestAnimationFrame(tick)
  })

  await page.reload()
  await page.waitForLoadState('networkidle')
  await expect(body(page, 'persisted')).toBeHidden()

  const frames = await page.evaluate(() => window.__frames)

  expect(frames.length).toBeGreaterThan(0)          // the probe actually sampled
  expect(frames.every(display => display === 'none')).toBe(true)
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
