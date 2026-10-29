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
  /* the listbox's own width limits: without them it stretches to the page and floating-ui's shift moves it a frame after open */
  .max-w-xl { max-width: 36rem; }
  .min-w-sm { min-width: 24rem; }
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

test('a closed popover menu still takes a server-driven attribute; an open one keeps its position and opacity', async ({ page }) => {
  const menu = menuOf(page, 'menu')
  await expect(menu).toHaveAttribute('data-state', 'menu-a')

  // closed: the node is patched in full
  await page.evaluate(() => window.Livewire.first().call('restyle'))
  await expect(menu).toHaveAttribute('data-state', 'menu-b')

  // open: its own attributes are left alone, its content is not
  await triggerOf(page, 'menu').click()
  await expectStillOpen(page, 'menu')
  const before = await rect(menu)
  const styleBefore = await menu.getAttribute('style')

  await renderNow(page)

  await expectStillOpen(page, 'menu')
  expect(await rect(menu)).toEqual(before)
  expect(await menu.getAttribute('style')).toBe(styleBefore)
  await expect(probe(page, 'menu').locator('[data-menu-renders]')).toHaveText('1')
})

test('a morph does not leave a closed popover menu with a stale inline style', async ({ page }) => {
  await triggerOf(page, 'menu').click()
  await triggerOf(page, 'menu').click()
  expect(await isOpen(menuOf(page, 'menu'))).toBe(false)

  await renderNow(page)

  // and it still opens in the right place afterwards
  await triggerOf(page, 'menu').click()
  await expectStillOpen(page, 'menu')
  const box = await rect(menuOf(page, 'menu'))
  const trigger = await rect(triggerOf(page, 'menu'))
  expect(Math.abs(box.x - trigger.x)).toBeLessThan(4)
  expect(box.y).toBeGreaterThan(trigger.y)
})

test('two tooltips in one component both survive a morph, anchored and still described', async ({ page }) => {
  const contentA = probe(page, 'tooltip').locator('[data-atom-tooltip-content]')
  const contentB = probe(page, 'tooltip2').locator('[data-atom-tooltip-content]')
  const buttonA = probe(page, 'tooltip').getByRole('button', { name: 'Save' })
  const buttonB = probe(page, 'tooltip2').getByRole('button', { name: 'Delete' })

  // one is held open by the pointer, the other by keyboard focus
  await buttonA.hover()
  await buttonB.focus()
  expect(await isOpen(contentA)).toBe(true)
  expect(await isOpen(contentB)).toBe(true)

  const idA = await contentA.getAttribute('id')
  const idB = await contentB.getAttribute('id')
  expect(idA).toBeTruthy()
  expect(idB).toBeTruthy()
  expect(idA).not.toBe(idB)
  const before = [await rect(contentA), await rect(contentB)]

  await renderNow(page)

  expect(await isOpen(contentA)).toBe(true)
  expect(await isOpen(contentB)).toBe(true)
  expect([await rect(contentA), await rect(contentB)]).toEqual(before)
  await expect(contentA).toHaveAttribute('id', idA)
  await expect(contentB).toHaveAttribute('id', idB)
  await expect(buttonA).toHaveAttribute('aria-describedby', idA)
  await expect(buttonB).toHaveAttribute('aria-describedby', idB)
})

test('a closed tooltip content takes a server-driven attribute', async ({ page }) => {
  const contentB = probe(page, 'tooltip2').locator('[data-atom-tooltip-content]')
  await expect(contentB).toHaveAttribute('data-state', 'menu-a')

  await page.evaluate(() => window.Livewire.first().call('restyle'))

  await expect(contentB).toHaveAttribute('data-state', 'menu-b')
})

test('a re-run init() does not stack morph callbacks', async ({ page }) => {
  // A dropdown, a tooltip and a command palette each register one callback per element, so a morph
  // runs one sync apiece however often their init() ran. Count the calls with a wrapper on the instance.
  const counts = await page.evaluate(async () => {
    const wrap = (el, method) => {
      const data = window.Alpine.$data(el)
      for (let i = 0; i < 3; i++) data.init()
      let calls = 0
      const original = data[method].bind(data)
      data[method] = (...args) => { calls++; return original(...args) }
      return () => calls
    }

    const dropdown = wrap(document.querySelector('[data-probe="menu"] [data-atom-dropdown]'), 'syncState')
    const tooltip = wrap(document.querySelector('[data-probe="tooltip"] [data-atom-tooltip]'), '_atomLink')

    const palette = document.querySelector('dialog[data-atom-command]')
    window.dispatchEvent(new CustomEvent('atom-command-show', { detail: { name: 'morph-palette' } }))
    const command = wrap(palette, 'filter')

    // opening the palette filters on the next tick; let that settle so only the morph is counted
    await new Promise((resolve) => setTimeout(resolve, 50))
    const before = [dropdown(), tooltip(), command()]
    await window.Livewire.first().call('bump')
    await new Promise((resolve) => setTimeout(resolve, 100))

    return [dropdown() - before[0], tooltip() - before[1], command() - before[2]]
  })

  expect(counts).toEqual([1, 1, 1])
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

test('a morph that swaps the trigger rebinds the tooltip to the new one: opening its menu still hides the tooltip', async ({ page }) => {
  const trigger = probe(page, 'swap').locator('button[data-generation]')
  const content = probe(page, 'swap').locator('[data-atom-tooltip-content]')
  const menu = menuOf(page, 'swap')

  await expect(trigger).toHaveAttribute('data-generation', '0')
  await page.evaluate(() => window.Livewire.first().call('swap'))
  await expect(trigger).toHaveAttribute('data-generation', '1')

  await trigger.hover()
  await expect(content).toBeVisible()
  await trigger.click()
  await expect(menu).toBeVisible()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  await expect(content).toBeHidden()
})

test('a tooltip taken out of the page stops watching its trigger', async ({ page }) => {
  const errors = []
  page.on('pageerror', (error) => errors.push(error.message))

  const hides = await page.evaluate(async () => {
    const root = document.querySelector('[data-probe="swap"] [data-atom-tooltip]')
    const trigger = root.querySelector(':scope > button')
    const data = window.Alpine.$data(root)
    let calls = 0
    const original = data._atomHide.bind(data)
    data._atomHide = (...args) => { calls++; return original(...args) }

    await window.Livewire.first().call('removeSwap')
    await new Promise((resolve) => setTimeout(resolve, 100))
    const afterRemoval = calls

    // the old trigger is out of the page; a watcher that outlived the tooltip would still hide on this
    trigger.setAttribute('aria-expanded', 'true')
    await new Promise((resolve) => setTimeout(resolve, 100))

    return { gone: !document.contains(root), afterRemoval, afterChange: calls }
  })

  expect(hides.gone).toBe(true)
  expect(hides.afterChange).toBe(hides.afterRemoval)
  expect(errors).toEqual([])
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
