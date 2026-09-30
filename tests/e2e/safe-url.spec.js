import { test, expect } from '@playwright/test'
import { readFileSync } from 'fs'
import { join } from 'path'

// A host that puts a user-supplied URL (a profile "website", a CMS link) into an
// atom component must not get click-XSS from a `javascript:` URL. The blade
// components gate href with safe_url() (tests/Feature/SafeUrlTest.php); this spec
// covers the client-side sinks and the JS twin of the helper, atom.safeUrl():
//
//   /atom/e2e/safe-url              table rows, command palette, toast, lightbox
//   /atom/e2e/safe-url-breadcrumbs  a breadcrumbs trail holding a hostile URL
//
// The hostile URLs set window.__pwned, so a click that follows one shows up here.

// a `javascript:` navigation is queued, not synchronous, so give it a moment to
// run before concluding it did not
async function pwned (page) {
  await page.waitForTimeout(250)
  return page.evaluate(() => window.__pwned)
}

// record calls to Livewire.navigate / window.open instead of performing them
async function spyOnNavigation (page) {
  await page.evaluate(() => {
    window.__navigated = []
    window.__opened = []
    // Livewire.navigate is a getter on a proxy, so a plain assignment is ignored
    Object.defineProperty(Livewire, 'navigate', { value: (url) => window.__navigated.push(url), configurable: true })
    window.open = (url) => window.__opened.push(url)
  })
}

const navigated = (page) => page.evaluate(() => window.__navigated)
const opened = (page) => page.evaluate(() => window.__opened)

// One corpus for both sides: tests/Fixtures/safe-url-corpus.json is also read by
// tests/Feature/SafeUrlTest.php, so the PHP helper and atom.safeUrl() are held to
// the same answers. `parity` is a sample sorted by what atom.safeUrl() answered in
// a browser when the file was generated; both suites must reproduce it.
const corpus = JSON.parse(readFileSync(join(process.cwd(), 'tests/Fixtures/safe-url-corpus.json'), 'utf8'))
const hostile = Object.values(corpus.hostile)
const legit = [...Object.values(corpus.legit), ...Object.values(corpus.oddities)]

test.describe('atom.safeUrl()', () => {
  test('blocks the hostile corpus', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')

    const passed = await page.evaluate((urls) => urls.filter(url => atom.safeUrl(url) !== null), hostile)

    expect(passed).toEqual([])
  })

  test('returns a legitimate URL unchanged', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')

    const changed = await page.evaluate((urls) => urls.filter(url => atom.safeUrl(url) !== url), legit)

    expect(changed).toEqual([])
  })

  test('returns null for empty and non-string input', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')

    const results = await page.evaluate(() => [null, undefined, '', {}, [], false, true].map(value => atom.safeUrl(value)))

    expect(results).toEqual([null, null, null, null, null, null, null])
  })

  test('blocks a deeply nested entity encoding', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')

    // blocked because it decodes down to a `javascript:` URL; the pass limit is a
    // fail-closed bound on top of that, and this test does not pin it down
    const result = await page.evaluate(() => {
      let url = '&#106;avascript:PWNED(1)'
      for (let i = 0; i < 12; i++) url = url.replaceAll('&', '&amp;')
      return atom.safeUrl(url)
    })

    expect(result).toBeNull()
  })

  test('answers what the shared sample recorded for every string', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')

    const wrong = await page.evaluate(({ allowed, blocked }) => ({
      blockedButShouldPass: allowed.filter(url => atom.safeUrl(url) !== url),
      passedButShouldBlock: blocked.filter(url => atom.safeUrl(url) !== null),
    }), corpus.parity)

    expect(corpus.parity.allowed.length).toBeGreaterThan(2000)
    expect(corpus.parity.blocked.length).toBeGreaterThan(2000)
    expect(wrong).toEqual({ blockedButShouldPass: [], passedButShouldBlock: [] })
  })
})

test.describe('table row', () => {
  test('a hostile href does not navigate', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')

    await page.locator('[data-row="hostile"]').click()

    expect(await pwned(page)).toBeUndefined()
    expect(page.url()).toMatch(/\/atom\/e2e\/safe-url$/)
    await expect(page.locator('[data-row="hostile"]')).not.toHaveClass(/cursor-pointer/)
  })

  test('a hostile href on a wire:navigate row does not navigate', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')
    await spyOnNavigation(page)

    await page.locator('[data-row="hostile-spa"]').click()

    expect(await pwned(page)).toBeUndefined()
    expect(await navigated(page)).toEqual([])
  })

  test('a legitimate href still navigates', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')

    await page.locator('[data-row="legit"]').click()

    expect(new URL(page.url()).hash).toBe('#row-legit')
  })

  test('a legitimate href on a wire:navigate row still goes through Livewire', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')
    await spyOnNavigation(page)

    await page.locator('[data-row="legit-spa"]').click()

    expect(await navigated(page)).toEqual(['/atom/e2e/safe-url?spa=1'])
  })
})

test.describe('command palette', () => {
  const palette = (page) => page.locator('dialog[data-atom-command]')
  const item = (page, name) => palette(page).locator(`[data-item="${name}"]`)

  test('a hostile item renders as a button with no href and does nothing on Enter', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')
    await page.keyboard.press('Meta+k')

    await expect(item(page, 'hostile')).toHaveJSProperty('tagName', 'BUTTON')
    await expect(item(page, 'hostile')).not.toHaveAttribute('href', /.*/)

    await page.keyboard.press('Enter')

    expect(await pwned(page)).toBeUndefined()
    expect(new URL(page.url()).hash).toBe('')
  })

  test('a legitimate item still navigates on Enter', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')
    await page.keyboard.press('Meta+k')

    await expect(item(page, 'legit')).toHaveAttribute('href', '#item-legit')

    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('Enter')

    expect(new URL(page.url()).hash).toBe('#item-legit')
  })

  test('Enter does not follow an anchor whose href was swapped for a hostile one', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')
    await page.keyboard.press('Meta+k')

    // whatever writes the href — a morph, a host script — the key handler re-checks it
    await item(page, 'swapped').evaluate(el => el.setAttribute('href', 'javascript:window.__pwned = 1'))

    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('Enter')

    expect(await pwned(page)).toBeUndefined()
    expect(new URL(page.url()).hash).toBe('')
  })
})

test.describe('toast', () => {
  const toast = (page) => page.locator('[data-atom-toast]')

  test('a hostile url or navigate target is not followed', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')
    await spyOnNavigation(page)

    await page.evaluate(() => atom.toast({ message: 'Hi', delay: 0, url: 'javascript:window.__pwned = 1', navigate: 'javascript:window.__pwned = 1' }))
    await expect(toast(page)).toBeVisible()
    await toast(page).click()

    expect(await pwned(page)).toBeUndefined()
    expect(await opened(page)).toEqual([])
    expect(await navigated(page)).toEqual([])
  })

  test('a legitimate url and navigate target still work', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')
    await spyOnNavigation(page)

    await page.evaluate(() => atom.toast({ message: 'Hi', delay: 0, url: 'https://example.com/' }))
    await toast(page).click()
    expect(await opened(page)).toEqual(['https://example.com/'])

    await page.evaluate(() => atom.toast({ message: 'Hi', delay: 0, navigate: '/atom/docs' }))
    await toast(page).click()
    expect(await navigated(page)).toEqual(['/atom/docs'])
  })
})

test.describe('lightbox', () => {
  test('a hostile download url is not opened', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url')
    await spyOnNavigation(page)

    await page.getByTestId('lightbox-open').click()
    await expect(page.locator('dialog[data-atom-lightbox]')).toBeVisible()
    await page.getByRole('button', { name: 'Download' }).click()

    expect(await pwned(page)).toBeUndefined()
    expect(await opened(page)).toEqual([])
  })
})

test.describe('breadcrumbs', () => {
  const crumbs = (page) => page.locator('[data-atom-breadcrumbs] ol a')

  test('a hostile crumb renders without an href and does nothing on click', async ({ page }) => {
    const errors = []
    page.on('pageerror', error => errors.push(error.message))

    await page.goto('/atom/e2e/safe-url-breadcrumbs')
    await expect(crumbs(page)).toHaveCount(3)

    const hostileCrumb = crumbs(page).filter({ hasText: 'Hostile' })
    await expect(hostileCrumb).not.toHaveAttribute('href', /.*/)

    await hostileCrumb.click()

    expect(await pwned(page)).toBeUndefined()
    expect(page.url()).toMatch(/\/atom\/e2e\/safe-url-breadcrumbs$/)
    expect(errors).toEqual([])
  })

  test('a legitimate crumb keeps its href', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url-breadcrumbs')

    await expect(crumbs(page).filter({ hasText: 'Legit' })).toHaveAttribute('href', '/atom/e2e/safe-url-breadcrumbs?legit=1')
    await expect(crumbs(page).filter({ hasText: 'Home' })).toHaveAttribute('href', '/atom/e2e/safe-url-breadcrumbs')
  })

  test('back() does not navigate to a hostile crumb', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url-breadcrumbs?penultimate=hostile')
    await expect(crumbs(page)).toHaveCount(3)
    await spyOnNavigation(page)

    await page.evaluate(() => window.dispatchEvent(new CustomEvent('navigate-back')))

    expect(await pwned(page)).toBeUndefined()
    expect(await navigated(page)).toEqual([])
  })

  test('back() still navigates to a legitimate crumb', async ({ page }) => {
    await page.goto('/atom/e2e/safe-url-breadcrumbs')
    await expect(crumbs(page)).toHaveCount(3)
    await spyOnNavigation(page)

    await page.evaluate(() => window.dispatchEvent(new CustomEvent('navigate-back')))

    expect(await navigated(page)).toEqual(['/atom/e2e/safe-url-breadcrumbs?legit=1'])
  })
})
