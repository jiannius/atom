import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/table-loading
// (tests/Fixtures/TableLoadingFixture.php), which pauses ~700ms on every request
// after the first so the loading overlay is actually observable.
//
// The overlay is a wire:loading element, so only a real browser against a real
// component proves anything: renderBlade() can read the wire:target string but
// never watches Livewire decide whether that string matches the request.

const overlay = page => page.locator('[data-atom-table-loading]')
const spinner = page => page.locator('[data-atom-table-loading] svg')

/** The row set is what the overlay is covering — fail loudly if it never moved. */
const rowCount = page => page.locator('[data-atom-table-row]').count()

// Playwright's boundingBox() is documented as viewport-relative, but the whole
// point here is where a box sits against the fold, so read the rect the browser
// itself reports rather than trusting a second coordinate space.
const rect = (page, selector) => page.evaluate(
  sel => { const { top, bottom, height } = document.querySelector(sel).getBoundingClientRect(); return { top, bottom, height } },
  selector,
)

test.describe('table loading overlay', () => {
  test('it covers the table while the rows-per-page changes', async ({ page }) => {
    await page.goto('/atom/e2e/table-loading')
    await page.waitForLoadState('networkidle')

    await expect(overlay(page)).toBeHidden()
    expect(await rowCount(page)).toBe(100)

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

    await expect(overlay(page)).toBeHidden()

    // same shape of bug as rows-per-page: $toggle() writes _table.show_trashed,
    // which swaps the whole result set out
    await page.locator('[data-atom-table-trashed]').click()

    await expect(overlay(page)).toBeVisible()
    await expect(overlay(page)).toBeHidden({ timeout: 5000 })
  })

  test('the spinner stays on screen wherever the fold falls in a long table', async ({ page }) => {
    await page.setViewportSize({ width: 1200, height: 762 })
    await page.goto('/atom/e2e/table-loading')
    await page.waitForLoadState('networkidle')

    const viewport = page.viewportSize()

    // guard against a vacuous pass: this only tests anything while the table runs
    // well past the fold, which is what the fixture's 100 rows are for
    const rows = await rect(page, '[data-atom-table-rows]')
    expect(rows.height).toBeGreaterThan(viewport.height * 3)

    // Sort by clicking the header from script. A Playwright click scrolls its
    // target into view first, which would pin the page to the top of the table
    // and hide the bug.
    await page.evaluate(() => window.scrollTo(0, 600))
    await page.evaluate(() => document.querySelector('[data-atom-table-columns] th [x-data]').click())
    await expect(overlay(page)).toBeVisible()

    // Walk the fold down the table while the request is still in flight. One
    // offset proves nothing: a spinner centred in the whole table box lands on
    // screen at the one position where the table's own midpoint happens to be
    // in the viewport, and passes there by coincidence.
    for (const y of [600, 1400, 2600, 3400]) {
      await page.evaluate(offset => window.scrollTo(0, offset), y)

      const veil = await rect(page, '[data-atom-table-loading]')
      const icon = await rect(page, '[data-atom-table-loading] svg')

      // Precondition for this offset: the veil straddles the viewport, so the
      // table really does run off both edges of the screen. This also catches a
      // request that finished early — a hidden element measures 0 × 0 and would
      // otherwise satisfy the assertions below without proving anything.
      expect(veil.top, `veil top at scrollY ${y}`).toBeLessThan(0)
      expect(veil.bottom, `veil bottom at scrollY ${y}`).toBeGreaterThan(viewport.height)

      // KEY ASSERTION: the spinner — the part that says "working" — is on
      // screen. Centred in the full table box it sits thousands of pixels below
      // the fold, so the user gets a greyed-out table and no indicator at all.
      expect(icon.top, `spinner top at scrollY ${y}`).toBeGreaterThanOrEqual(0)
      expect(icon.bottom, `spinner bottom at scrollY ${y}`).toBeLessThanOrEqual(viewport.height)
    }
  })
})
