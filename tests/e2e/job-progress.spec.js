import { test, expect } from '@playwright/test'

// <atom:progress :job> on a Livewire page (#115). tests/Fixtures/JobProgressFixture.php reads the signed-in user's entry, and
// /atom/e2e/job-progress/state stands in for a worker by writing it (and forgetting it). The fixture polls every second. What is
// proven here is the part Pest cannot see: that the page really polls while the job is active, stops when it is done, starts again for
// a new run (a node that merely gains wire:poll never would), sends one request per tick however many jobs it shows, and sleeps inside
// a closed modal.

const UPDATE = /livewire[^/]*\/update/

const state = async (page, query) => {
  const response = await page.request.get(`/atom/e2e/job-progress/state?${query}`)
  expect(response.ok()).toBe(true)
}

const open = async (page, query = '') => {
  const css = await page.request.get('/atom/e2e/consumer-css/latest')
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/job-progress${query ? '?' + query : ''}`)
  await page.waitForFunction(() => window.Livewire !== undefined && document.querySelector('[data-testid="job-progress-page"]') !== null)
}

/** Count the Livewire update requests the page sends. */
const counter = (page) => {
  const counter = { n: 0 }
  page.on('request', (request) => {
    if (request.method() === 'POST' && UPDATE.test(request.url())) counter.n++
  })
  return counter
}

const bar = (page, testid = 'export') => page.locator(`[data-testid="${testid}"] [role="progressbar"]`)

test.describe('job progress', () => {
  test.use({ viewport: { width: 1000, height: 700 } })

  test('polls while running, follows the numbers, and the bar is in step with the state', async ({ page }) => {
    await state(page, 'status=running&done=1&total=4&run=a')
    await open(page)

    const updates = counter(page)

    await expect(bar(page)).toHaveAttribute('aria-valuenow', '25')

    await state(page, 'status=running&done=3&total=4&run=a')

    await expect(bar(page)).toHaveAttribute('aria-valuenow', '75', { timeout: 4000 })
    expect(updates.n).toBeGreaterThanOrEqual(1)

    // still the same node, polling on, across several re-renders
    await page.waitForTimeout(2500)
    expect(updates.n).toBeGreaterThanOrEqual(3)
  })

  test('shows Done and Download when it finishes, then sends nothing more', async ({ page }) => {
    await state(page, 'status=running&done=1&total=4&run=a')
    await open(page)

    const updates = counter(page)

    await state(page, 'status=finished&done=4&total=4&run=a&file=1')

    await expect(page.locator('[data-testid="export"]')).toContainText('Done', { timeout: 4000 })
    await expect(page.locator('[data-atom-progress-download]')).toBeVisible()
    await expect(page.locator('[data-atom-progress-file]')).toHaveText('journal.xlsx')

    const settled = updates.n
    await page.waitForTimeout(4000)

    expect(updates.n, 'a finished job must not keep polling').toBe(settled)

    // the path never reaches the page
    expect(await page.content()).not.toContain('secret-7')

    // the host's own action serves it
    await page.locator('[data-atom-progress-download]').click()
    await expect(page.locator('[data-testid="downloaded"]')).toHaveText('journal.xlsx')
  })

  test('stops for a failed job, and shows the generic line and the reported messages', async ({ page }) => {
    await state(page, 'status=running&done=1&total=4&run=a')
    await open(page)

    const updates = counter(page)

    await state(page, 'status=failed&done=1&total=4&run=a&errors=2')

    await expect(page.locator('[data-atom-progress-errors]')).toContainText('The job stopped before it finished.', { timeout: 4000 })
    await expect(page.locator('[data-atom-progress-errors] li')).toHaveCount(2)

    const settled = updates.n
    await page.waitForTimeout(3000)

    expect(updates.n).toBe(settled)
  })

  test('polls again for a new run after a finished one', async ({ page }) => {
    await state(page, 'status=finished&done=4&total=4&run=a&file=1')
    await open(page)

    const updates = counter(page)
    await page.waitForTimeout(2500)

    expect(updates.n, 'a finished job does not poll').toBe(0)

    // a new run replaces the entry; the host's button re-renders the page, and the new run's node starts polling
    await state(page, 'status=running&done=1&total=4&run=b')
    await page.locator('[data-testid="again"]').click()

    await expect(bar(page)).toHaveAttribute('aria-valuenow', '25')

    const first = updates.n
    await state(page, 'status=running&done=2&total=4&run=b')

    await expect(bar(page)).toHaveAttribute('aria-valuenow', '50', { timeout: 4000 })
    expect(updates.n).toBeGreaterThan(first)
  })

  test('polls again when a failed run is retried: the queue keeps the run id, so only the state changes', async ({ page }) => {
    await state(page, 'status=failed&done=1&total=4&run=a')
    await open(page)

    const updates = counter(page)
    await page.waitForTimeout(2000)

    expect(updates.n).toBe(0)

    await state(page, 'status=running&done=1&total=4&run=a')
    await page.locator('[data-testid="again"]').click()
    await expect(bar(page)).toHaveAttribute('aria-valuenow', '25')

    await state(page, 'status=running&done=3&total=4&run=a')

    await expect(bar(page)).toHaveAttribute('aria-valuenow', '75', { timeout: 4000 })
  })

  test('disappears, and stops polling, when the entry is forgotten mid-poll', async ({ page }) => {
    await state(page, 'status=running&done=1&total=4&run=a')
    await open(page)

    const updates = counter(page)

    await state(page, 'forget=1')

    await expect(page.locator('[data-testid="start"]')).toBeVisible({ timeout: 4000 })
    await expect(page.locator('[data-testid="export"]')).toHaveCount(0)

    const settled = updates.n
    await page.waitForTimeout(3000)

    expect(updates.n).toBe(settled)
  })

  test('sends one request per tick for two jobs on a page, not two', async ({ page }) => {
    await state(page, 'status=running&done=1&total=4&run=a')
    await state(page, 'name=other&status=running&done=1&total=4&run=a')
    await open(page, 'second=1')

    await expect(bar(page, 'other')).toBeVisible()

    const updates = counter(page)
    await page.waitForTimeout(3500)

    // one clock: about three or four ticks, where two clocks would make six or more
    expect(updates.n).toBeGreaterThanOrEqual(2)
    expect(updates.n).toBeLessThanOrEqual(5)
  })

  test('sleeps inside a closed modal and wakes when it opens', async ({ page }) => {
    await state(page, 'status=finished&done=4&total=4&run=a')
    await state(page, 'name=modal&status=running&done=1&total=4&run=a')
    await open(page, 'modal=1')

    const updates = counter(page)
    await page.waitForTimeout(3000)

    expect(updates.n, 'a poll in a closed modal is paused').toBe(0)

    await page.locator('[data-testid="open-modal"]').click()
    await expect(page.locator('[data-testid="inside"]')).toBeVisible()

    await expect.poll(() => updates.n, { timeout: 4000 }).toBeGreaterThan(0)
  })
})
