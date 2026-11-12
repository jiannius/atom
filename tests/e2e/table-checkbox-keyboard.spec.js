import { test, expect } from '@playwright/test'

// The table checkbox is a control (#215): Tab reaches it, Space toggles it, it has a role, a name and an aria-checked that follows
// `$wire._table.checkboxes`, the select-all one works the same way, and none of it opens a row that has an href. Two fixtures:
// /atom/e2e/sticky-selection (a header checkbox, rows that carry `checkbox-label`, and a search whose round-trip morphs the table) and
// /atom/e2e/table-row-href (a row with an href, and the default "Select row" name).

const selected = (page, component) =>
  page.evaluate((name) => window.Livewire.all().find((c) => c.name === name).$wire.$get('_table.checkboxes').map(String), component)

const sticky = async (page) => {
  await page.goto('/atom/e2e/sticky-selection')
  await page.waitForFunction(() => window.Livewire && window.Alpine)
  await page.waitForLoadState('networkidle')
}

const selectedIds = (page) => selected(page, 'atom-e2e-sticky-selection')

test.describe('on the list with a header checkbox', () => {
  test('every checkbox is a named checkbox: the header "Select all", each row its own label', async ({ page }) => {
    await sticky(page)

    await expect(page.getByRole('checkbox', { name: 'Select all' })).toHaveCount(1)
    await expect(page.getByRole('checkbox', { name: 'Select Apple' })).toHaveCount(1)
    await expect(page.getByRole('checkbox')).toHaveCount(7)
    for (const box of await page.getByRole('checkbox').all()) {
      await expect(box).toHaveAttribute('tabindex', '0')
      await expect(box).toHaveAttribute('aria-checked', 'false')
    }
  })

  test('Tab walks from the header checkbox to the first row checkbox, and Space toggles it', async ({ page }) => {
    await sticky(page)

    await page.getByRole('checkbox', { name: 'Select all' }).focus()
    await page.keyboard.press('Tab')
    const apple = page.getByRole('checkbox', { name: 'Select Apple' })
    await expect(apple).toBeFocused()

    await page.keyboard.press('Space')
    await expect(apple).toHaveAttribute('aria-checked', 'true')
    await expect(apple).toHaveAttribute('data-checked', /.+/)
    await expect(page.getByRole('checkbox', { name: 'Select all' })).toHaveAttribute('aria-checked', 'false')
    expect(await selectedIds(page)).toEqual(['1'])

    await page.keyboard.press('Space')
    await expect(apple).toHaveAttribute('aria-checked', 'false')
    await expect(apple).not.toHaveAttribute('data-checked', /.+/)
    expect(await selectedIds(page)).toEqual([])
  })

  test('Space is prevented (so the page does not scroll), and an auto-repeat does not toggle it back', async ({ page }) => {
    await sticky(page)
    const apple = page.getByRole('checkbox', { name: 'Select Apple' })
    await apple.focus()

    const prevented = await apple.evaluate((el) => {
      const down = new KeyboardEvent('keydown', { key: ' ', code: 'Space', bubbles: true, cancelable: true })
      el.dispatchEvent(down)
      return down.defaultPrevented
    })
    expect(prevented).toBe(true)
    await expect(apple).toHaveAttribute('aria-checked', 'true')

    // an auto-repeat keydown is the same press: it must not toggle the box back
    await apple.evaluate((el) => el.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', code: 'Space', bubbles: true, cancelable: true, repeat: true })))
    await expect(apple).toHaveAttribute('aria-checked', 'true')
  })

  test('select all works by keyboard, and so does clearing it', async ({ page }) => {
    await sticky(page)
    const all = page.getByRole('checkbox', { name: 'Select all' })

    await all.focus()
    await page.keyboard.press('Space')
    await expect(all).toHaveAttribute('aria-checked', 'true')
    await expect(page.locator('[role=checkbox][aria-checked=true]')).toHaveCount(7)
    expect((await selectedIds(page)).sort()).toEqual(['1', '2', '3', '4', '5', '6'])

    await page.keyboard.press('Space')
    await expect(all).toHaveAttribute('aria-checked', 'false')
    expect(await selectedIds(page)).toEqual([])
  })

  test('a click still toggles, and aria-checked follows a selection made with the mouse', async ({ page }) => {
    await sticky(page)

    await page.getByRole('checkbox', { name: 'Select Banana' }).click()
    await expect(page.getByRole('checkbox', { name: 'Select Banana' })).toHaveAttribute('aria-checked', 'true')
    expect(await selectedIds(page)).toEqual(['3'])
  })

  test('the state survives a Livewire morph (a search round-trip), and Space still works afterwards', async ({ page }) => {
    await sticky(page)
    await page.getByRole('checkbox', { name: 'Select Apple' }).press('Space')
    await expect(page.getByRole('checkbox', { name: 'Select Apple' })).toHaveAttribute('aria-checked', 'true')

    const box = page.locator('[data-atom-table-search] input')
    await box.fill('ap')
    await box.press('Enter')
    await expect(page.locator('[data-atom-table-row]')).toHaveCount(2)
    await expect(page.getByRole('checkbox', { name: 'Select Apple' })).toHaveAttribute('aria-checked', 'true')

    await page.getByRole('checkbox', { name: 'Select Apricot' }).press('Space')
    await expect(page.getByRole('checkbox', { name: 'Select Apricot' })).toHaveAttribute('aria-checked', 'true')

    await box.fill('')
    await box.press('Enter')
    await expect(page.locator('[data-atom-table-row]')).toHaveCount(6)
    await expect(page.getByRole('checkbox', { name: 'Select Apple' })).toHaveAttribute('aria-checked', 'true')
    await expect(page.getByRole('checkbox', { name: 'Select Cherry' })).toHaveAttribute('aria-checked', 'false')
    expect((await selectedIds(page)).sort()).toEqual(['1', '2'])
  })

  test('Tab focus is :focus-visible on a checkbox', async ({ page }) => {
    await sticky(page)
    const apple = page.getByRole('checkbox', { name: 'Select Apple' })

    await page.getByRole('checkbox', { name: 'Select all' }).focus()
    await page.keyboard.press('Tab')
    await expect(apple).toBeFocused()
    expect(await apple.evaluate((el) => el.matches(':focus-visible'))).toBe(true)
  })
})

test.describe('in a row that opens an href', () => {
  const open = async (page) => {
    await page.goto('/atom/e2e/table-row-href')
    await page.waitForFunction(() => window.Livewire && window.Alpine)
  }
  const box = (page) => page.getByRole('checkbox', { name: 'Select row' })

  test('the default name is "Select row", and a click, Space and Enter open nothing', async ({ page }) => {
    await open(page)

    await expect(box(page)).toHaveCount(1)
    await box(page).focus()
    await expect(box(page)).toBeFocused()

    await page.keyboard.press('Space')
    await expect(box(page)).toHaveAttribute('aria-checked', 'true')
    expect(await selected(page, 'atom-e2e-table-row-href')).toEqual(['1'])

    await page.keyboard.press('Enter')
    await box(page).click()
    await page.waitForTimeout(300)

    await expect(page).not.toHaveURL(/go=/)
    await expect(box(page)).toHaveAttribute('aria-checked', 'false')
  })

  test('a keyboard user sees a focus ring on the consumer\'s Tailwind, floor and latest', async ({ page }) => {
    for (const tw of ['floor', 'latest']) {
      await page.goto(`/atom/e2e/table-row-href?tw=${tw}`)
      await page.waitForFunction(() => window.Livewire && window.Alpine)
      await page.getByTestId('input').focus()
      await page.keyboard.press('Tab')
      // the next stop after the note field is not necessarily the box; walk until it is
      for (let i = 0; i < 12 && !(await box(page).evaluate((el) => el === document.activeElement)); i++) await page.keyboard.press('Tab')

      await expect(box(page)).toBeFocused()
      const ring = await box(page).evaluate((el) => { const s = getComputedStyle(el); return { style: s.outlineStyle, width: s.outlineWidth } })
      expect(ring).toEqual({ style: 'solid', width: '2px' })
    }
  })
})
