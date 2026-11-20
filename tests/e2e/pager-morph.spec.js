import { test, expect } from '@playwright/test'

// Both pagers (`<atom:pagination>`, `<atom:table.pagination>`) over a paginator that goes from one page to many and back
// (tests/Fixtures/PagerMorphFixture.php). A `//` comment holding an apostrophe inside the nav's `{{ }}` echo made Livewire's morph
// precompiler lose the BLOCK marker of the next `@if`, so the pager printed an END marker too many; the same shape as #263 in the cell.

const open = async (page) => {
  const problems = []
  page.on('pageerror', (error) => problems.push(error.message))
  page.on('console', (message) => message.type() === 'error' && problems.push(message.text()))
  await page.goto('/atom/e2e/pager-morph')
  return problems
}

const show = async (page, set) => {
  const update = page.waitForResponse((r) => r.url().includes('/update') && r.request().method() === 'POST')
  await page.locator(`[data-show="${set}"]`).click()
  expect((await update).status()).toBe(200)
}

const markers = (page) =>
  page.evaluate(() => {
    const comments = []
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT)
    while (walker.nextNode()) comments.push(walker.currentNode.data)
    return [comments.filter((c) => c === '[if BLOCK]><![endif]').length, comments.filter((c) => c === '[if ENDBLOCK]><![endif]').length]
  })

const pages = (page, id) => page.locator(`[data-testid="${id}"] [aria-current="page"], [data-testid="${id}"] button[aria-label^="Go to page"]`).count()

test('the pagers go from one page to many and back with no error and balanced markers', async ({ page }) => {
  const problems = await open(page)

  for (const id of ['standalone', 'table-pager', 'table-pager-bare']) {
    expect(await pages(page, id)).toBe(0)
  }
  const [start, end] = await markers(page)
  expect(end).toBe(start)

  for (let round = 0; round < 2; round++) {
    await show(page, 'many')
    for (const id of ['standalone', 'table-pager', 'table-pager-bare']) {
      expect(await pages(page, id)).toBe(10)
    }
    await expect(page.locator('[data-testid="table-pager"]').getByRole('button', { name: 'rows / page' })).toBeVisible()
    await expect(page.locator('[data-testid="table-pager-bare"]').getByRole('button', { name: 'rows / page' })).toHaveCount(0)
    const [opened, closed] = await markers(page)
    expect(closed).toBe(opened)

    // a page click, then back to a single page
    await page.locator('[data-testid="standalone"]').getByRole('button', { name: 'Go to page 2' }).click()
    await expect(page.locator('[data-testid="standalone"] [aria-current="page"]')).toHaveText('2')
    await expect(page.locator('[data-testid="table-pager"] [aria-current="page"]')).toHaveText('2')

    await show(page, 'one')
    for (const id of ['standalone', 'table-pager', 'table-pager-bare']) {
      expect(await pages(page, id)).toBe(0)
    }
    const [a, b] = await markers(page)
    expect(b).toBe(a)
  }

  expect(problems).toEqual([])
})
