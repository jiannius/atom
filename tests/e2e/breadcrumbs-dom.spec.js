import { test, expect } from '@playwright/test'

// breadcrumbs.spec.js drives the trail-merging logic with a stubbed document, so
// it cannot see how the Livewire root is found in a real page. These cases render
// the actual sidebar layout — whose sr-only <h1> sits in front of the Livewire
// root when the page has a title — and assert the trail still resolves.

test('renders the trail inside a titled sidebar layout', async ({ page }) => {
  await page.goto('/atom/e2e/breadcrumbs')

  await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveText('Dashboard')
})

test('renders the trail when the component is inside a wrapper div', async ({ page }) => {
  await page.goto('/atom/e2e/breadcrumbs-wrapped')

  await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveText('Dashboard')
})

test('does not throw on a page whose component declares no breadcrumbs', async ({ page }) => {
  const errors = []
  page.on('pageerror', error => errors.push(error.message))

  await page.goto('/atom/e2e/breadcrumbs-untrailed')
  await page.evaluate(() => {
    window.dispatchEvent(new CustomEvent('livewire:navigated'))
    window.dispatchEvent(new CustomEvent('livewire:navigate'))
  })

  expect(errors).toEqual([])
  await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveCount(0)
})

test('the page title h1 precedes the Livewire root it labels', async ({ page }) => {
  await page.goto('/atom/e2e/breadcrumbs')

  const firstChildTag = await page.locator('[data-atom-main] > *').first().evaluate(el => el.tagName)
  const rootIsDeeper = await page.locator('[data-atom-main] > [wire\\:id]').count()

  expect(firstChildTag).toBe('H1')
  expect(rootIsDeeper).toBe(1)
})

// back() must land on the page as the visitor left it, query string included: the
// trail's `url` is the declared address (no query string), `href` is the full
// location getLatestHref() captured when the visitor navigated away.
test('back() returns to the listing with its query string', async ({ page }) => {
  const errors = []
  page.on('pageerror', error => errors.push(error.message))

  await page.goto('/atom/e2e/breadcrumbs-back?status=approved&page=2')

  await page.locator('[data-open-record]').click()
  await expect(page).toHaveURL(/\/atom\/e2e\/breadcrumbs-back\/record$/)
  await expect(page.locator('[data-atom-breadcrumbs] ol a')).toHaveCount(1)

  await page.evaluate(() => {
    window.__navigated = []
    // Livewire.navigate is a getter on a proxy, so a plain assignment is ignored
    Object.defineProperty(Livewire, 'navigate', { value: (url) => window.__navigated.push(url), configurable: true })
    window.dispatchEvent(new CustomEvent('navigate-back'))
  })

  const [target] = await page.evaluate(() => window.__navigated)
  const url = new URL(target, page.url())

  expect(url.pathname).toBe('/atom/e2e/breadcrumbs-back')
  expect(url.search).toBe('?status=approved&page=2')
  expect(errors).toEqual([])
})

// The v4 shells persist the trail in their `crumbs` slot, and a page that prints its own page.header shows its title there.
// The single-crumb heading would then say it twice, so it shows only on a page without one. The trail is one element that
// survives wire:navigate, so the check runs on every navigation rather than once at render.
test.describe('the single-crumb heading and page.header', () => {
  const heading = (page) => page.locator('[data-atom-breadcrumbs] [data-atom-heading]')
  const header = (page) => page.locator('[data-atom-main] h1[data-atom-page-header-title]')

  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
  })

  test('shows on a page without a page.header', async ({ page }) => {
    await page.goto('/atom/e2e/breadcrumbs-header/old')

    await expect(heading(page)).toHaveText('Old page')
    await expect(header(page)).toHaveCount(0)
  })

  test('is not printed on a page that has its own page.header', async ({ page }) => {
    await page.goto('/atom/e2e/breadcrumbs-header/new')

    await expect(header(page)).toHaveText('New page')
    // wait for the trail to be built (one crumb), so the absence below is not just the trail not being there yet
    await page.waitForFunction(() => {
      const root = document.querySelector('[data-atom-breadcrumbs]')
      return root && window.Alpine?.$data(root).breadcrumbs.length === 1
    })
    await expect(heading(page)).toHaveCount(0)
  })

  test('a page.header that a Livewire update renders later takes the heading away, with no navigation', async ({ page }) => {
    await page.goto('/atom/e2e/breadcrumbs-header/late')

    await expect(heading(page)).toHaveText('Late page')
    await expect(header(page)).toHaveCount(0)

    await page.locator('[data-load]').click()

    await expect(header(page)).toHaveText('Late page')
    await expect(heading(page)).toHaveCount(0)
  })

  test('follows the page through wire:navigate on the persisted trail, there and back', async ({ page }) => {
    const errors = []
    page.on('pageerror', error => errors.push(error.message))

    await page.goto('/atom/e2e/breadcrumbs-header/old')
    await page.evaluate(() => { document.querySelector('[data-atom-breadcrumbs]').dataset.marker = 'same-element' })
    await expect(heading(page)).toHaveText('Old page')

    await page.locator('[data-go="new"]').click()
    await expect(page).toHaveURL(/breadcrumbs-header\/new$/)
    await expect(header(page)).toHaveText('New page')
    await expect(heading(page)).toHaveCount(0)

    await page.locator('[data-go="old"]').click()
    await expect(page).toHaveURL(/breadcrumbs-header\/old$/)
    await expect(header(page)).toHaveCount(0)
    await expect(heading(page)).toHaveText('Old page')

    // the same persisted element all along: it was not rebuilt by a full load
    await expect(page.locator('[data-atom-breadcrumbs][data-marker="same-element"]')).toHaveCount(1)
    expect(errors).toEqual([])
  })

  test('with more than one crumb nothing changes: the trail shows and no heading, with or without a page.header', async ({ page }) => {
    await page.goto('/atom/e2e/breadcrumbs-header/plain')

    await expect(header(page)).toHaveCount(0)
    await expect(page.locator('[data-atom-breadcrumbs] ol li')).toHaveCount(2)
    await expect(heading(page)).toHaveCount(0)

    await page.goto('/atom/e2e/breadcrumbs-header/deep')

    await expect(header(page)).toHaveText('Detail')
    await expect(page.locator('[data-atom-breadcrumbs] ol li')).toHaveCount(2)
    await expect(heading(page)).toHaveCount(0)
  })
})
