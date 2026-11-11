import { test, expect } from '@playwright/test'

// A Livewire update and the command palette (#202). setActive() writes an `id` on the highlighted item, which the server never renders;
// Livewire keys a morph by id when there is no wire:key, so the item was replaced instead of patched, and the focus on it went to <body>.
// helpers/morphed.js takes off the ids the palette wrote before it is morphed, and the palette writes them again after.
//
// Fixture: /atom/e2e/dropdown-morph (palette `morph-palette`, three items with wire:click="bump", which re-renders and leaves the palette open).

const P = 'dialog[data-atom-command]'
const ITEM = `${P} [data-atom-command-item]`

const open = async (page) => {
  await page.goto('/atom/e2e/dropdown-morph')
  await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-command-show', { detail: { name: 'morph-palette' } })))
  await page.waitForFunction(() => document.activeElement?.hasAttribute('data-atom-command-search'))
}
const renders = (page) => page.locator('[data-renders]').first().textContent()
const bump = async (page) => {
  const before = await renders(page)
  await page.evaluate(() => window.Livewire.first().call('bump'))
  await expect(page.locator('[data-renders]').first()).not.toHaveText(before)
  await page.waitForTimeout(200)
}
const tag = (page) => page.evaluate((sel) => {
  window.__items = Array.from(document.querySelectorAll(sel))
}, ITEM)
const report = (page) => page.evaluate((sel) => {
  const now = Array.from(document.querySelectorAll(sel))
  const a = document.activeElement

  return {
    same: now.map((e, i) => e === window.__items[i]),
    active: now.map((e) => e.hasAttribute('data-active')),
    ids: now.map((e) => e.id),
    descendant: document.querySelector('[data-atom-command-search]').getAttribute('aria-activedescendant'),
    focus: a === document.body ? 'body' : a.hasAttribute('data-atom-command-search') ? 'search' : (a.getAttribute('data-label') || a.tagName),
  }
}, ITEM)

test('Tab onto the highlighted item, a morph patches it and the focus stays', async ({ page }) => {
  await open(page)
  await page.keyboard.press('Tab')
  await tag(page)
  expect((await report(page)).focus).toBe('Dashboard')

  await bump(page)

  const after = await report(page)
  expect(after.same).toEqual([true, true, true])
  expect(after.focus).toBe('Dashboard')
  expect(after.active).toEqual([true, false, false])
  expect(after.ids[0]).not.toBe('')
  expect(after.descendant).toBe(after.ids[0])
})

test('ArrowDown, a morph replaces no item and the search keeps the focus', async ({ page }) => {
  await open(page)
  await page.keyboard.press('ArrowDown')
  await tag(page)

  await bump(page)

  const after = await report(page)
  expect(after.same).toEqual([true, true, true])
  expect(after.active).toEqual([false, true, false])
  expect(after.focus).toBe('search')
  expect(after.descendant).toBe(after.ids[1])

  // every item that was ever highlighted is patched too, not only the current one
  await page.keyboard.press('ArrowUp')
  await bump(page)
  const again = await report(page)
  expect(again.same).toEqual([true, true, true])
  expect(again.active).toEqual([true, false, false])
  expect(again.focus).toBe('search')
})

test('a mouse click on an item keeps the focus on it', async ({ page, browserName }) => {
  test.skip(browserName !== 'chromium', 'WebKit does not focus a button on click')

  await open(page)
  await tag(page)
  const before = await renders(page)
  await page.locator(ITEM).filter({ hasText: 'Dashboard' }).click()
  await expect(page.locator('[data-renders]').first()).not.toHaveText(before)
  await page.waitForTimeout(200)

  const after = await report(page)
  expect(after.same).toEqual([true, true, true])
  expect(after.focus).toBe('Dashboard')
})

// A host's own `id` and `wire:key` on an item (#202). Only the ids the palette wrote are taken off before a morph; one the host rendered stays, and
// the item is patched, not replaced. Fixture: `?hostids=1` adds `Host id` (id="host-item") and `Host key` (wire:key="host-key") after the three items.
test('a host id and a wire:key on an item survive a morph, and the item is the same node', async ({ page }) => {
  await page.goto('/atom/e2e/dropdown-morph?hostids=1')
  await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined)
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-command-show', { detail: { name: 'morph-palette' } })))
  await page.waitForFunction(() => document.activeElement?.hasAttribute('data-atom-command-search'))
  await expect(page.locator(ITEM)).toHaveCount(5)

  const hostId = page.locator(`${ITEM}[data-label="Host id"]`)
  const hostKey = page.locator(`${ITEM}[data-label="Host key"]`)
  await expect(hostId).toHaveAttribute('id', 'host-item')

  // highlight each host item in turn (End = the last one, ArrowUp = the one before it), so the palette's own id logic has run on both
  for (const [key, active] of [['End', hostKey], ['ArrowUp', hostId]]) {
    await page.keyboard.press(key)
    await expect(active).toHaveAttribute('data-active', '')
    await tag(page)

    await bump(page)

    const after = await report(page)
    expect(after.same).toEqual([true, true, true, true, true])
    expect(after.ids[3]).toBe('host-item')
    expect(after.ids[after.active.indexOf(true)]).not.toBe('')
    expect(await hostId.getAttribute('id')).toBe('host-item')
    expect(await hostKey.getAttribute('wire:key')).toBe('host-key')
    expect(after.focus).toBe('search')
  }

  // the palette's id on an item without one is still written again after the morph (the item before is not a host item)
  await page.keyboard.press('ArrowUp')
  await bump(page)
  expect((await report(page)).ids[2]).not.toBe('')
})
