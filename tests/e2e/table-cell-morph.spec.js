import { test, expect } from '@playwright/test'

// Issue #263: a table cell ended in `@endif@endif`, and Livewire's morph markers skip a directive glued to a word character, so every
// non-checkbox cell kept a `<!--[if BLOCK]-->` with no end. When a filled cell became the empty mark, Alpine morph built a Block with a
// null end and threw `Block.appendChild`, an unhandled rejection that left duplicate or stale rows. The rows are an UN-keyed `@foreach`
// (tests/Fixtures/TableCellMorphFixture.php), so the filled row's nodes are patched into the empty one.

const open = async (page, query = '') => {
  const problems = []
  page.on('pageerror', (error) => problems.push(error.message)) // an unhandled rejection is a pageerror in Chromium
  page.on('console', (message) => message.type() === 'error' && problems.push(message.text()))
  await page.goto('/atom/e2e/table-cell-morph' + query)
  return problems
}

const show = async (page, set) => {
  const update = page.waitForResponse((r) => r.url().includes('/update') && r.request().method() === 'POST')
  await page.locator(`[data-show="${set}"]`).click()
  expect((await update).status()).toBe(200)
}

/** The text of every body row's cells, with the missing-value mark and its screen-reader text read as "-". */
const rows = (page) =>
  page.locator('[data-testid="people"] tbody tr').evaluateAll((trs) =>
    trs.map((tr) => [...tr.querySelectorAll('td')].map((td) => (td.querySelector('[data-atom-missing]') ? '-' : td.textContent.trim()))),
  )

const full = [
  ['Row one', 'one@example.test'],
  ['Row two', 'two@example.test'],
  ['Row three', 'three@example.test'],
]
const blank = [['Row one', '-'], ['Row two', '-'], ['Row three', '-']]

for (const [name, query] of [['un-keyed rows', ''], ['keyed rows', '?keyed=1']]) {
  test(`${name}: a filled cell becomes the empty mark and back, with no error`, async ({ page }) => {
    const problems = await open(page, query)
    expect(await rows(page)).toEqual(full)

    await show(page, 'blank')
    expect(await rows(page)).toEqual(blank)

    await show(page, 'full')
    expect(await rows(page)).toEqual(full)

    await show(page, 'mixed')
    expect(await rows(page)).toEqual([['Row one', '-'], ['Row two', 'two@example.test']])

    await show(page, 'blank')
    expect(await rows(page)).toEqual(blank)

    // the markers are still paired in the page, so the next morph has something to walk
    const markers = await page.evaluate(() => {
      const comments = []
      const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT)
      while (walker.nextNode()) comments.push(walker.currentNode.data)
      return [comments.filter((c) => c === '[if BLOCK]><![endif]').length, comments.filter((c) => c === '[if ENDBLOCK]><![endif]').length]
    })
    expect(markers[1]).toBe(markers[0])
    expect(problems).toEqual([])
  })
}

test('a cell with a filler of its own morphs from filled to the filler with no error', async ({ page }) => {
  const problems = await open(page, '?filler=1')

  await show(page, 'blank')
  expect(await page.locator('[data-testid="people"] tbody tr').evaluateAll((trs) => trs.map((tr) => tr.querySelectorAll('td')[1].textContent.trim()))).toEqual(['n/a', 'n/a', 'n/a'])

  await show(page, 'full')
  expect(await rows(page)).toEqual(full)
  expect(problems).toEqual([])
})
