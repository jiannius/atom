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

// a closed select's trigger, and its list
const selectOf = (page, name) => ({
  trigger: page.getByTestId(name).locator('[data-atom-dropdown] > button, [data-atom-dropdown] > [data-atom-dropdown-trigger]').first(),
  list: page.getByTestId(name).locator('[data-atom-menu][popover]'),
  active: () => page.evaluate((id) => document.querySelector(`[data-testid=${id}] [data-active]`)?.textContent.trim() ?? null, name),
})
const isOpen = (loc) => loc.evaluate((el) => el.matches(':popover-open'))

// A select's list is the same menu component with role="listbox" and keys of its own (select.js): the menu's keys must leave it alone.
for (const [name, label] of [['listbox', 'a non-searchable listbox'], ['filter', 'a filter select']]) {
  test.describe(label, () => {
    test('Down and Up open it closed, and the arrows, Home, End and Enter walk and pick as before', async ({ page }) => {
      await open(page)
      const { trigger, list, active } = selectOf(page, name)

      await trigger.focus()
      await page.keyboard.press('ArrowDown')
      await expect.poll(() => isOpen(list)).toBe(true)
      // it stays open, and the option is the select's own highlight, not a menu item with the focus
      await page.waitForTimeout(250)
      expect(await isOpen(list)).toBe(true)
      await expect(trigger).toHaveAttribute('aria-expanded', 'true')
      expect(await page.evaluate(() => document.activeElement?.closest('[data-atom-menu-item]') ?? null)).toBe(null)

      await page.keyboard.press('ArrowDown')
      const second = await active()
      expect(second).not.toBe(null)
      await page.keyboard.press('End')
      expect(await active()).toBe('The Hague')
      await page.keyboard.press('Home')
      const first = await active()
      expect(first).toBe('New York')
      await page.keyboard.press('ArrowDown')
      expect(await active()).toBe('Newark')

      await page.keyboard.press('Enter')
      await expect.poll(() => isOpen(list)).toBe(false)
      await expect(trigger).toContainText('Newark')

      // closed again: Up opens it too
      await page.keyboard.press('ArrowUp')
      await expect.poll(() => isOpen(list)).toBe(true)
      await page.waitForTimeout(250)
      expect(await isOpen(list)).toBe(true)
    })
  })
}

test.describe('a trigger', () => {
  test('that is a text field keeps Down and Up, and a keyboard click on it leaves the focus in it', async ({ page }) => {
    await open(page)

    const input = page.getByTestId('f-input')
    const menu = page.getByTestId('field').locator('[data-atom-menu]')

    await input.focus()
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('ArrowUp')
    await expect(menu).toBeHidden()

    // a click with no pointer (detail 0) on the field opens the menu, and the person goes on typing
    await input.evaluate((el) => el.click())
    await expect(menu).toBeVisible()
    await expect(input).toBeFocused()
  })

  test('inside an editable region still opens on Down, on the first item', async ({ page }) => {
    await open(page)

    // Chromium hands a key pressed on a button inside an editing host to the host, so the key is sent to the button itself
    await page.getByTestId('editable').getByRole('button', { name: 'Editable' }).evaluate((el) => {
      el.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true }))
    })
    await expect(page.getByTestId('editable').locator('[data-atom-menu]')).toBeVisible()
    await expect(page.getByTestId('e-one')).toBeFocused()
  })

  test('of a list with another role than menu is left alone, even with menu items in it', async ({ page }) => {
    await open(page)

    const trigger = page.getByTestId('other-role').getByRole('button', { name: 'Other role' })
    const list = page.getByTestId('other-role').locator('[data-atom-menu]')

    await trigger.focus()
    await page.keyboard.press('ArrowDown')
    await page.waitForTimeout(200)
    await expect(list).toBeHidden()

    // opened with the keyboard it takes no focus, and the arrows in it are not the menu's
    await page.keyboard.press('Enter')
    await expect(list).toBeVisible()
    await expect(trigger).toBeFocused()
    await page.getByTestId('r-one').focus()
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('r-one')).toBeFocused()
  })

  test('of a panel with no items opens nothing on Down', async ({ page }) => {
    await open(page)

    await page.getByTestId('panel').getByRole('button', { name: 'Panel' }).focus()
    await page.keyboard.press('ArrowDown')
    await page.waitForTimeout(200)
    await expect(page.getByTestId('panel').locator('[data-atom-menu]')).toBeHidden()
  })

  test('opened with the mouse leaves the focus off the items', async ({ page }) => {
    await open(page)

    await page.getByTestId('act').getByRole('button', { name: 'Act' }).click()
    await expect(page.getByTestId('act').locator('[data-atom-menu]')).toBeVisible()
    await page.waitForTimeout(100)
    expect(await page.evaluate(() => document.activeElement?.closest('[data-atom-menu-item]') ?? null)).toBe(null)
  })
})

test('a menu with a key handler of its own keeps it: Down is not walked a second time', async ({ page }) => {
  await open(page)

  await page.getByTestId('owned').getByRole('button', { name: 'Owned' }).focus()
  await page.keyboard.press('Enter')
  await expect(page.getByTestId('o-first')).toBeFocused()

  await page.keyboard.press('ArrowDown')
  await expect(page.getByTestId('o-last')).toBeFocused()
})

test('a held Enter that opened the menu does not run the first item', async ({ page }) => {
  await open(page)

  const trigger = page.getByTestId('act').getByRole('button', { name: 'Act' })
  await trigger.focus()
  await page.keyboard.down('Enter')
  await page.keyboard.down('Enter')
  await page.keyboard.down('Enter')
  await page.keyboard.up('Enter')
  await page.waitForTimeout(250)

  await expect(page.getByTestId('n')).toHaveText('0')
  await expect(page.getByTestId('act').locator('[data-atom-menu]')).toBeVisible()
  await expect(page.getByTestId('a-count')).toBeFocused()

  // a press of its own after that is an Enter on the item
  await page.keyboard.press('Enter')
  await expect(page.getByTestId('n')).toHaveText('1')
})

test.describe('where the focus goes when a menu opened with the keyboard closes', () => {
  const act = (page) => page.getByTestId('act').getByRole('button', { name: 'Act' })
  const menu = (page) => page.getByTestId('act').locator('[data-atom-menu]')
  const openAct = async (page) => {
    await open(page)
    await act(page).focus()
    await page.keyboard.press('Enter')
    await expect(page.getByTestId('a-count')).toBeFocused()
  }

  test('an item that moves it to a field keeps it there', async ({ page }) => {
    await openAct(page)
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('a-focus')).toBeFocused()
    await page.keyboard.press('Enter')

    await expect(menu(page)).toBeHidden()
    await page.waitForTimeout(250)
    await expect(page.getByTestId('target-input')).toBeFocused()
  })

  test('an item that moves it to a field a moment later keeps it there', async ({ page }) => {
    await openAct(page)
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('a-later')).toBeFocused()
    await page.keyboard.press('Enter')

    await expect(menu(page)).toBeHidden()
    await expect(page.getByTestId('target-input')).toBeFocused()
    await page.waitForTimeout(250)
    await expect(page.getByTestId('target-input')).toBeFocused()
  })

  test('reopened after the close, the focus stays on the first item of the reopened menu', async ({ page }) => {
    await openAct(page)
    await page.evaluate(() => {
      // in the document's capture phase, so this task is queued before the dropdown's own
      document.addEventListener('toggle', (e) => {
        if (e.newState === 'closed') setTimeout(() => document.querySelector('[data-testid=act] [data-atom-dropdown] > button').click())
      }, { capture: true, once: true })
    })
    await page.keyboard.press('Escape')
    await page.waitForTimeout(100)
    await expect(menu(page)).toBeVisible()
    await page.waitForTimeout(250)
    await expect(page.getByTestId('a-count')).toBeFocused()
  })

  test('a menu whose focus was in a field is left to the browser', async ({ page }) => {
    await open(page)
    await page.getByTestId('search').getByRole('button', { name: 'With search' }).focus()
    await page.keyboard.press('Enter')
    await expect(page.getByTestId('s-one')).toBeFocused()
    await page.getByTestId('s-input').focus()
    await page.evaluate(() => {
      document.addEventListener('toggle', (e) => {
        if (e.newState === 'closed') setTimeout(() => document.activeElement.blur())
      }, { capture: true, once: true })
    })
    await page.keyboard.press('Escape')
    await expect(page.getByTestId('search').locator('[data-atom-menu]')).toBeHidden()
    await page.waitForTimeout(250)
    await expect(page.getByTestId('search').getByRole('button', { name: 'With search' })).not.toBeFocused()
  })

  test('an item that asks first leaves it in the dialog, and Cancel gives it back to the trigger', async ({ page }) => {
    await openAct(page)
    await page.keyboard.press('End')
    await page.keyboard.press('Enter')

    const dialog = page.locator('dialog[x-data*="atom-confirm"]')
    await expect(dialog).toHaveAttribute('data-open', '')
    await page.waitForTimeout(250)
    expect(await page.evaluate(() => document.activeElement?.closest('dialog') !== null)).toBe(true)

    await page.keyboard.press('Escape')
    await expect(dialog).not.toHaveAttribute('data-open', '')
    await expect(act(page)).toBeFocused()
  })

  test('a click on a field outside it leaves the focus in the field', async ({ page }) => {
    await openAct(page)
    await page.getByTestId('outside-input').click()
    await expect(menu(page)).toBeHidden()
    await page.waitForTimeout(250)
    await expect(page.getByTestId('outside-input')).toBeFocused()
  })

  test('a click on the bare page does not send it back to the trigger', async ({ page }) => {
    await openAct(page)
    await page.mouse.click(1000, 600)
    await expect(menu(page)).toBeHidden()
    await page.waitForTimeout(250)
    await expect(act(page)).not.toBeFocused()
  })

  test('a menu that never held it does not pull it to the trigger', async ({ page }) => {
    await open(page)
    await act(page).click()
    await expect(menu(page)).toBeVisible()
    await page.evaluate(() => document.activeElement.blur())
    await page.keyboard.press('Escape')
    await expect(menu(page)).toBeHidden()
    await page.waitForTimeout(250)
    await expect(act(page)).not.toBeFocused()
  })

  test('closed and reopened in the same task, it stays on the reopened menu\'s first item', async ({ page }) => {
    await openAct(page)
    await page.evaluate(() => {
      document.querySelector('[data-testid=act] [data-atom-menu]').hidePopover()
      document.querySelector('[data-testid=act] [data-atom-dropdown] > button').click()
    })
    await expect(menu(page)).toBeVisible()
    await page.waitForTimeout(300)
    await expect(page.getByTestId('a-count')).toBeFocused()
  })

  // WebKit leaves the focus on the item as the popover closes and then drops it: the page's own lookups stand in for what it reports then.
  for (const [name, stub] of [
    ['<body>', () => document.activeElement.blur()],
    ['the root element', () => { document.documentElement.tabIndex = -1; document.documentElement.focus() }],
    // the real focus is lost too (blur), so only the page's own answer decides
    ['nothing', () => { document.activeElement.blur(); Object.defineProperty(document, 'activeElement', { configurable: true, get: () => null }) }],
    ['an item of the closed menu', () => {
      const item = document.querySelector('[data-testid=a-count]')
      document.activeElement.blur()
      Object.defineProperty(document, 'activeElement', { configurable: true, get: () => item })
    }],
  ]) {
    test(`reported as ${name} after the close, it goes back to the trigger`, async ({ page }) => {
      await openAct(page)
      await page.evaluate((fn) => {
        // in the capture phase of the document, so this task is queued before the dropdown's own
        document.addEventListener('toggle', (e) => {
          if (e.newState !== 'closed') return
          // eslint-disable-next-line no-new-func
          setTimeout(new Function(`return (${fn})()`))
        }, { capture: true, once: true })
      }, stub.toString())

      await page.keyboard.press('Escape')
      await expect(menu(page)).toBeHidden()
      await page.waitForTimeout(250)
      await page.evaluate(() => { delete document.activeElement })
      expect(await page.evaluate(() => document.querySelector('[data-testid=act] [data-atom-dropdown] > button').matches(':focus'))).toBe(true)
    })
  }
})

test.describe('a morph while the menu is open', () => {
  const keyed = (page) => page.getByTestId('keyed')
  const bump = async (page) => {
    const before = Number(await page.getByTestId('renders').textContent())
    await page.evaluate(() => window.Livewire.first().call('bump'))
    await expect(page.getByTestId('renders')).toHaveText(String(before + 1))
    await page.waitForTimeout(150)
  }
  const openKeyed = async (page) => {
    await open(page)
    await keyed(page).getByRole('button', { name: 'Keyed' }).focus()
    await page.keyboard.press('Enter')
    await expect(page.getByTestId('k-a')).toBeFocused()
    await page.keyboard.press('ArrowDown')
    await expect(page.getByTestId('k-b')).toBeFocused()
  }

  test('does not pull the focus back when it is legitimately somewhere else', async ({ page }) => {
    await openKeyed(page)
    // out of the item with nowhere to go (no focusout target), then into a field the menu knows nothing about
    await page.evaluate(() => {
      document.activeElement.blur()
      document.querySelector('[data-testid=outside-input]').focus()
    })

    await bump(page)
    await expect(page.getByTestId('outside-input')).toBeFocused()
  })

  test('does not pull it back after it left the menu for a field and then the page', async ({ page }) => {
    await openKeyed(page)
    await page.evaluate(() => {
      document.querySelector('[data-testid=outside-input]').focus()
      document.activeElement.blur()
    })

    await bump(page)
    expect(await page.evaluate(() => document.activeElement === document.body)).toBe(true)
  })

  test('does not pull it back after a press on the padding of a menu that stays open', async ({ page }) => {
    await open(page)
    await page.getByTestId('locked').getByRole('button', { name: 'Locked' }).focus()
    await page.keyboard.press('Enter')
    await expect(page.getByTestId('l-one')).toBeFocused()

    const menu = page.getByTestId('locked').locator('[data-atom-menu]')
    const box = await menu.boundingBox()
    // the middle of the top padding: a point 2px in from the corner is outside the panel's rounded corner, and Firefox hit-tests there
    await page.mouse.click(box.x + box.width / 2, box.y + 2)
    await page.waitForTimeout(100)
    expect(await menu.evaluate((el) => el.matches(':popover-open'))).toBe(true)

    await bump(page)
    expect(await page.evaluate(() => document.activeElement?.closest('[data-atom-menu-item]') ?? null)).toBe(null)
  })
})
