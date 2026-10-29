import { test, expect } from '@playwright/test'

// The column picker (table-columns P4): the toolbar's `columns` prop draws an icon-only "Columns" button with a corner badge and a menu
// of the table's named columns. On a Livewire table (tests/Fixtures/TableColumnsFixture.php, ?picker=1: the toolbar in the table's
// header slot; ?outside=1: the toolbar above the table, steering it by id) and on static tables
// (resources/views/e2e/table-column-picker.blade.php). Columns of the fixture: name (locked), email, phone (hidden by default), city, plan.

const KEY = 'atom:table-columns:people'
const root = '[data-atom-column-picker]'
const trigger = (page, n = 0) => page.locator(`${root} [data-atom-column-picker-trigger]`).nth(n)
const menu = (page, n = 0) => page.locator(`${root} [data-atom-column-menu]`).nth(n)
const items = (page, n = 0) => menu(page, n).locator('[data-atom-menu-item]')
const item = (page, label, n = 0) => menu(page, n).locator('[data-atom-column-name]').filter({ hasText: label })
const badge = (page, n = 0) => page.locator(`${root} [data-atom-column-picker-badge]`).nth(n)
const reset = (page, n = 0) => menu(page, n).locator('[data-atom-column-reset]')
const tipOf = (page, n = 0) => page.locator(`${root} [data-atom-tooltip-content]`).nth(n)

const hidden = (page, name, scope = '[data-atom-table]') => page.evaluate(([name, scope]) => {
  const all = [...document.querySelectorAll(`${scope} [data-atom-column="${name}"]`)]
  return all.length > 0 && all.every((el) => getComputedStyle(el).display === 'none')
}, [name, scope])
const shown = (page, name, scope = '[data-atom-table]') => page.evaluate(([name, scope]) => {
  const all = [...document.querySelectorAll(`${scope} [data-atom-column="${name}"]`)]
  return all.length > 0 && all.every((el) => getComputedStyle(el).display !== 'none')
}, [name, scope])
const stored = (page, key = KEY) => page.evaluate((key) => window.localStorage.getItem(key), key)
const hideList = (page, scope = '[data-atom-table]') => page.locator(`${scope} [data-atom-column-scope]`).first().getAttribute('data-atom-column-hide')

// Run an action that makes a Livewire round trip, then wait for the response and for `applied`, a concrete condition the caller
// polls (the response is applied a moment after it arrives, and on a slow runner that moment is long). No timer.
const roundTrip = async (page, action, applied) => {
  const response = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST')
  await action()
  await response
  await applied()
}

// the table's count text ("... of N"), which a search or a new table rewrites
const countText = (page) => page.locator('[data-atom-table-count-source]').getAttribute('data-text')

// two animation frames: what a handler queued by the last event has had its turn (a negative check needs a moment, not a timer)
const frames = (page) => page.evaluate(() => new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done))))

async function open(page, { path = 'table-columns', tw = 'latest', query = '', seed = null, key = KEY } = {}) {
  await page.goto(`/atom/e2e/${path}?tw=${tw}${query}`)
  await page.evaluate(([key, seed]) => {
    window.localStorage.clear()
    if (seed) window.localStorage.setItem(key, JSON.stringify(seed))
  }, [key, seed])
  await page.goto(`/atom/e2e/${path}?tw=${tw}${query}`)
  await page.waitForLoadState('networkidle')
  await expect(trigger(page)).toBeVisible()
}

const openMenu = async (page, n = 0) => {
  await trigger(page, n).click()
  await expect(menu(page, n)).toBeVisible()
}

test.use({ viewport: { width: 1280, height: 800 } })

for (const tw of ['floor', 'latest']) {
  test.describe(`the picker on a Livewire table, ${tw} consumer build`, () => {
    test('lists every named column in order as a menu of checkboxes, the locked one greyed with a padlock', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await openMenu(page)

      await expect(menu(page)).toHaveAttribute('role', 'menu')
      await expect(menu(page)).toHaveAccessibleName('Show columns')
      await expect(menu(page).locator('[data-atom-column-name]')).toHaveText(['Name', 'Email', 'Phone', 'City', 'Plan'])
      await expect(menu(page).locator('[data-atom-column-name]')).toHaveCount(5)

      for (const [name, checked] of [['name', 'true'], ['email', 'true'], ['phone', 'false'], ['city', 'true'], ['plan', 'true']]) {
        const row = menu(page).locator(`[data-atom-column-name="${name}"]`)
        await expect(row).toHaveAttribute('role', 'menuitemcheckbox')
        await expect(row).toHaveAttribute('aria-checked', checked)
      }

      const locked = menu(page).locator('[data-atom-column-name="name"]')
      await expect(locked).toHaveAttribute('aria-disabled', 'true')
      await expect(locked.getByRole('img', { name: 'Always shown' })).toBeVisible()
      await expect(menu(page).locator('[data-atom-column-name="email"]')).not.toHaveAttribute('aria-disabled', 'true')
      await expect(menu(page).locator('[data-atom-column-name="email"] [role="img"]')).toHaveCount(0)

      // the checkbox column is never listed
      await expect(menu(page)).not.toContainText('checkbox')
    })

    test('a click toggles the column, keeps the menu open and moves the badge and its description', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await openMenu(page)

      // phone is hidden by default: the badge says 1
      await expect(badge(page)).toHaveText('1')

      await item(page, 'Email').click()
      expect(await hidden(page, 'email')).toBe(true)
      await expect(item(page, 'Email')).toHaveAttribute('aria-checked', 'false')
      await expect(menu(page)).toBeVisible()
      await expect(badge(page)).toHaveText('2')

      await item(page, 'Phone').click()
      expect(await shown(page, 'phone')).toBe(true)
      await expect(item(page, 'Phone')).toHaveAttribute('aria-checked', 'true')
      await expect(badge(page)).toHaveText('1')
      expect(JSON.parse(await stored(page))).toEqual({ v: 1, columns: { email: false, phone: true } })
    })

    test('the badge is aria-hidden and announced as the button\'s description; the button\'s name stays "Columns"', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })

      await expect(trigger(page)).toHaveAccessibleName('Columns')
      await expect(trigger(page)).toHaveAccessibleDescription('1 hidden')
      await expect(badge(page)).toHaveAttribute('aria-hidden', 'true')

      await openMenu(page)
      await item(page, 'Email').click()
      await expect(trigger(page)).toHaveAccessibleDescription('2 hidden')
      await expect(trigger(page)).toHaveAccessibleName('Columns')

      await item(page, 'Phone').click()
      await item(page, 'Email').click()
      await expect(badge(page)).toBeHidden()
      await expect(trigger(page)).toHaveAccessibleDescription('')
      await expect(trigger(page)).not.toHaveAttribute('aria-describedby')
    })

    test('the choices persist across a reload and Reset restores the defaults and clears the storage', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await openMenu(page)
      await item(page, 'Email').click()
      await item(page, 'Phone').click()
      expect(await stored(page)).not.toBeNull()

      await page.reload()
      await page.waitForLoadState('networkidle')
      expect(await hidden(page, 'email')).toBe(true)
      expect(await shown(page, 'phone')).toBe(true)
      await expect(badge(page)).toHaveText('1')

      await openMenu(page)
      await expect(reset(page)).not.toHaveAttribute('aria-disabled', 'true')
      await reset(page).click()

      expect(await shown(page, 'email')).toBe(true)
      expect(await hidden(page, 'phone')).toBe(true)
      expect(await stored(page)).toBeNull()
      await expect(item(page, 'Email')).toHaveAttribute('aria-checked', 'true')
      await expect(item(page, 'Phone')).toHaveAttribute('aria-checked', 'false')
      await expect(badge(page)).toHaveText('1')
      // nothing differs now
      await expect(reset(page)).toHaveAttribute('aria-disabled', 'true')
    })

    test('Reset to default is disabled and does nothing while the table is at its defaults', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await openMenu(page)

      await expect(reset(page)).toHaveAttribute('aria-disabled', 'true')
      await reset(page).click({ force: true })
      expect(await stored(page)).toBeNull()
      await expect(menu(page)).toBeVisible()
    })

    test('with a locked column shown, every hideable column can be hidden', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await openMenu(page)

      // phone is hidden by default; email, city and plan go one after the other, the last of them too
      await item(page, 'Email').click()
      await item(page, 'City').click()
      await expect(item(page, 'Plan')).not.toHaveAttribute('aria-disabled', 'true')
      await item(page, 'Plan').click()

      await expect(item(page, 'Plan')).toHaveAttribute('aria-checked', 'false')
      expect(await hidden(page, 'plan')).toBe(true)
      expect(JSON.parse(await stored(page))).toEqual({ v: 1, columns: { email: false, city: false, plan: false } })
      expect(await shown(page, 'name')).toBe(true)
      await expect(badge(page)).toHaveText('4')
    })

    test('the last shown column of a table of only hideable columns cannot be hidden', async ({ page }) => {
      await open(page, { path: 'table-column-picker', tw, key: 'atom:table-columns:picker-f' })
      await openMenu(page, 4)

      await expect(menu(page, 4).locator('[data-atom-column-name]')).toHaveText(['Email', 'City'])
      await item(page, 'Email', 4).click()

      // city is the only one left: its row is disabled and a click changes nothing
      await expect(item(page, 'City', 4)).toHaveAttribute('aria-disabled', 'true')
      await expect(item(page, 'City', 4)).toHaveAttribute('aria-checked', 'true')
      await item(page, 'City', 4).click({ force: true })
      await expect(item(page, 'City', 4)).toHaveAttribute('aria-checked', 'true')
      expect(await shown(page, 'city', '[data-testid="f"]')).toBe(true)
      expect(JSON.parse(await stored(page, 'atom:table-columns:picker-f'))).toEqual({ v: 1, columns: { email: false } })

      // showing another one frees it again
      await item(page, 'Email', 4).click()
      await expect(item(page, 'City', 4)).not.toHaveAttribute('aria-disabled', 'true')
    })

    test('a locked item does nothing on click but takes focus', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await openMenu(page)

      const locked = menu(page).locator('[data-atom-column-name="name"]')
      await locked.click({ force: true })
      await expect(locked).toHaveAttribute('aria-checked', 'true')
      expect(await shown(page, 'name')).toBe(true)
      expect(await stored(page)).toBeNull()

      await locked.focus()
      await expect(locked).toBeFocused()
    })

    test('the keyboard: Enter opens, focus lands on the first item, arrows walk, Space toggles, Escape returns focus without a tooltip', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })

      await trigger(page).focus()
      await expect(tipOf(page)).toBeVisible()
      await page.keyboard.press('Enter')
      await expect(menu(page)).toBeVisible()
      await expect(items(page).first()).toBeFocused()
      // the tooltip is gone as soon as the menu is open, and focus moving into the menu does not bring it back
      await expect(tipOf(page)).toBeHidden()

      // the locked first item is focusable, so the arrows never stick on it
      await page.keyboard.press('ArrowDown')
      await expect(menu(page).locator('[data-atom-column-name="email"]')).toBeFocused()
      await page.keyboard.press('ArrowDown')
      await expect(menu(page).locator('[data-atom-column-name="phone"]')).toBeFocused()
      await page.keyboard.press('End')
      await expect(reset(page)).toBeFocused()
      await page.keyboard.press('ArrowDown')
      await expect(items(page).first()).toBeFocused()
      await page.keyboard.press('ArrowUp')
      await expect(reset(page)).toBeFocused()
      await page.keyboard.press('Home')
      await expect(items(page).first()).toBeFocused()

      await page.keyboard.press('ArrowDown')
      await page.keyboard.press(' ')
      expect(await hidden(page, 'email')).toBe(true)
      await expect(menu(page).locator('[data-atom-column-name="email"]')).toHaveAttribute('aria-checked', 'false')
      await expect(menu(page)).toBeVisible()
      await page.keyboard.press('Enter')
      expect(await shown(page, 'email')).toBe(true)

      await page.keyboard.press('Escape')
      await expect(menu(page)).toBeHidden()
      await expect(trigger(page)).toBeFocused()
      await expect(trigger(page)).toHaveAttribute('aria-expanded', 'false')
      // Escape gives the focus back; the tooltip does not pop up over a menu that is just closing
      await frames(page)
      await expect(tipOf(page)).toBeHidden()
    })

    test('the tooltip "Columns" shows on hover and is suppressed while the menu is open', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })

      await trigger(page).hover()
      await expect(tipOf(page)).toBeVisible()
      await expect(tipOf(page)).toHaveText('Columns')

      await trigger(page).click()
      await expect(menu(page)).toBeVisible()
      await expect(tipOf(page)).toBeHidden()

      await page.mouse.move(5, 5)
      await trigger(page).hover()
      await frames(page)
      await expect(tipOf(page)).toBeHidden()
    })

    test('the trigger is an icon-only ghost button: no label, a name, a menu popup', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })

      await expect(trigger(page).locator('[data-atom-button-label]')).toHaveCount(0)
      await expect(trigger(page)).toHaveAttribute('aria-haspopup', 'menu')
      await expect(trigger(page)).toHaveAttribute('aria-expanded', 'false')
      const box = await trigger(page).boundingBox()
      expect(Math.abs(box.width - box.height)).toBeLessThan(1)
    })

    test('survives a morph: after a search round trip the badge, the checks and the cells are still right', async ({ page }) => {
      await open(page, { tw, query: '&picker=1', seed: { v: 1, columns: { email: false, phone: true } } })
      await expect(badge(page)).toHaveText('1')

      await roundTrip(page, () => page.getByTestId('search').fill('Person 1'), () => expect.poll(() => countText(page)).toMatch(/\b11\b/))
      expect(await hidden(page, 'email')).toBe(true)
      expect(await shown(page, 'phone')).toBe(true)
      await expect(badge(page)).toHaveText('1')
      await expect(trigger(page)).toHaveAccessibleName('Columns')
      await expect(trigger(page)).toHaveAccessibleDescription('1 hidden')

      await openMenu(page)
      await expect(item(page, 'Email')).toHaveAttribute('aria-checked', 'false')
      await expect(item(page, 'Phone')).toHaveAttribute('aria-checked', 'true')

      // and a morph while the menu is open leaves it open
      await roundTrip(page, () => page.getByTestId('search').fill('Person 3'), () => expect.poll(() => countText(page)).toMatch(/\b2\b/))
      await expect(menu(page)).toBeVisible()
      await expect(item(page, 'Email')).toHaveAttribute('aria-checked', 'false')

      // a toggle after a morph still works
      await item(page, 'Email').click()
      expect(await shown(page, 'email')).toBe(true)
      await expect(badge(page)).toBeHidden()
    })

    test('a wire:key re-adopt (a new table with the server\'s state) shows the new state in the picker', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await expect(badge(page)).toHaveText('1')

      await roundTrip(page, () => page.locator('[data-reset-layout]').click(), () => expect.poll(() => shown(page, 'phone')).toBe(true))

      // resetLayout() pushes { phone: true } and a new wire:key: the new table adopts it, the picker is drawn again from it
      await expect.poll(() => shown(page, 'phone')).toBe(true)
      await expect(badge(page)).toBeHidden()
      await expect(trigger(page)).toHaveAccessibleDescription('')

      await openMenu(page)
      await expect(item(page, 'Phone')).toHaveAttribute('aria-checked', 'true')
      await expect(item(page, 'Email')).toHaveAttribute('aria-checked', 'true')
    })

    test('a server-added column appears in the menu after a round trip, without a reload', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await openMenu(page)
      await expect(menu(page).locator('[data-atom-column-name]')).toHaveText(['Name', 'Email', 'Phone', 'City', 'Plan'])

      // (the click outside closes the menu; the list behind it is what changes)
      await roundTrip(page, () => page.locator('[data-add-column]').click(), () => expect(page.locator('[data-atom-table] th[data-atom-column="region"]')).toHaveCount(1))
      await openMenu(page)
      await expect(menu(page).locator('[data-atom-column-name]')).toHaveText(['Name', 'Email', 'Phone', 'City', 'Plan', 'Region'])
      await item(page, 'Region').click()
      expect(await hidden(page, 'region')).toBe(true)
      await expect(badge(page)).toHaveText('2')
    })

    test('the picker leaves with the header while rows are ticked and comes back', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await expect(trigger(page)).toBeVisible()

      await page.locator('[data-atom-table-row] [data-atom-table-checkbox]').first().click()
      await expect(trigger(page)).toBeHidden()

      await page.locator('[data-atom-table-row] [data-atom-table-checkbox]').first().click()
      await expect(trigger(page)).toBeVisible()
      await openMenu(page)
      await expect(items(page).first()).toBeFocused()
    })

    test('with sticky-selection the picker stays while rows are ticked', async ({ page }) => {
      await open(page, { tw, query: '&picker=1&sticky=1' })

      await page.locator('[data-atom-table-row] [data-atom-table-checkbox]').first().click()
      await expect.poll(() => page.locator('[data-atom-table-row] [data-atom-table-checkbox][data-checked]').count()).toBe(1)
      await expect(trigger(page)).toBeVisible()

      await openMenu(page)
      await item(page, 'Email').click()
      expect(await hidden(page, 'email')).toBe(true)
    })

    test('a toolbar outside the table steers it by `for`, across a re-adopt too', async ({ page }) => {
      await open(page, { tw, query: '&outside=1' })

      expect(await page.locator('[data-atom-table] [data-atom-column-picker]').count()).toBe(0)
      await expect(page.getByTestId('outside-toolbar').locator(root)).toHaveAttribute('data-atom-column-picker-for', 'people-table')

      await openMenu(page)
      await expect(menu(page).locator('[data-atom-column-name]')).toHaveText(['Name', 'Email', 'Phone', 'City', 'Plan'])
      await item(page, 'City').click()
      expect(await hidden(page, 'city')).toBe(true)
      await expect(badge(page)).toHaveText('2')
      await page.keyboard.press('Escape')

      await roundTrip(page, () => page.locator('[data-reset-layout]').click(), () => expect.poll(() => shown(page, 'city')).toBe(true))
      await expect(badge(page)).toBeHidden()
      await openMenu(page)
      await expect(item(page, 'City')).toHaveAttribute('aria-checked', 'true')
      await item(page, 'Email').click()
      expect(await hidden(page, 'email')).toBe(true)
      await expect(badge(page)).toHaveText('1')
    })

    test('a label that looks like markup is shown as text, never parsed', async ({ page }) => {
      await open(page, { tw, query: '&picker=1&label=1' })
      await openMenu(page)

      const city = menu(page).locator('[data-atom-column-name="city"]')
      await expect(city).toContainText('City <img src=x onerror="window.__picked=1">')
      await expect(menu(page).locator('img')).toHaveCount(0)
      expect(await page.evaluate(() => window.__picked)).toBeUndefined()
    })

    test('the event the engine fires reaches the page when a column is toggled from the menu', async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })
      await openMenu(page)
      await item(page, 'City').click()

      await expect(page.getByTestId('saved')).toHaveText('[{"city":false}]')
    })
  })

  test.describe(`the picker on static tables, ${tw} consumer build`, () => {
    test('the divider is drawn only when something visible precedes the picker', async ({ page }) => {
      await open(page, { path: 'table-column-picker', tw, key: 'atom:table-columns:picker-a' })

      // A: an Export button before the picker. B and E: nothing before it (the count is off)
      await expect(page.getByTestId('a-panel').locator('[data-atom-table-toolbar-divider]')).toBeVisible()
      await expect(page.getByTestId('b-panel').locator('[data-atom-table-toolbar-divider]')).toHaveCount(0)
      await expect(page.getByTestId('e-panel').locator('[data-atom-table-toolbar-divider]')).toHaveCount(0)

      // and the picker is last in the end group, the divider just before it
      const order = await page.getByTestId('a-panel').locator('[data-atom-table-toolbar-end] > *').evaluateAll((els) => els.map((el) => el.hasAttribute('data-atom-table-toolbar-divider') ? 'divider' : el.hasAttribute('data-atom-column-picker') ? 'picker' : el.tagName.toLowerCase()))
      expect(order.slice(-2)).toEqual(['divider', 'picker'])
      expect(order.indexOf('picker')).toBe(order.length - 1)
    })

    test('a picker outside a table lists its table\'s columns, toggles them and keeps a label that holds markup as text', async ({ page }) => {
      await open(page, { path: 'table-column-picker', tw, key: 'atom:table-columns:picker-a' })
      await openMenu(page, 0)

      await expect(menu(page, 0).locator('[data-atom-column-name]')).toHaveCount(4)
      await expect(menu(page, 0).locator('[data-atom-column-name="notes"]')).toContainText('Notes <b>bold</b> & <img src=x onerror="window.__picked=1">')
      await expect(menu(page, 0).locator('img, b')).toHaveCount(0)
      expect(await page.evaluate(() => window.__picked)).toBeUndefined()

      await item(page, 'Email', 0).click()
      expect(await hidden(page, 'email', '[data-testid="a"]')).toBe(true)
      expect(await shown(page, 'email', '[data-testid="b"]')).toBe(true)
      await expect(badge(page, 0)).toHaveText('2')
    })

    test('a picker only touches the table it is for', async ({ page }) => {
      await open(page, { path: 'table-column-picker', tw, key: 'atom:table-columns:picker-a' })
      await openMenu(page, 1)

      // B's picker hides B's email (its locked name stays) and nothing of A's
      await expect(menu(page, 1).locator('[data-atom-column-name]')).toHaveText(['Name', 'Email'])
      await item(page, 'Email', 1).click()
      expect(await hidden(page, 'email', '[data-testid="b"]')).toBe(true)
      expect(await shown(page, 'email', '[data-testid="a"]')).toBe(true)
      expect(await shown(page, 'name', '[data-testid="b"]')).toBe(true)
      await expect(badge(page, 1)).toHaveText('1')
      await expect(badge(page, 0)).toHaveText('1') // A's own default-hidden phone, unchanged
    })

    test('two tables on one persist-key stay in step, and the picker follows a change made through the API', async ({ page }) => {
      await open(page, { path: 'table-column-picker', tw, key: 'atom:table-columns:picker-shared' })

      // A, B, C and E have a picker, in that order
      await openMenu(page, 2)
      await item(page, 'City', 2).click()

      expect(await hidden(page, 'city', '[data-testid="c"]')).toBe(true)
      expect(await hidden(page, 'city', '[data-testid="d"]')).toBe(true)
      expect(await shown(page, 'email', '[data-testid="d"]')).toBe(true)
      await expect(badge(page, 2)).toHaveText('1')

      // another control changes it through the API: the picker catches up on the announced change
      await page.evaluate(() => window.atom.tableColumns.hide(document.querySelector('[data-testid="d"]'), 'email'))
      await expect(item(page, 'Email', 2)).toHaveAttribute('aria-checked', 'false')
      await expect(badge(page, 2)).toHaveText('2')
      // a hidden row is never the disabled one
      await expect(item(page, 'Email', 2)).not.toHaveAttribute('aria-disabled', 'true')

      // a table with another key is not touched
      expect(await shown(page, 'email', '[data-testid="a"]')).toBe(true)
    })

    test('a picker whose `for` names no table warns once, naming the id, and lists no columns', async ({ page }) => {
      const warnings = []
      page.on('console', (message) => { if (message.type() === 'warning') warnings.push(message.text()) })
      // the helper loads the page twice (it seeds storage first): count what the last load said
      page.on('framenavigated', () => { warnings.length = 0 })

      await open(page, { path: 'table-column-picker', tw, key: 'atom:table-columns:picker-a' })
      await openMenu(page, 5)
      await expect(menu(page, 5).locator('[data-atom-column-name]')).toHaveCount(0)
      await page.keyboard.press('Escape')
      await openMenu(page, 0)
      // every picker reads its table again when a change is announced: the dead one must not say it twice
      await page.evaluate(() => { for (let i = 0; i < 3; i++) window.dispatchEvent(new CustomEvent('atom-table-columns-changed')) })

      const mine = warnings.filter((text) => text.includes('picker-missing'))
      expect(mine).toHaveLength(1)
      expect(mine[0]).toContain('no <atom:table> has that id')
      // the pickers that find their table say nothing
      expect(warnings.filter((text) => text.includes('[atom]'))).toHaveLength(1)
    })
  })
}

test.describe('at 390px', () => {
  test.use({ viewport: { width: 390, height: 800 } })

  for (const tw of ['floor', 'latest']) {
    test(`the toolbar keeps the search and the icon-only picker on one row, and the menu fits the screen, ${tw} consumer build`, async ({ page }) => {
      await open(page, { tw, query: '&picker=1' })

      const search = page.locator('[data-atom-table-toolbar] [data-atom-table-search]').first()
      const box = await trigger(page).boundingBox()
      const searchBox = await search.boundingBox()

      // the picker shares the search's row (its icon-only end group is narrow) and nothing scrolls sideways
      expect(Math.abs((box.y + box.height / 2) - (searchBox.y + searchBox.height / 2))).toBeLessThan(searchBox.height)
      expect(searchBox.width).toBeGreaterThanOrEqual(190)
      // (the fixture's own row of test buttons is wider than the screen: look at the toolbar, not the page)
      expect(box.x + box.width).toBeLessThanOrEqual(390)
      expect(await page.locator('[data-atom-table-toolbar]').evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true)

      await openMenu(page)
      const panel = await menu(page).boundingBox()
      expect(panel.x).toBeGreaterThanOrEqual(0)
      expect(panel.x + panel.width).toBeLessThanOrEqual(390)

      // the host's `max-sm:hidden` city column is drawn hidden at this width whatever the picker says (README: next to your own classes)
      await item(page, 'Email').click()
      expect(await hidden(page, 'email')).toBe(true)
    })
  }

  test('with only the count before it, the divider is not drawn on a phone', async ({ page }) => {
    await page.goto('/atom/e2e/table-columns?picker=1')
    await page.waitForLoadState('networkidle')

    await expect(page.locator('[data-atom-table-toolbar-divider]')).toBeHidden()
    await expect(trigger(page)).toBeVisible()
  })
})

test('with only the count before it, the divider follows the count: drawn once the count has text', async ({ page }) => {
  await page.goto('/atom/e2e/table-columns?picker=1')
  await page.waitForLoadState('networkidle')

  await expect(page.locator('[data-atom-table-count]')).toHaveText(/1.*5.*30/)
  await expect(page.locator('[data-atom-table-toolbar-divider]')).toBeVisible()
})
