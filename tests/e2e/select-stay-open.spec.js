import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/select-stay-open (tests/Fixtures/SelectStayOpenFixture.php), #280: a `multiple`
// select stays open while values are ticked and unticked, by a click or by Enter, and closes on Escape, a click outside or a
// click on its trigger. A single select still closes on a pick. Every select here is on `wire:model.live`, so each tick is a
// Livewire update that morphs the component while the list is open; the Client filter's update also adds or removes a Branch
// field above it (shown while exactly one client is ticked), which moves its trigger.

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const trigger = (page, name) => probe(page, name).locator('[data-atom-dropdown] > button').first()
const menu = (page, name) => probe(page, name).locator('[data-atom-menu][popover]')
const option = (page, name, label) => menu(page, name).locator('[data-atom-option]', { hasText: label })
const search = (page, name) => menu(page, name).locator('[data-atom-select-search]')

const isOpen = (page, name) => menu(page, name).evaluate(el => el.matches(':popover-open'))

// Open, and settled: the popover's `toggle` event is a task of its own after `:popover-open`, and the select picks its
// active option a tick after that, so a key pressed before then is overwritten by that pick.
async function settled (page, name) {
  await expect.poll(() => isOpen(page, name)).toBe(true)
  await expect.poll(() => probe(page, name).locator('[x-modelable="selectValue"]').evaluate(el => Alpine.$data(el).open)).toBe(true)
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve))))
}

// what the component holds, read from the server's own render of it
const server = async (page) => JSON.parse(await page.locator('[data-server]').textContent())

const renders = async (page) => Number(await page.locator('[data-renders]').textContent())

// The open list is anchored to its trigger: its left edge on the trigger's, and its top 2px under the trigger (or its bottom
// 2px over it, if it flipped).
const anchored = (page, name) => page.evaluate((name) => {
  let root = document.querySelector(`[data-probe="${name}"]`)
  let t = root.querySelector('[data-atom-dropdown] > button').getBoundingClientRect()
  let m = root.querySelector('[data-atom-menu][popover]').getBoundingClientRect()

  return Math.abs(m.left - t.left) < 1.5
    && (Math.abs(m.top - (t.bottom + 2)) < 1.5 || Math.abs(m.bottom - (t.top - 2)) < 1.5)
}, name)

// a tick is a request: wait for the render it causes, then for the value it carried
async function tick (page, picked, expected, key) {
  let before = await renders(page)

  await picked()
  await expect.poll(() => renders(page)).toBeGreaterThan(before)
  await expect.poll(async () => (await server(page))[key]).toEqual(expected)
}

test.beforeEach(async ({ page }) => {
  await page.goto('/atom/e2e/select-stay-open')
  await expect(page.locator('[data-renders]')).toHaveText('1')
})

test('a multiple filter stays open while three clients are ticked, by click and by Enter, and the list stays on its trigger', async ({ page }) => {
  await trigger(page, 'clients').click()
  await settled(page, 'clients')

  // the first tick shows the Branch field above the filter, which moves the trigger down
  let top = (await trigger(page, 'clients').boundingBox()).y

  await tick(page, () => option(page, 'clients', 'Alpha Trading').click(), ['alpha-trading'], 'clients')
  await expect(page.locator('[data-branch]')).toBeVisible()
  expect((await trigger(page, 'clients').boundingBox()).y).toBeGreaterThan(top + 10)
  expect(await isOpen(page, 'clients')).toBe(true)
  await expect.poll(() => anchored(page, 'clients')).toBe(true)
  await expect(option(page, 'clients', 'Alpha Trading')).toHaveAttribute('aria-selected', 'true')

  // the second takes it away again
  await tick(page, () => option(page, 'clients', 'Beta Works').click(), ['alpha-trading', 'beta-works'], 'clients')
  await expect(page.locator('[data-branch]')).toHaveCount(0)
  expect(await isOpen(page, 'clients')).toBe(true)
  await expect.poll(() => anchored(page, 'clients')).toBe(true)
  await expect(option(page, 'clients', 'Beta Works')).toHaveAttribute('aria-selected', 'true')

  // the third by the keyboard, from a search
  await expect(search(page, 'clients')).toBeFocused()
  await search(page, 'clients').pressSequentially('char')
  await expect(menu(page, 'clients').locator('[data-atom-option]')).toHaveCount(1)
  // the search's fetch picks the active option a tick after the list is in
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve))))
  await page.keyboard.press('ArrowDown')
  await expect(option(page, 'clients', 'Charlie Supplies')).toHaveAttribute('data-active', '')
  await tick(page, () => page.keyboard.press('Enter'), ['alpha-trading', 'beta-works', 'charlie-supplies'], 'clients')

  expect(await isOpen(page, 'clients')).toBe(true)
  await expect.poll(() => anchored(page, 'clients')).toBe(true)
  await expect(option(page, 'clients', 'Charlie Supplies')).toHaveAttribute('aria-selected', 'true')

  // the active option and the focus outlive the update
  await expect(option(page, 'clients', 'Charlie Supplies')).toHaveAttribute('data-active', '')
  await expect(search(page, 'clients')).toBeFocused()
  let activeId = await option(page, 'clients', 'Charlie Supplies').getAttribute('id')
  await expect(search(page, 'clients')).toHaveAttribute('aria-activedescendant', activeId)

  // the trigger still names what is ticked: Alpha and Beta are no longer in the searched list
  await expect(trigger(page, 'clients')).toContainText('3 selected')

  await page.keyboard.press('Escape')
  await expect.poll(() => isOpen(page, 'clients')).toBe(false)
  expect((await server(page)).clients).toEqual(['alpha-trading', 'beta-works', 'charlie-supplies'])
})

test('the search is kept and selected after a pick, so the next search types over it', async ({ page }) => {
  await trigger(page, 'clients').click()
  await expect(search(page, 'clients')).toBeFocused()

  await search(page, 'clients').pressSequentially('al')
  await expect(menu(page, 'clients').locator('[data-atom-option]')).toHaveCount(3)

  await tick(page, () => option(page, 'clients', 'Alpine Foods').click(), ['alpine-foods'], 'clients')

  // kept, selected, focused, and the list is still the one the search found
  await expect(search(page, 'clients')).toHaveValue('al')
  await expect(search(page, 'clients')).toBeFocused()
  expect(await search(page, 'clients').evaluate(el => [el.selectionStart, el.selectionEnd])).toEqual([0, 2])
  await expect(menu(page, 'clients').locator('[data-atom-option]')).toHaveCount(3)

  // a deselect keeps it too
  await tick(page, () => option(page, 'clients', 'Alpine Foods').click(), [], 'clients')
  expect(await isOpen(page, 'clients')).toBe(true)
  await expect(search(page, 'clients')).toHaveValue('al')
  await expect(option(page, 'clients', 'Alpine Foods')).toHaveAttribute('aria-selected', 'false')

  // typing replaces it
  await page.keyboard.type('be')
  await expect(search(page, 'clients')).toHaveValue('be')
  await expect(menu(page, 'clients').locator('[data-atom-option]')).toHaveCount(1)
  await expect(option(page, 'clients', 'Beta Works')).toBeVisible()
})

test('a multiple listbox stays open while three tags are ticked, and a deselect keeps it open', async ({ page }) => {
  await trigger(page, 'tags').click()
  await settled(page, 'tags')

  await search(page, 'tags').pressSequentially('r')
  await expect(menu(page, 'tags').locator('[data-atom-option]')).toHaveCount(4)

  await tick(page, () => option(page, 'tags', 'Red').click(), ['red'], 'tags')
  expect(await isOpen(page, 'tags')).toBe(true)
  await expect(search(page, 'tags')).toHaveValue('r')
  await expect(search(page, 'tags')).toBeFocused()

  await tick(page, () => option(page, 'tags', 'Rose').click(), ['red', 'rose'], 'tags')
  expect(await isOpen(page, 'tags')).toBe(true)

  await tick(page, () => option(page, 'tags', 'Ruby').click(), ['red', 'rose', 'ruby'], 'tags')
  expect(await isOpen(page, 'tags')).toBe(true)
  await expect.poll(() => anchored(page, 'tags')).toBe(true)

  for (let label of ['Red', 'Rose', 'Ruby']) {
    await expect(option(page, 'tags', label)).toHaveAttribute('aria-selected', 'true')
  }

  // untick one while open
  await tick(page, () => option(page, 'tags', 'Rose').click(), ['red', 'ruby'], 'tags')
  expect(await isOpen(page, 'tags')).toBe(true)
  await expect(option(page, 'tags', 'Rose')).toHaveAttribute('aria-selected', 'false')

  // a search that leaves Red out of the list keeps its chip on the trigger
  await search(page, 'tags').fill('blue')
  await expect(menu(page, 'tags').locator('[data-atom-option]')).toHaveCount(1)
  await tick(page, () => option(page, 'tags', 'Blue').click(), ['red', 'ruby', 'blue'], 'tags')
  await expect(trigger(page, 'tags')).toContainText('Red')
  await expect(trigger(page, 'tags')).toContainText('Ruby')
  await expect(trigger(page, 'tags')).toContainText('Blue')

  await page.keyboard.press('Escape')
  await expect.poll(() => isOpen(page, 'tags')).toBe(false)
})

test('a multiple listbox without a search ticks by Enter and by click and keeps the focus on its trigger', async ({ page }) => {
  await trigger(page, 'sizes').focus()
  await page.keyboard.press('Enter')
  await settled(page, 'sizes')

  await page.keyboard.press('ArrowDown')
  await expect(option(page, 'sizes', 'Small')).toHaveAttribute('data-active', '')
  await tick(page, () => page.keyboard.press('Enter'), ['small'], 'sizes')
  expect(await isOpen(page, 'sizes')).toBe(true)
  await expect(option(page, 'sizes', 'Small')).toHaveAttribute('data-active', '')

  await page.keyboard.press('ArrowDown')
  await tick(page, () => page.keyboard.press('Enter'), ['small', 'medium'], 'sizes')
  expect(await isOpen(page, 'sizes')).toBe(true)

  await tick(page, () => option(page, 'sizes', 'Large').click(), ['small', 'medium', 'large'], 'sizes')
  expect(await isOpen(page, 'sizes')).toBe(true)
  await expect(trigger(page, 'sizes')).toBeFocused()

  // the keys still work after a click (a click does not move the active option: it is still on Medium)
  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('ArrowDown')
  await expect(option(page, 'sizes', 'Huge')).toHaveAttribute('data-active', '')
  await tick(page, () => page.keyboard.press('Enter'), ['small', 'medium', 'large', 'huge'], 'sizes')
  expect(await isOpen(page, 'sizes')).toBe(true)

  // Enter on a ticked one unticks it, still open
  await tick(page, () => page.keyboard.press('Enter'), ['small', 'medium', 'large'], 'sizes')
  expect(await isOpen(page, 'sizes')).toBe(true)
  await expect(option(page, 'sizes', 'Huge')).toHaveAttribute('aria-selected', 'false')

  await page.keyboard.press('Escape')
  await expect.poll(() => isOpen(page, 'sizes')).toBe(false)
})

test('a multiple filter with grouped options stays open, and closes on its trigger and on a click outside', async ({ page }) => {
  await trigger(page, 'regions').click()
  await settled(page, 'regions')

  await tick(page, () => option(page, 'regions', 'Kedah').click(), ['kedah'], 'regions')
  expect(await isOpen(page, 'regions')).toBe(true)
  await tick(page, () => option(page, 'regions', 'Johor').click(), ['kedah', 'johor'], 'regions')
  expect(await isOpen(page, 'regions')).toBe(true)
  await tick(page, () => option(page, 'regions', 'Penang').click(), ['kedah', 'johor', 'penang'], 'regions')
  expect(await isOpen(page, 'regions')).toBe(true)

  // the trigger closes it
  await trigger(page, 'regions').click()
  await expect.poll(() => isOpen(page, 'regions')).toBe(false)

  // and a click outside
  await trigger(page, 'regions').click()
  await expect.poll(() => isOpen(page, 'regions')).toBe(true)
  await page.locator('[data-server]').click()
  await expect.poll(() => isOpen(page, 'regions')).toBe(false)

  expect((await server(page)).regions).toEqual(['kedah', 'johor', 'penang'])
})

test('a single filter and a single listbox still close on a pick', async ({ page }) => {
  await trigger(page, 'status').click()
  await expect.poll(() => isOpen(page, 'status')).toBe(true)
  await tick(page, () => option(page, 'status', 'Sent').click(), 'sent', 'status')
  await expect.poll(() => isOpen(page, 'status')).toBe(false)

  await trigger(page, 'priority').click()
  await expect.poll(() => isOpen(page, 'priority')).toBe(true)
  await tick(page, () => option(page, 'priority', 'Paid').click(), 'paid', 'priority')
  await expect.poll(() => isOpen(page, 'priority')).toBe(false)

  // by Enter too
  await trigger(page, 'priority').focus()
  await page.keyboard.press('Enter')
  await settled(page, 'priority')
  await expect(option(page, 'priority', 'Paid')).toHaveAttribute('data-active', '')
  await page.keyboard.press('ArrowDown')
  await expect(option(page, 'priority', 'Draft')).toHaveAttribute('data-active', '')
  await tick(page, () => page.keyboard.press('Enter'), 'draft', 'priority')
  await expect.poll(() => isOpen(page, 'priority')).toBe(false)
})
