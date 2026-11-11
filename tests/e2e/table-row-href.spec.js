import { test, expect } from '@playwright/test'

// <atom:table.row href> is reachable and opens without a mouse (#207): Tab reaches the row, Enter on the row itself opens it, Ctrl or Cmd with a
// click and a middle click open it in a new tab, and a click on a control inside the row stays that control's. The row keeps its table semantics (no
// role). The page is resources/views/e2e/table-row-href.blade.php, on the consumer's Tailwind (?tw=floor|latest).

const open = async (page, tw = 'latest') => {
  await page.goto(`/atom/e2e/table-row-href?tw=${tw}`)
  await page.waitForFunction(() => window.Livewire && window.Alpine)
}

const row = (page, name) => page.locator(`[data-row="${name}"]`)

// a click lands on the row's first cell: Playwright's click on the <tr> itself is a click on the middle of the table
const cell = (page, text) => page.getByText(text, { exact: true })

// Ctrl-click is a context menu on macOS (Cmd-click is the new tab there), so a real click uses whichever the platform means
const newTabModifier = 'ControlOrMeta'

for (const tw of ['floor', 'latest']) {
  test.describe(`on the ${tw} build`, () => {
    test('Tab reaches the row after the control before the table, with a visible focus ring', async ({ page }) => {
      await open(page, tw)

      await page.getByTestId('before').focus()
      await page.keyboard.press('Tab')

      await expect(row(page, 'plain')).toBeFocused()
      const ring = await row(page, 'plain').evaluate((el) => {
        const s = getComputedStyle(el)
        return { style: s.outlineStyle, width: s.outlineWidth }
      })
      expect(ring).toEqual({ style: 'solid', width: '2px' })
    })

    test('a row with no href, and one with a blocked href, are not in the tab order', async ({ page }) => {
      await open(page, tw)

      for (const name of ['none', 'blocked']) {
        await expect(row(page, name)).not.toHaveAttribute('tabindex', /.*/)
        await expect(row(page, name)).not.toHaveAttribute('data-href', /.*/)
      }

      // Tab from the SPA row's last control goes straight to the button after the table
      await row(page, 'spa').focus()
      await page.keyboard.press('Tab')
      await expect(page.getByTestId('after')).toBeFocused()
    })
  })
}

test('the row stays a table row to assistive tech: no link role', async ({ page }) => {
  await open(page)

  await expect(row(page, 'plain')).not.toHaveAttribute('role', /.*/)
  expect(await row(page, 'plain').evaluate((el) => el.tagName)).toBe('TR')
})

test('Enter on the focused row opens its href', async ({ page }) => {
  await open(page)

  await row(page, 'plain').focus()
  await page.keyboard.press('Enter')

  await expect(page).toHaveURL(/go=plain$/)
})

test('Enter on a wire:navigate row goes through Livewire, not a page load', async ({ page }) => {
  await open(page)
  await page.evaluate(() => {
    window.__loaded = true
    window.__navigated = []
    document.addEventListener('livewire:navigate', (e) => window.__navigated.push(e.detail.url.pathname + e.detail.url.search))
  })

  await row(page, 'spa').focus()
  await page.keyboard.press('Enter')

  await expect(page).toHaveURL(/go=spa$/)
  expect(await page.evaluate(() => window.__loaded)).toBe(true)
  expect(await page.evaluate(() => window.__navigated)).toEqual(['/atom/e2e/table-row-href?go=spa'])
})

test('a click on the row still opens it', async ({ page }) => {
  await open(page)

  await page.getByText('Plain row').click()

  await expect(page).toHaveURL(/go=plain$/)
})

test('Enter inside a field in the row belongs to the field, not the row', async ({ page }) => {
  await open(page)

  await page.getByTestId('input').focus()
  await page.keyboard.press('Enter')

  await expect(page).not.toHaveURL(/go=/)
})

test('Enter on a button in the row presses the button and does not open the row', async ({ page }) => {
  await open(page)

  await page.getByTestId('button').focus()
  await page.keyboard.press('Enter')

  await expect(page.getByTestId('button')).toHaveAttribute('data-clicked', '1')
  await expect(page).not.toHaveURL(/go=/)
})

test.describe('a new tab', () => {
  test('a Ctrl-click and a Cmd-click open the href in a new page and leave this one', async ({ page, context }) => {
    await open(page)

    // the platform's own modifier, as a real click
    const real = context.waitForEvent('page')
    await page.getByText('Plain row').click({ modifiers: [newTabModifier] })
    const realTab = await real
    await realTab.waitForLoadState()
    expect(realTab.url()).toMatch(/go=plain$/)
    await realTab.close()

    // both modifiers, as the click event they set

    for (const modifier of ['Control', 'Meta']) {
      const opened = context.waitForEvent('page')
      await page.getByText('Plain row').dispatchEvent('click', modifier === 'Control' ? { ctrlKey: true, bubbles: true } : { metaKey: true, bubbles: true })
      const tab = await opened

      await tab.waitForLoadState()
      expect(tab.url()).toMatch(/go=plain$/)
      await tab.close()
    }

    await expect(page).not.toHaveURL(/go=/)
  })

  test('Ctrl-Enter on the focused row opens the href in a new page', async ({ page, context }) => {
    await open(page)

    await row(page, 'plain').focus()
    const opened = context.waitForEvent('page')
    await page.keyboard.press('Control+Enter')
    const tab = await opened

    await tab.waitForLoadState()
    expect(tab.url()).toMatch(/go=plain$/)
    await expect(page).not.toHaveURL(/go=/)
  })

  test('a middle click opens the href in a new page, on a plain row and on a wire:navigate row', async ({ page, context }) => {
    await open(page)

    for (const [name, go] of [['plain', 'plain'], ['spa', 'spa']]) {
      const opened = context.waitForEvent('page')
      await cell(page, name === 'plain' ? 'Plain row' : 'SPA row').click({ button: 'middle' })
      const tab = await opened

      await tab.waitForLoadState()
      expect(tab.url()).toMatch(new RegExp(`go=${go}$`))
      await tab.close()
    }

    await expect(page).not.toHaveURL(/go=/)
  })

  test('a middle click on a link in the row opens only the link\'s own page', async ({ page, context }) => {
    await open(page)

    const pages = []
    context.on('page', (p) => pages.push(p))
    await page.getByTestId('link').click({ button: 'middle' })
    await page.waitForTimeout(500)

    expect(pages).toHaveLength(1)
    await pages[0].waitForLoadState()
    expect(pages[0].url()).toMatch(/go=link$/)
  })
})

test.describe('controls inside the row do not open it', () => {
  const controls = { checkbox: (page) => page.getByTestId('checkbox'), button: (page) => page.getByTestId('button'), input: (page) => page.getByTestId('input'), 'actions menu button': (page) => page.locator('[data-atom-table-actions] [aria-haspopup="menu"]') }

  for (const [name, control] of Object.entries(controls)) {
    test(`a click on the ${name}`, async ({ page }) => {
      await open(page)
      const popups = []
      page.context().on('page', (p) => popups.push(p))

      await control(page).click()
      await control(page).click({ modifiers: [newTabModifier] })
      await control(page).click({ button: 'middle' })
      await page.waitForTimeout(300)

      await expect(page).not.toHaveURL(/go=/)
      expect(popups).toHaveLength(0)
    })
  }

  test('an item picked from the actions menu does not open the row', async ({ page }) => {
    await open(page)

    await page.locator('[data-atom-table-actions] [aria-haspopup="menu"]').click()
    await page.getByRole('menuitem', { name: 'Edit' }).click()

    await expect(page).not.toHaveURL(/go=/)
  })

  test('a click on a link in the row follows the link, not the row', async ({ page }) => {
    await open(page)

    await page.getByTestId('link').click()

    await expect(page).toHaveURL(/go=link$/)
  })

  test('the checkbox still toggles', async ({ page }) => {
    await open(page)

    await page.getByTestId('checkbox').check()

    await expect(page.getByTestId('checkbox')).toBeChecked()
  })
})

test('a row with no href, and one with a blocked href, do nothing on a click', async ({ page }) => {
  await open(page)

  await cell(page, 'No href').click()
  await cell(page, 'Blocked href').click()

  await expect(page).not.toHaveURL(/go=/)
  expect(await page.evaluate(() => window.__pwned)).toBeUndefined()
})
