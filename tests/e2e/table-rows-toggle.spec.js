import { test, expect } from '@playwright/test'

// <atom:table rows-toggle-when-selected> (#235): while anything is ticked, a click on a row that has a checkbox cell ticks or unticks that
// row instead of opening it. The page is resources/views/e2e/table-row-toggle.blade.php (fixture: tests/Fixtures/TableRowToggleFixture.php),
// on the consumer's Tailwind (?tw=floor|latest); ?off=1 leaves the prop off, ?sticky=1 adds sticky-selection, ?all=1 starts in select-all
// mode and ?ticked=1,2 starts with those rows ticked.

const open = async (page, query = '') => {
  await page.goto(`/atom/e2e/table-row-toggle?tw=latest${query}`)
  await page.waitForFunction(() => window.Livewire && window.Alpine)
}

const row = (page, name) => page.locator(`[data-row="${name}"]`)
const box = (page, name) => row(page, name).locator('[data-atom-table-checkbox]')
const checked = (page) => page.evaluate(() => [...window.Livewire.all()[0].$wire._table.checkboxes].map(String))

// a click lands on the row's name cell: Playwright's click on the <tr> itself is the middle of the table
const nameCell = (page, text) => page.getByText(text, { exact: true })

for (const tw of ['floor', 'latest']) {
  test(`nothing ticked: a click on a row opens it as before (${tw})`, async ({ page }) => {
    await page.goto(`/atom/e2e/table-row-toggle?tw=${tw}`)
    await page.waitForFunction(() => window.Livewire && window.Alpine)

    await nameCell(page, 'Plain row').click()

    await expect(page).toHaveURL(/go=plain$/)
  })
}

test('something ticked: a click on a plain href row ticks it and does not navigate; a second click unticks it', async ({ page }) => {
  await open(page, '&ticked=2')

  await nameCell(page, 'Plain row').click()

  await expect(box(page, 'plain')).toHaveAttribute('data-checked', /.*/)
  expect(await checked(page)).toEqual(['2', '1'])
  await expect(page).not.toHaveURL(/go=/)

  await nameCell(page, 'Plain row').click()

  await expect(box(page, 'plain')).not.toHaveAttribute('data-checked', /.*/)
  expect(await checked(page)).toEqual(['2'])
  await expect(page).not.toHaveURL(/go=/)
})

test('a wire:navigate row toggles and does not go through Livewire', async ({ page }) => {
  await open(page, '&ticked=1')
  await page.evaluate(() => {
    window.__navigated = []
    document.addEventListener('livewire:navigate', (e) => window.__navigated.push(e.detail.url.pathname + e.detail.url.search))
  })

  await nameCell(page, 'SPA row').click()

  expect(await checked(page)).toEqual(['1', '2'])
  await page.waitForTimeout(200)
  expect(await page.evaluate(() => window.__navigated)).toEqual([])
  await expect(page).not.toHaveURL(/go=/)
})

test('a wire:click row toggles instead of running its handler', async ({ page }) => {
  await open(page, '&ticked=1')

  await nameCell(page, 'Wire row').click()
  await page.waitForTimeout(300)

  expect(await checked(page)).toEqual(['1', '3'])
  await expect(page.getByTestId('pings')).toHaveText('0')

  // untick the others: the wire:click row opens again once nothing is ticked
  await nameCell(page, 'Wire row').click()
  await page.getByTestId('bulk-action').waitFor()
  await box(page, 'plain').click()
  expect(await checked(page)).toEqual([])
  await nameCell(page, 'Wire row').click()
  await expect(page.getByTestId('pings')).toHaveText('1')
})

test('a row with no checkbox cell still opens while rows are ticked', async ({ page }) => {
  await open(page, '&ticked=1')

  await nameCell(page, 'No checkbox row').click()

  await expect(page).toHaveURL(/go=nocheck$/)
})

test('a double-click on the only ticked row unticks it and does not open it', async ({ page }) => {
  await open(page, '&ticked=1')

  await nameCell(page, 'Plain row').dblclick()
  await page.waitForTimeout(300)

  expect(await checked(page)).toEqual([])
  await expect(page).not.toHaveURL(/go=/)
})

test('a click that ends a drag-select of the row text neither ticks nor opens it', async ({ page }) => {
  await open(page, '&ticked=2')

  const cellBox = await nameCell(page, 'Plain row').boundingBox()
  await page.mouse.move(cellBox.x + 2, cellBox.y + cellBox.height / 2)
  await page.mouse.down()
  await page.mouse.move(cellBox.x + 40, cellBox.y + cellBox.height / 2, { steps: 5 })
  await page.mouse.up()
  await page.waitForTimeout(300)

  expect(await page.evaluate(() => String(window.getSelection()))).not.toBe('')
  expect(await checked(page)).toEqual(['2'])
  await expect(page).not.toHaveURL(/go=/)
})

test('a selection outside the row does not swallow the click (rows with user-select: none)', async ({ page }) => {
  await open(page, '&ticked=2')
  await page.addStyleTag({ content: 'table, table * { user-select: none }' })
  await page.evaluate(() => {
    const el = document.querySelector('[data-testid="opened"]')
    el.textContent = 'outside the table'
    const range = document.createRange()
    range.selectNodeContents(el)
    const sel = window.getSelection()
    sel.removeAllRanges()
    sel.addRange(range)
  })
  expect(await page.evaluate(() => String(window.getSelection()))).not.toBe('')

  // the row is not selectable, so the click does not clear the selection
  await nameCell(page, 'Plain row').click()

  expect(await page.evaluate(() => String(window.getSelection()))).not.toBe('')
  expect(await checked(page)).toEqual(['2', '1'])
  await expect(page).not.toHaveURL(/go=/)
})

test('a row of a table inside a row belongs to that table, not the outer one', async ({ page }) => {
  await open(page, '&ticked=1&nested=1')

  await nameCell(page, 'Inner row').click()

  await expect(page).toHaveURL(/go=inner$/)
})

test('the cell of the checkbox itself ticks the row once, not twice', async ({ page }) => {
  await open(page, '&ticked=1')

  await box(page, 'spa').click()

  expect(await checked(page)).toEqual(['1', '2'])
  await expect(page).not.toHaveURL(/go=/)
})

test.describe('a control inside the row stays its own', () => {
  test('a button runs and the row is not ticked', async ({ page }) => {
    await open(page, '&ticked=2')

    await page.getByTestId('button').click()

    await expect(page.getByTestId('button')).toHaveAttribute('data-clicked', '1')
    expect(await checked(page)).toEqual(['2'])
    await expect(page).not.toHaveURL(/go=/)
  })

  test('a link follows its href and the row is not ticked', async ({ page }) => {
    await open(page, '&ticked=2')

    await page.getByTestId('link').click()

    await expect(page).toHaveURL(/go=link$/)
  })

  test('the row actions menu opens and the row is not ticked', async ({ page }) => {
    await open(page, '&ticked=2')

    await row(page, 'plain').getByTestId('actions-cell').getByRole('button').click()

    await expect(page.getByText('Edit')).toBeVisible()
    expect(await checked(page)).toEqual(['2'])
    await expect(page).not.toHaveURL(/go=/)
  })
})

test('Enter on a focused row ticks it while rows are ticked and does not open it', async ({ page }) => {
  await open(page, '&ticked=2')

  await row(page, 'plain').focus()
  await page.keyboard.press('Enter')

  expect(await checked(page)).toEqual(['2', '1'])
  await expect(page).not.toHaveURL(/go=/)

  await page.keyboard.press('Enter')

  expect(await checked(page)).toEqual(['2'])
})

test('Enter held down on the only ticked row unticks it once and the repeat does not open it', async ({ page }) => {
  await open(page, '&ticked=1')

  await row(page, 'plain').focus()
  await page.keyboard.down('Enter')
  await page.keyboard.down('Enter') // the second keydown of a held key is an auto-repeat
  await page.keyboard.up('Enter')
  await page.waitForTimeout(300)

  expect(await checked(page)).toEqual([])
  await expect(page).not.toHaveURL(/go=/)
})

test('Enter on a focused row opens it when nothing is ticked', async ({ page }) => {
  await open(page)

  await row(page, 'plain').focus()
  await page.keyboard.press('Enter')

  await expect(page).toHaveURL(/go=plain$/)
})

test('Ctrl or Cmd with a click opens a new tab and does not tick the row', async ({ page, context }) => {
  await open(page, '&ticked=2')

  const opened = context.waitForEvent('page')
  await nameCell(page, 'Plain row').dispatchEvent('click', { ctrlKey: true, bubbles: true })
  const tab = await opened
  await tab.waitForLoadState()

  expect(tab.url()).toMatch(/go=plain$/)
  await tab.close()
  expect(await checked(page)).toEqual(['2'])
  await expect(page).not.toHaveURL(/go=/)
})

test('select-all mode counts as ticked: a click ticks the row and leaves select-all', async ({ page }) => {
  await open(page, '&all=1')

  await nameCell(page, 'Plain row').click()

  await expect(page).not.toHaveURL(/go=/)
  expect(await checked(page)).toEqual(['1'])
  expect(await page.evaluate(() => window.Livewire.all()[0].$wire._table.select_all)).toBe(false)
})

test('with sticky-selection a click toggles too', async ({ page }) => {
  await open(page, '&ticked=1&sticky=1')

  await nameCell(page, 'SPA row').click()
  expect(await checked(page)).toEqual(['1', '2'])

  await nameCell(page, 'SPA row').click()
  expect(await checked(page)).toEqual(['1'])
  await expect(page).not.toHaveURL(/go=/)
})

test('the checked bar says how to open a record again', async ({ page }) => {
  await open(page, '&ticked=1')

  await expect(page.locator('[data-atom-table-rows-toggle-hint]')).toHaveText('Untick all to open a record')
})

test.describe('with the prop off', () => {
  test('a click while rows are ticked still opens the row, and the bar has no hint', async ({ page }) => {
    await open(page, '&ticked=1&off=1')

    await expect(page.locator('[data-atom-table-rows-toggle-hint]')).toHaveCount(0)
    await expect(page.locator('[data-atom-rows-toggle]')).toHaveCount(0)

    await nameCell(page, 'Plain row').click()

    await expect(page).toHaveURL(/go=plain$/)
  })

  test('a wire:navigate row still goes through Livewire', async ({ page }) => {
    await open(page, '&ticked=1&off=1')

    await nameCell(page, 'SPA row').click()

    await expect(page).toHaveURL(/go=spa$/)
  })
})
