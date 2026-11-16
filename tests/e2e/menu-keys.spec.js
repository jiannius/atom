import { test, expect } from '@playwright/test'

// Up, Down, Home and End in atom's menus (resources/js/alpinejs/dropdown.js menuKey(), the fixture is tests/Fixtures/MenuKeysFixture.php): the ⋯ menu of a
// page header, a table toolbar and a table row, and a plain `<atom:menu popover>`. Opening with the keyboard lands on the first item, the keys wrap, a
// disabled, hidden or separator row is never a stop, and a field inside a menu keeps its own keys. The page is on the consumer's Tailwind (latest).
//
// WebKit does not focus a button on click, so every spec here focuses the trigger by hand and opens it with the keyboard.

const log = async (page) => JSON.parse(await page.getByTestId('log').textContent())
const focused = (page) => page.evaluate(() => document.activeElement?.getAttribute('data-testid') ?? document.activeElement?.tagName)

const open = async (page) => {
  await page.goto('/atom/e2e/menu-keys')
  await page.waitForFunction(() => window.Livewire && window.Alpine && window.atom)
}

// The ⋯ trigger of a surface, and the menu it opens
const trigger = (page, surface) => page.getByTestId(surface).getByRole('button', { name: /More actions/ })
const menuOf = (page, surface) => page.getByTestId(surface).locator('[data-atom-menu]')

const surfaces = {
  header: ['h-duplicate', 'h-print', 'h-delete'],
  toolbar: ['t-archive', 't-import', 't-link'],
  rows: ['r-edit', 'r-void'],
}

for (const [surface, items] of Object.entries(surfaces)) {
  test.describe(`the ${surface} menu`, () => {
    test('opens on the first item with Enter, and Down, Up, Home and End wrap', async ({ page }) => {
      await open(page)

      await trigger(page, surface).focus()
      await page.keyboard.press('Enter')
      await expect(menuOf(page, surface)).toBeVisible()
      expect(await focused(page)).toBe(items[0])

      // down to the end, and round to the first
      for (const next of [...items.slice(1), items[0]]) {
        await page.keyboard.press('ArrowDown')
        expect(await focused(page)).toBe(next)
      }

      // up from the first goes to the last, and back
      await page.keyboard.press('ArrowUp')
      expect(await focused(page)).toBe(items.at(-1))
      await page.keyboard.press('ArrowUp')
      expect(await focused(page)).toBe(items.at(-2))

      await page.keyboard.press('End')
      expect(await focused(page)).toBe(items.at(-1))
      await page.keyboard.press('Home')
      expect(await focused(page)).toBe(items[0])
    })

    test('Space opens it on the first item too, and Escape puts the focus back on the trigger', async ({ page }) => {
      await open(page)

      await trigger(page, surface).focus()
      await page.keyboard.press('Space')
      await expect(menuOf(page, surface)).toBeVisible()
      expect(await focused(page)).toBe(items[0])

      await page.keyboard.press('ArrowDown')
      await page.keyboard.press('Escape')
      await expect(menuOf(page, surface)).toBeHidden()
      await expect(trigger(page, surface)).toBeFocused()
      await expect(trigger(page, surface)).toHaveAttribute('aria-expanded', 'false')
    })

    test('Down on the trigger opens it on the first item, Up on the last', async ({ page }) => {
      await open(page)

      await trigger(page, surface).focus()
      await page.keyboard.press('ArrowDown')
      await expect(menuOf(page, surface)).toBeVisible()
      expect(await focused(page)).toBe(items[0])

      await page.keyboard.press('Escape')
      await expect(menuOf(page, surface)).toBeHidden()
      await expect(trigger(page, surface)).toBeFocused()

      await page.keyboard.press('ArrowUp')
      await expect(menuOf(page, surface)).toBeVisible()
      expect(await focused(page)).toBe(items.at(-1))
    })
  })
}

test.describe('a plain menu', () => {
  const trig = (page) => page.getByTestId('plain').getByRole('button', { name: 'Options' })

  test('skips a disabled, a hidden and a separator row, at both ends and between', async ({ page }) => {
    await open(page)

    await trig(page).focus()
    await page.keyboard.press('Enter')

    // "Disabled first" is not a stop: the menu opens on Edit
    expect(await focused(page)).toBe('p-edit')

    // "Hidden" (x-show false) and the separator are not stops either
    await page.keyboard.press('ArrowDown')
    expect(await focused(page)).toBe('p-link')
    await page.keyboard.press('ArrowDown')
    expect(await focused(page)).toBe('p-confirm')

    // "Disabled last" is not: Down wraps to Edit, Up from Edit goes to the confirm item, End and Home agree
    await page.keyboard.press('ArrowDown')
    expect(await focused(page)).toBe('p-edit')
    await page.keyboard.press('ArrowUp')
    expect(await focused(page)).toBe('p-confirm')
    await page.keyboard.press('Home')
    expect(await focused(page)).toBe('p-edit')
    await page.keyboard.press('End')
    expect(await focused(page)).toBe('p-confirm')
  })

  test('Enter on an item runs it and closes the menu', async ({ page }) => {
    await open(page)

    await trig(page).focus()
    await page.keyboard.press('Enter')
    await expect(page.getByTestId('p-edit')).toBeFocused()
    await page.keyboard.press('Enter')

    await expect.poll(() => log(page)).toEqual(['edit'])
    await expect(page.getByTestId('plain').locator('[data-atom-menu]')).toBeHidden()
  })

  test('Enter on a link item follows it', async ({ page }) => {
    await open(page)

    await trig(page).focus()
    await page.keyboard.press('Enter')
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('p-link')).toBeFocused()
    await page.keyboard.press('Enter')

    await expect(page.getByTestId('landing')).toBeVisible()
  })

  test('Enter on a confirm item, reached with the arrows, asks first and runs it on Accept', async ({ page }) => {
    await open(page)

    await trig(page).focus()
    await page.keyboard.press('Enter')
    await page.keyboard.press('End')
    await expect(page.getByTestId('p-confirm')).toBeFocused()
    await page.keyboard.press('Enter')

    const dialog = page.locator('dialog[x-data*="atom-confirm"]')
    await expect(dialog).toHaveAttribute('data-open', '')
    expect(await log(page)).toEqual([])

    await dialog.locator('button[type="submit"]').click()
    await expect(dialog).not.toHaveAttribute('data-open', '')
    await expect.poll(() => log(page)).toEqual(['cancel-job'])
  })

  test('Cancel on that confirm puts the focus back on the trigger, and the arrows work again from there', async ({ page }) => {
    await open(page)

    await trig(page).focus()
    await page.keyboard.press('Enter')
    await page.keyboard.press('End')
    await page.keyboard.press('Enter')

    const dialog = page.locator('dialog[x-data*="atom-confirm"]')
    await expect(dialog).toHaveAttribute('data-open', '')
    await page.keyboard.press('Escape')
    await expect(dialog).not.toHaveAttribute('data-open', '')
    await expect(trig(page)).toBeFocused()

    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('p-edit')).toBeFocused()
    expect(await log(page)).toEqual([])
  })

  test('a key with a modifier is the browser\'s', async ({ page }) => {
    await open(page)

    await trig(page).focus()
    await page.keyboard.press('Enter')
    await page.keyboard.press('Shift+ArrowDown')
    await page.keyboard.press('Control+End')
    expect(await focused(page)).toBe('p-edit')
  })

  test('opened with the mouse, the arrows still walk it from the trigger', async ({ page }) => {
    await open(page)

    await trig(page).click()
    await expect(page.getByTestId('plain').locator('[data-atom-menu]')).toBeVisible()
    await trig(page).focus()
    await page.keyboard.press('ArrowDown')
    expect(await focused(page)).toBe('p-edit')
    await page.keyboard.press('ArrowDown')
    expect(await focused(page)).toBe('p-link')
  })

  test('a menu with nothing reachable opens, takes no focus and breaks nothing', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))

    await open(page)

    const empty = page.getByTestId('empty').getByRole('button', { name: 'Empty' })
    await empty.focus()
    await page.keyboard.press('Enter')
    await expect(page.getByTestId('empty').locator('[data-atom-menu]')).toBeVisible()
    await expect(empty).toBeFocused()

    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('End')
    await expect(empty).toBeFocused()
    expect(errors).toEqual([])
  })
})

test.describe('a field inside a menu', () => {
  test('keeps its own arrow keys, Home and End, and the items still walk', async ({ page }) => {
    await open(page)

    const input = page.getByTestId('s-input')
    await page.getByTestId('search').getByRole('button', { name: 'With search' }).focus()
    await page.keyboard.press('Enter')
    await expect(page.getByTestId('search').locator('[data-atom-menu]')).toBeVisible()

    // the first item, not the field, takes the focus on open
    expect(await focused(page)).toBe('s-one')

    // a listener on the document sees each key after the dropdown has: none may arrive prevented, the field's own handling is the browser's. (The caret is
    // not read: what Home and End do in a text field differs between engines and platforms.)
    await page.evaluate(() => {
      window.__prevented = []
      document.addEventListener('keydown', (e) => window.__prevented.push([e.key, e.defaultPrevented]))
    })

    await input.focus()
    for (const key of ['ArrowDown', 'ArrowUp', 'Home', 'End']) await page.keyboard.press(key)

    expect(await page.evaluate(() => window.__prevented)).toEqual([['ArrowDown', false], ['ArrowUp', false], ['Home', false], ['End', false]])
    await expect(input).toBeFocused()

    // and from an item, the arrows walk the items only
    await page.getByTestId('s-two').focus()
    await page.keyboard.press('ArrowDown')
    expect(await focused(page)).toBe('s-one')
    await page.keyboard.press('ArrowUp')
    expect(await focused(page)).toBe('s-two')
  })
})

test.describe('a menu that is re-rendered while it is open', () => {
  const keyed = (page) => page.getByTestId('keyed')
  const render = async (page, method) => {
    const renders = page.getByTestId('renders')
    const before = Number(await renders.textContent())
    await page.evaluate((name) => window.Livewire.first().call(name), method)
    // reorder() does not count; wait for the response by the log of a round trip
    if (method === 'bump') await expect(renders).toHaveText(String(before + 1))
  }

  test('the focused item stays focused, and the arrows go on from it', async ({ page }) => {
    await open(page)

    await keyed(page).getByRole('button', { name: 'Keyed' }).focus()
    await page.keyboard.press('Enter')
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('k-b')).toBeFocused()

    await render(page, 'bump')

    await expect(keyed(page).locator('[data-atom-menu]')).toBeVisible()
    await expect(page.getByTestId('k-b')).toBeFocused()
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('k-c')).toBeFocused()
  })

  test('a morph that moves the focused node keeps the focus inside the menu', async ({ page }) => {
    await open(page)

    await keyed(page).getByRole('button', { name: 'Keyed' }).focus()
    await page.keyboard.press('Enter')
    await expect(page.getByTestId('k-a')).toBeFocused()

    // [a, b, c] becomes [c, b, a]: the focused node is the one that moves
    await render(page, 'reorder')
    await expect(keyed(page).locator('[data-atom-menu-item]').first()).toHaveAttribute('data-testid', 'k-c')

    // the menu is open and the focus is on one of its items, not lost to the page
    await expect(keyed(page).locator('[data-atom-menu]')).toBeVisible()
    expect(await page.evaluate(() => document.activeElement?.closest('[data-atom-menu]') !== null)).toBe(true)
    await expect(page.getByTestId('k-a')).toBeFocused()

    // Down from the last item wraps to the first
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('k-c')).toBeFocused()
  })

  test('a morph that replaces the focused node puts the focus on the item at its place', async ({ page }) => {
    await open(page)

    await keyed(page).getByRole('button', { name: 'Keyed' }).focus()
    await page.keyboard.press('Enter')
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('k-b')).toBeFocused()
    const before = await page.getByTestId('k-b').evaluate((el) => (window.__old = el, true))
    expect(before).toBe(true)

    await render(page, 'rekey')
    await expect.poll(() => page.evaluate(() => window.__old.isConnected)).toBe(false)

    await expect(keyed(page).locator('[data-atom-menu]')).toBeVisible()
    await expect(page.getByTestId('k-b')).toBeFocused()
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('k-c')).toBeFocused()
  })
})
