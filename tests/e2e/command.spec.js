import { test, expect } from '@playwright/test'

// Drives the live command-palette demo on /atom/docs/command.
const palette = (page) => page.locator('dialog[data-atom-command]')
const items = (page) => palette(page).locator('[data-atom-command-item]')

test('opens via the meta.k shortcut and closes on Escape', async ({ page }) => {
  await page.goto('/atom/docs/command')

  await expect(palette(page)).toBeHidden()

  await page.keyboard.press('Meta+k')
  await expect(palette(page)).toBeVisible()
  await expect(palette(page).locator('[data-atom-command-search]')).toBeFocused()

  await page.keyboard.press('Escape')
  await expect(palette(page)).toBeHidden()
})

test('opens via the trigger button', async ({ page }) => {
  await page.goto('/atom/docs/command')

  await page.getByRole('button', { name: 'Open palette' }).click()
  await expect(palette(page)).toBeVisible()
})

test('filters items as you type and hides empty groups', async ({ page }) => {
  await page.goto('/atom/docs/command')
  await page.keyboard.press('Meta+k')

  // "Buttons"/"Dashboard" are in the Pages group; "Export" in Actions.
  await page.keyboard.type('export')

  await expect(items(page).filter({ hasText: 'Export' })).toBeVisible()
  await expect(items(page).filter({ hasText: 'Dashboard' })).toBeHidden()

  // the Pages group has no visible items now → the group (and its heading) hides
  await expect(palette(page).locator('[data-atom-command-group]').first()).toBeHidden()
})

test('shows the empty state when nothing matches', async ({ page }) => {
  await page.goto('/atom/docs/command')
  await page.keyboard.press('Meta+k')

  await page.keyboard.type('zzzznomatch')

  await expect(palette(page).locator('[data-atom-command-empty]')).toBeVisible()
})

test('arrow keys move the active item across the list and Enter activates it', async ({ page }) => {
  await page.goto('/atom/docs/command')
  await page.keyboard.press('Meta+k')

  // Wait until the palette is open and the search input is focused. showCommand
  // focuses it and sets the initial active item in the same $nextTick, so this
  // gate guarantees ArrowUp reaches the search keydown handler (not a synthetic
  // race where the key fires before focus lands).
  await expect(palette(page)).toBeVisible()
  await expect(palette(page).locator('[data-atom-command-search]')).toBeFocused()

  // No filter: all items visible; active starts at the first item (Dashboard, an anchor).
  // ArrowUp wraps to the LAST item (Export — an action that dispatches to the result sink).
  await page.keyboard.press('ArrowUp')

  // traversal actually moved: Export is now the active item
  await expect(items(page).filter({ hasText: 'Export' })).toHaveAttribute('data-active', '')

  // Enter activates the traversed-to item
  await page.keyboard.press('Enter')
  await expect(page.locator('[data-atom-command-result]')).toHaveText('export')
})

test('closes on backdrop click', async ({ page }) => {
  await page.goto('/atom/docs/command')
  await page.keyboard.press('Meta+k')
  await expect(palette(page)).toBeVisible()

  // click the ::backdrop: viewport top-left, outside the top-anchored (mt-[10vh],
  // horizontally-centred max-w-xl) dialog box. A backdrop click targets the
  // <dialog> element itself, so backdropClick's `e.target === $root` check fires.
  await page.mouse.click(5, 5)
  await expect(palette(page)).toBeHidden()
})
