import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/url-filters (tests/Fixtures/UrlFiltersFixture.php),
// which uses Traits\WithUrlFilters.
//
// The filters a user chose on a listing used to be lost when they opened a record and came
// back through the breadcrumb, because they only ever lived in Livewire state. They are in
// the URL now, and the breadcrumb already saves the listing's full URL. The rig serves no
// Tailwind, so everything is asserted on the URL, the $wire state and the rows, not styling.

const box = page => page.locator('[data-atom-table-search] input')
const rows = page => page.locator('[data-atom-table-row]')
const names = page => page.locator('[data-name]').allTextContents().then(list => list.map(name => name.trim()))
const state = page => page.evaluate(() => Livewire.all().find(c => c.name === 'atom-e2e-url-filters').$wire.filters)
const query = page => Object.fromEntries(new URL(page.url()).searchParams)

async function pickStatus (page, label) {
  await page.locator('[data-atom-table-filters]').getByRole('combobox', { name: 'Status' }).click()
  await page.locator('[data-atom-option]').filter({ hasText: label }).first().click()
}

// a listing filtered to "a" and Fresh: the one row, Apple
async function filter (page) {
  await page.goto('/atom/e2e/url-filters')
  await page.waitForLoadState('networkidle')

  await box(page).fill('a')
  await expect(rows(page)).toHaveCount(2)   // Apple, Apricot: the default region is MY
  await pickStatus(page, 'Fresh')
  await expect(rows(page)).toHaveCount(1)
  await expect.poll(() => query(page)).toMatchObject({ search: 'a', 'status[0]': 'fresh' })
}

async function openRecord (page) {
  await page.locator('[data-open-record]').click()
  await expect(page).toHaveURL(/\/atom\/e2e\/url-filters\/record$/)
  await expect(page.locator('[data-atom-breadcrumbs] ol a')).toHaveCount(1)
}

async function expectRestored (page) {
  await expect(page).toHaveURL(/\/atom\/e2e\/url-filters\?/)
  await expect.poll(() => query(page)).toMatchObject({ search: 'a', 'status[0]': 'fresh' })
  expect(query(page)).not.toHaveProperty('region')   // the default is never written
  expect(await state(page)).toEqual({ search: 'a', status: ['fresh'], region: 'MY', tenant: 7 })
  await expect(box(page)).toHaveValue('a')
  expect(await names(page)).toEqual(['Apple'])
}

test('the filters come back through the breadcrumb', async ({ page }) => {
  await filter(page)
  await openRecord(page)

  await page.locator('[data-atom-breadcrumbs] ol a').click()

  await expectRestored(page)
})

test('the filters come back through Back', async ({ page }) => {
  const errors = []
  page.on('pageerror', error => errors.push(error.message))

  await filter(page)
  await openRecord(page)

  await page.locator('[data-back]').click()

  await expectRestored(page)
  expect(errors).toEqual([])
})

test('the filters come back through the browser Back button', async ({ page }) => {
  await filter(page)
  await openRecord(page)

  await page.goBack()

  await expectRestored(page)
})

test('clearing a filter takes its key out of the URL; "All" stays in it', async ({ page }) => {
  await filter(page)

  await box(page).fill('')
  await expect(rows(page)).toHaveCount(2)   // Apple and Cherry: Fresh in MY
  await expect.poll(() => query(page)).not.toHaveProperty('search')

  await page.evaluate(() => Livewire.all().find(c => c.name === 'atom-e2e-url-filters').$wire.$set('filters.status', []))
  await expect.poll(() => query(page)).not.toHaveProperty('status[0]')
  await expect(rows(page)).toHaveCount(3)

  // "All" is not the default (MY), so it has to be written, or a reload would bring MY back
  await page.evaluate(() => Livewire.all().find(c => c.name === 'atom-e2e-url-filters').$wire.$set('filters.region', ''))
  await expect.poll(() => query(page)).toHaveProperty('region', '')
  await expect(rows(page)).toHaveCount(5)

  await page.reload()
  await page.waitForLoadState('networkidle')
  expect((await state(page)).region).toBe('')
  await expect(rows(page)).toHaveCount(5)

  // and back to the default leaves the URL again
  await page.evaluate(() => Livewire.all().find(c => c.name === 'atom-e2e-url-filters').$wire.$set('filters.region', 'MY'))
  await expect.poll(() => query(page)).not.toHaveProperty('region')
})

test('a planted link opens the listing clean: unknown keys, bad shapes and the tenant are ignored', async ({ page }) => {
  await page.goto('/atom/e2e/url-filters?evil=1&tenant=9&region=XX&search[0]=a&status=fresh')
  await page.waitForLoadState('networkidle')

  expect(await state(page)).toEqual({ search: '', status: [], region: 'MY', tenant: 7 })
  expect(await names(page)).toEqual(['Apple', 'Apricot', 'Cherry'])   // never the other tenant's Secret
})

test('the browser cannot set a key nobody declared, or the tenant: the server state is unchanged', async ({ page }) => {
  await page.goto('/atom/e2e/url-filters?search=ap')
  await page.waitForLoadState('networkidle')

  // Livewire keeps no state on the server: it is the snapshot, and `canonical` is the last one the server
  // confirmed (a refused update never reaches it, while `$wire` shows what the browser asked for). A call
  // would replay the refused updates and be refused again, so the confirmed state is read directly.
  const server = async () => ({
    filters: await page.evaluate(() => Livewire.all().find(c => c.name === 'atom-e2e-url-filters').canonical.filters),
    rows: await names(page),
  })
  const before = await server()

  expect(before).toEqual({ filters: { search: 'ap', status: [], region: 'MY', tenant: 7 }, rows: ['Apple', 'Apricot'] })

  const refused = await page.evaluate(async () => {
    const wire = Livewire.all().find(c => c.name === 'atom-e2e-url-filters').$wire
    const attempts = [
      () => wire.$set('filters.tenant', 9),
      () => wire.$set('filters.evil', 'x'),
      () => wire.$set('filters', { tenant: 9 }),
      () => wire.$set('filters', { search: ['a'] }),
      () => wire.$set('filters.status', 'fresh'),
    ]
    const results = []

    for (const attempt of attempts) {
      try { await attempt(); results.push('accepted') } catch (e) { results.push('refused') }
    }

    return results
  })

  expect(refused).toEqual(['refused', 'refused', 'refused', 'refused', 'refused'])

  // the tenant is still 7, nothing was added, and the other tenant's row is still not listed
  expect(await server()).toEqual(before)
})
