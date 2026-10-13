import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/dropdown-morph (tests/Fixtures/DropdownMorphFixture.php), issue #56.
//
// A Livewire morph strips every attribute the server did not render, unless the node carries
// wire:ignore / wire:ignore.self. A dropdown writes its open state with JS across several nodes:
// `data-open` on its root (which the compiled opacity rule reads), the floating-ui inline style on the
// popover, aria-expanded / aria-haspopup on the trigger, aria-describedby on a tooltip's trigger and the
// filtered list of a command palette. A morph landing while one is open used to wipe them: the panel
// went opacity 0 while still positioned and still clickable.
//
// Atom's rig ships no compiled Tailwind, so the opacity rule that makes that visible does not exist
// here; CONSUMER_CSS stands in for it, the rule the dropdown's `opacity-0` and `[data-open]:opacity-100` classes compile to (:where(), so zero specificity). And
// Playwright's toBeVisible() counts opacity:0 as visible, so everything below reads computed opacity,
// :popover-open and the panel's position instead.

const CONSUMER_CSS = `
  :where([data-atom-dropdown]) [data-atom-menu] { opacity: 0; }
  :where([data-atom-dropdown][data-open]) [data-atom-menu] { opacity: 1; }
  /* no utilities in the rig, so an icon has no size and a listbox grows thousands of pixels tall */
  svg { width: 16px; height: 16px; }
`

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const rootOf = (page, name) => probe(page, name).locator('[data-atom-dropdown]')
const menuOf = (page, name) => probe(page, name).locator('[data-atom-menu][popover]')
const triggerOf = (page, name) => probe(page, name).locator('[data-atom-dropdown] > button, [data-atom-dropdown] > [data-atom-dropdown-trigger]').first()

const opacity = (loc) => loc.evaluate((el) => getComputedStyle(el).opacity)
const isOpen = (loc) => loc.evaluate((el) => el.matches(':popover-open'))
const rect = (loc) => loc.evaluate((el) => {
  const { x, y, width, height } = el.getBoundingClientRect()
  return { x: Math.round(x), y: Math.round(y), width: Math.round(width), height: Math.round(height) }
})

async function renderNow (page) {
  const renders = page.locator('[data-renders]')
  const before = Number(await renders.textContent())
  await page.evaluate(() => window.Livewire.first().call('bump'))
  await expect(renders).toHaveText(String(before + 1))
}

// Everything that must still describe an open panel after the morph.
async function expectStillOpen (page, name, expandedTrigger = triggerOf(page, name)) {
  const menu = menuOf(page, name)

  expect(await isOpen(menu)).toBe(true)
  expect(await opacity(menu)).toBe('1')
  await expect(rootOf(page, name)).toHaveAttribute('data-open', /.*/)
  await expect(expandedTrigger).toHaveAttribute('aria-expanded', 'true')
}

test.beforeEach(async ({ page }) => {
  await page.goto('/atom/e2e/dropdown-morph')
  await page.addStyleTag({ content: CONSUMER_CSS })
  await page.waitForFunction(() => window.Livewire && window.Alpine)
})

test('the stand-in opacity rule is real: an open dropdown is opaque, a stripped one is not', async ({ page }) => {
  // Guards the rig itself: if this rule stopped matching, every opacity assertion below would pass for free.
  await triggerOf(page, 'menu').click()
  expect(await opacity(menuOf(page, 'menu'))).toBe('1')

  await rootOf(page, 'menu').evaluate((el) => el.removeAttribute('data-open'))
  expect(await opacity(menuOf(page, 'menu'))).toBe('0')
})

test('a plain dropdown keeps its state through a morph: opacity, position and aria', async ({ page }) => {
  await triggerOf(page, 'menu').click()
  await expectStillOpen(page, 'menu')

  const before = await rect(menuOf(page, 'menu'))
  const styleBefore = await menuOf(page, 'menu').getAttribute('style')
  expect(styleBefore).toContain('inset')

  await renderNow(page)

  await expectStillOpen(page, 'menu')
  expect(await rect(menuOf(page, 'menu'))).toEqual(before)
  expect(await menuOf(page, 'menu').getAttribute('style')).toBe(styleBefore)
  await expect(triggerOf(page, 'menu')).toHaveAttribute('aria-haspopup', 'menu')
})

test('the slot of an open dropdown still updates (the ignore is not a full wire:ignore)', async ({ page }) => {
  await triggerOf(page, 'menu').click()
  await expect(probe(page, 'menu').locator('[data-menu-renders]')).toHaveText('0')

  await renderNow(page)

  await expect(probe(page, 'menu').locator('[data-menu-renders]')).toHaveText('1')
})

test('a server-driven class on the dropdown root still updates while it is open', async ({ page }) => {
  await triggerOf(page, 'menu').click()
  await expect(rootOf(page, 'menu')).toHaveClass(/menu-a/)

  await page.evaluate(() => window.Livewire.first().call('restyle'))

  await expect(rootOf(page, 'menu')).toHaveClass(/menu-b/)
  await expectStillOpen(page, 'menu')
})

test('the dropdown keeps one positioning loop: the data object survives a morph', async ({ page }) => {
  await triggerOf(page, 'menu').click()

  // Alpine hands back a proxy, so identity is useless: tag the object and look for the tag.
  const before = await rootOf(page, 'menu').evaluate((el) => { window.Alpine.$data(el).morphMarker = 'same'; return !!window.Alpine.$data(el).cleanup })
  expect(before).toBe(true)

  await renderNow(page)

  expect(await rootOf(page, 'menu').evaluate((el) => [window.Alpine.$data(el).morphMarker, !!window.Alpine.$data(el).cleanup])).toEqual(['same', true])
})

test('a morph does not break the toggle: the next click on the trigger still closes it', async ({ page }) => {
  await triggerOf(page, 'menu').click()
  await renderNow(page)

  await triggerOf(page, 'menu').click()
  expect(await isOpen(menuOf(page, 'menu'))).toBe(false)
  await expect(rootOf(page, 'menu')).not.toHaveAttribute('data-open')
  await expect(triggerOf(page, 'menu')).toHaveAttribute('aria-expanded', 'false')
})

test('a listbox keeps its opacity, position and aria through a morph', async ({ page }) => {
  await triggerOf(page, 'plain').click()
  await expectStillOpen(page, 'plain')
  const before = await rect(menuOf(page, 'plain'))

  await renderNow(page)

  await expectStillOpen(page, 'plain')
  expect(await rect(menuOf(page, 'plain'))).toEqual(before)
  await expect(triggerOf(page, 'plain')).toHaveAttribute('aria-haspopup', 'listbox')
})

test('a filter select keeps its opacity, position and aria through a morph', async ({ page }) => {
  await triggerOf(page, 'filter').click()
  await expectStillOpen(page, 'filter')
  const before = await rect(menuOf(page, 'filter'))

  await renderNow(page)

  await expectStillOpen(page, 'filter')
  expect(await rect(menuOf(page, 'filter'))).toEqual(before)
})

test('the delayed .live flow: a second field opened before the first one answers stays usable', async ({ page }) => {
  // Pick in A (its .live request takes ~600ms), open B straight away; the response morphs the page
  // under B's open panel.
  await triggerOf(page, 'slow').click()
  await menuOf(page, 'slow').getByText('Beta').click()

  await triggerOf(page, 'plain').click()
  await expectStillOpen(page, 'plain')
  const before = await rect(menuOf(page, 'plain'))
  const three = menuOf(page, 'plain').getByText('Three')
  const optionBefore = await rect(three)

  await expect(page.locator('[data-renders]')).toHaveText('1', { timeout: 10000 })

  // visible, anchored where it was, and the option under the old coordinates is still the same one
  await expectStillOpen(page, 'plain')
  expect(await rect(menuOf(page, 'plain'))).toEqual(before)
  expect(await rect(three)).toEqual(optionBefore)

  await page.mouse.click(optionBefore.x + optionBefore.width / 2, optionBefore.y + optionBefore.height / 2)
  await expect(triggerOf(page, 'plain')).toContainText('Three')
})

test('a tooltip stays anchored and keeps its aria link through a morph', async ({ page }) => {
  const tooltip = probe(page, 'tooltip').locator('[data-atom-tooltip]')
  const content = probe(page, 'tooltip').locator('[data-atom-tooltip-content]')
  const button = probe(page, 'tooltip').getByRole('button', { name: 'Save' })

  await button.hover()
  expect(await isOpen(content)).toBe(true)
  const id = await content.getAttribute('id')
  expect(id).toBeTruthy()
  await expect(button).toHaveAttribute('aria-describedby', id)
  const before = await rect(content)
  expect(before.y).toBeLessThan((await rect(button)).y)

  // the click the issue describes: a wire:click button that re-renders the page
  const renders = page.locator('[data-renders]')
  await button.click()
  await expect(renders).toHaveText('1')

  expect(await isOpen(content)).toBe(true)
  expect(await rect(content)).toEqual(before)
  await expect(content).toHaveAttribute('id', id)
  await expect(button).toHaveAttribute('aria-describedby', id)
})

test('a context menu keeps its position through a morph', async ({ page }) => {
  const target = probe(page, 'context').locator('[data-context-target]')
  const menu = probe(page, 'context').locator('[data-atom-menu][popover]')

  await target.click({ button: 'right', position: { x: 40, y: 20 } })
  expect(await isOpen(menu)).toBe(true)
  const before = await rect(menu)
  expect(before.x).toBeGreaterThan(0)

  await renderNow(page)

  expect(await isOpen(menu)).toBe(true)
  expect(await rect(menu)).toEqual(before)
})

test('a command palette keeps its filtered list and its highlighted item through a morph', async ({ page }) => {
  const palette = page.locator('dialog[data-atom-command]')
  const items = palette.locator('[data-atom-command-item]')
  const search = palette.locator('[data-atom-command-search]')

  await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-command-show', { detail: { name: 'morph-palette' } })))
  await expect(palette).toBeVisible()

  await page.keyboard.type('s')
  // Dashboard has no "s"? it does ("Dashboard"): keep a query that leaves exactly two: "ing" -> Billing, Settings
  await search.fill('ing')
  await expect(items.filter({ visible: true })).toHaveCount(2)

  await page.keyboard.press('ArrowDown')
  await expect(items.nth(2)).toHaveAttribute('data-active', '')

  await renderNow(page)

  await expect(items.filter({ visible: true })).toHaveCount(2)
  await expect(items.nth(0)).toBeHidden()
  await expect(items.nth(2)).toHaveAttribute('data-active', '')
  await expect(search).toHaveAttribute('aria-activedescendant', /.+/)

  // Enter must fire the highlighted one, not one the stripped filter let back in
  await page.keyboard.press('Enter')
  await expect(page.locator('[data-renders]')).toHaveText('2')
})
