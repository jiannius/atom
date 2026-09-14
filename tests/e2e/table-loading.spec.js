import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/table-loading
// (tests/Fixtures/TableLoadingFixture.php).
//
// The overlay is a wire:loading element, so only a real browser against a real
// component proves anything: renderBlade() can read the wire:target string but
// never watches Livewire decide whether that string matches the request.
//
// The request is held open from here rather than slept on the server. `testbench
// serve` is a single-worker php artisan serve, so a server-side pause blocks every
// other Playwright worker sharing it.

const overlay = page => page.locator('[data-atom-table-loading]')

/** Hold every Livewire round-trip open for `ms`, so the overlay can be measured. */
async function holdRequests (page, ms) {
  await page.route('**/livewire-*/update*', async route => {
    const response = await route.fetch()
    await new Promise(r => setTimeout(r, ms))
    await route.fulfill({ response })
  })
}

// A held request outlives the test otherwise, and route.fetch() throws into the
// runner once the page is gone.
test.afterEach(async ({ page }) => {
  await page.unrouteAll({ behavior: 'ignoreErrors' })
})

const rect = (page, selector) => page.evaluate(
  sel => { const { top, bottom, height } = document.querySelector(sel).getBoundingClientRect(); return { top, bottom, height } },
  selector,
)

test.describe('table loading overlay', () => {
  test('it covers the table while the rows-per-page changes', async ({ page }) => {
    await page.goto('/atom/e2e/table-loading')
    await page.waitForLoadState('networkidle')
    await holdRequests(page, 1000)

    await expect(overlay(page)).toBeHidden()
    await expect(page.locator('[data-atom-table-row]')).toHaveCount(100)

    // the rows-per-page control: a dropdown in the pagination bar whose items
    // are plain $set('_table.max_rows', N) calls
    await page.getByRole('button', { name: /100 rows/ }).click()
    await page.getByRole('menuitem', { name: '200 rows / page' }).click()

    // KEY ASSERTION: changing the page size is a table load like any other, so
    // the overlay has to fire for it. Without _table.max_rows in wire:target the
    // screen sits on stale rows for the whole round-trip with no indicator.
    await expect(overlay(page)).toBeVisible()

    await expect(page.locator('[data-atom-table-row]')).toHaveCount(200)
    await expect(overlay(page)).toBeHidden()
  })

  test('it covers the table while the trashed view toggles', async ({ page }) => {
    await page.goto('/atom/e2e/table-loading')
    await page.waitForLoadState('networkidle')
    await holdRequests(page, 1000)

    await expect(overlay(page)).toBeHidden()

    // same shape of bug as rows-per-page: $toggle() writes _table.show_trashed,
    // which swaps the whole result set out
    await page.locator('[data-atom-table-trashed]').click()

    await expect(overlay(page)).toBeVisible()
    await expect(overlay(page)).toBeHidden({ timeout: 5000 })
  })

  test('a table on a page with no Livewire component is not left under the veil', async ({ page }) => {
    // /atom/docs/table renders five plain-Blade tables and mounts no Livewire
    // component. It is the real instance of the bug, and unlike the e2e fixture
    // pages it serves the real atom.css, which is where the fix lives.
    await page.goto('/atom/docs/table')
    await page.waitForLoadState('networkidle')

    // Precondition, and the whole reason this page is the target: Livewire's own
    // stylesheet carries the same [wire\:loading] { display: none } rule, but is
    // auto-injected only on a request that rendered a component. Every such rule
    // on this page has to come from atom.css, or the test would be passing on
    // Livewire's stylesheet rather than on anything atom ships.
    const sources = await page.evaluate(() => [...document.styleSheets]
      .filter(sheet => {
        try { return [...sheet.cssRules].some(r => r.selectorText?.includes('wire\\:loading')) }
        catch { return false }
      })
      .map(sheet => sheet.href ?? 'inline <style>'))

    expect(sources.length).toBeGreaterThan(0)
    expect(sources.every(href => href.includes('/atom/')), `rule sources: ${sources}`).toBe(true)

    // Every loading indicator on the page, not just the table's: the same rule
    // covers the search box's spinner and anything else the package grows.
    const indicators = page.locator('[wire\\:loading], [wire\\:loading\\.flex]')
    const count = await indicators.count()

    expect(count).toBeGreaterThan(0)
    expect(await page.locator('[data-atom-table-loading]').count()).toBeGreaterThan(0)
    expect(await page.locator('[data-atom-table-search] [wire\\:loading]').count()).toBeGreaterThan(0)

    // KEY ASSERTION: each one holds itself down. At its default display the table
    // overlay veils the whole table permanently and the search box keeps a spinner
    // parked in it — no request in flight, and nothing that will ever end it.
    for (let i = 0; i < count; i++) {
      await expect(indicators.nth(i)).toBeHidden()
    }
  })

  test('the spinner stays on screen wherever the fold falls in a long table', async ({ page }) => {
    await page.setViewportSize({ width: 1200, height: 762 })
    await page.goto('/atom/e2e/table-loading')
    await page.waitForLoadState('networkidle')
    await holdRequests(page, 1500)

    const viewport = page.viewportSize()

    // guard against a vacuous pass: this only tests anything while the table runs
    // well past the fold, which is what the fixture's 100 rows are for
    const rows = await rect(page, '[data-atom-table-rows]')
    expect(rows.height).toBeGreaterThan(viewport.height * 3)

    // Sort by clicking the header from script. A Playwright click scrolls its
    // target into view first, which would pin the page to the top of the table
    // and hide the bug.
    await page.evaluate(() => document.querySelector('[data-atom-table-columns] th [x-data]').click())
    await expect(overlay(page)).toBeVisible()

    // The offsets that matter depend on the table's own geometry, so read it first.
    const box = await page.evaluate(() => {
      const veil = document.querySelector('[data-atom-table-loading]').getBoundingClientRect()
      return {
        docHeight: document.documentElement.scrollHeight,
        veilTop: veil.top + window.scrollY,
        veilBottom: veil.bottom + window.scrollY,
      }
    })

    const offsets = [
      // the two partial-overlap cases: a table whose top has only just scrolled in,
      // and one whose bottom is about to scroll out. A viewport-tall strut cannot
      // reach the visible slice in either, so the spinner needs its own constraints.
      box.veilTop - viewport.height + 150,
      box.veilBottom - 150,
      // and the straightforward case, where the table covers the whole screen
      1400,
      2600,
    ].filter(y => y > 0 && y < box.docHeight - viewport.height)

    expect(offsets.length).toBeGreaterThanOrEqual(4)

    // Walk the fold down the table in ONE round-trip to the page: measuring each
    // offset in its own evaluate raced the in-flight request, and a request that
    // finished leaves a display:none element measuring 0 × 0 — which would satisfy
    // "the spinner is on screen" without proving anything.
    const measured = await page.evaluate(ys => {
      const veil = document.querySelector('[data-atom-table-loading]')
      const icon = veil.querySelector('svg')

      return ys.map(y => {
        window.scrollTo(0, y)
        const v = veil.getBoundingClientRect()
        const i = icon.getBoundingClientRect()
        return { y, veilTop: v.top, veilBottom: v.bottom, iconTop: i.top, iconBottom: i.bottom }
      })
    }, offsets)

    for (const m of measured) {
      // the veil is genuinely on screen at this offset — which also catches a
      // request that finished early, since a hidden element measures 0 × 0
      expect(m.veilBottom - m.veilTop, `veil height at scrollY ${m.y}`).toBeGreaterThan(viewport.height)
      expect(m.veilTop, `veil top at scrollY ${m.y}`).toBeLessThan(viewport.height)
      expect(m.veilBottom, `veil bottom at scrollY ${m.y}`).toBeGreaterThan(0)

      // KEY ASSERTION: the spinner — the part that says "working" — is on screen.
      // Centred in the full table box it sits thousands of pixels below the fold,
      // so the user gets a greyed-out table and no indicator at all.
      expect(m.iconTop, `spinner top at scrollY ${m.y}`).toBeGreaterThanOrEqual(0)
      expect(m.iconBottom, `spinner bottom at scrollY ${m.y}`).toBeLessThanOrEqual(viewport.height)

      // And where the table covers the whole screen it is CENTRED on it, not just
      // squeezed on somewhere. The spinner's own top/bottom would happily park it
      // against the bottom edge; the viewport-tall strut is what centres it.
      if (m.veilTop < 0 && m.veilBottom > viewport.height) {
        const offCentre = Math.abs((m.iconTop + m.iconBottom) / 2 - viewport.height / 2)
        expect(offCentre, `spinner off-centre at scrollY ${m.y}`).toBeLessThan(80)
      }
    }
  })
})
