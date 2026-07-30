import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/select-morph (tests/Fixtures/SelectMorphFixture.php).
//
// Regression cover for the listbox that stopped loading its options after any
// Livewire re-render: the per-render uniqid() in the markup made the morph
// replace the popover node (orphaning the toggle listener that dispatches
// `open`) and re-evaluate x-data (leaving the popover's Alpine effects bound to
// the discarded data object), so the picker sat on "No Results" forever.
//
// "Bump" is a wire:click that only increments a counter — the morph is the point.

const select = (page, name) => page.locator(`[data-select="${name}"]`)
const trigger = (page, name) => select(page, name).locator('[data-atom-dropdown] > button')
const options = (page, name) => select(page, name).locator('[data-atom-option]')

async function bump (page) {
  const renders = page.locator('[data-renders]')
  const before = Number(await renders.textContent())
  await page.locator('[data-bump]').click()
  await expect(renders).toHaveText(String(before + 1))
}

test('a callback-backed listbox fetches its options on open', async ({ page }) => {
  await page.goto('/atom/e2e/select-morph')

  await trigger(page, 'callback').click()
  await expect(options(page, 'callback').first()).toBeVisible()
  await expect(options(page, 'callback')).toHaveCount(246)
})

test('a callback-backed listbox still fetches after a Livewire re-render', async ({ page }) => {
  await page.goto('/atom/e2e/select-morph')

  // never opened before the morph — the state the bug rendered unusable
  await bump(page)

  await trigger(page, 'callback').click()
  await expect(options(page, 'callback').first()).toBeVisible()
  await expect(options(page, 'callback')).toHaveCount(246)

  // and again on a later re-render
  await page.keyboard.press('Escape')
  await bump(page)
  await trigger(page, 'callback').click()
  await expect(options(page, 'callback')).toHaveCount(246)
})

test('a static-options listbox still lists its options after a re-render', async ({ page }) => {
  await page.goto('/atom/e2e/select-morph')

  await bump(page)

  await trigger(page, 'static').click()
  await expect(options(page, 'static')).toHaveCount(2)
  await expect(options(page, 'static').first()).toBeVisible()
})

test('a selected option survives a re-render', async ({ page }) => {
  await page.goto('/atom/e2e/select-morph')

  await trigger(page, 'static').click()
  await options(page, 'static').filter({ hasText: 'Published' }).click()
  await expect(trigger(page, 'static')).toContainText('Published')

  await bump(page)

  // the x-data is no longer re-evaluated on morph, so the picker keeps its state
  await expect(trigger(page, 'static')).toContainText('Published')
})

// A stable id keeps Livewire's morph from replacing the popover, but consumer
// markup can still get replaced (a re-minted wire:key, a conditional wrapper).
// The dropdown's listeners are bound on its root rather than on the popover node
// so that a swap can't orphan the handler that dispatches `open` — asserted on
// the select's state, since a hand-replaced node carries no Alpine effects.
test('a replaced popover node still drives the fetch', async ({ page }) => {
  await page.goto('/atom/e2e/select-morph')

  const fetched = await page.evaluate(async () => {
    const root = document.querySelector('[data-select="callback"]')
    const popover = root.querySelector('[data-atom-menu]')
    popover.replaceWith(popover.cloneNode(true))

    root.querySelector('[data-atom-dropdown] > button').click()
    await new Promise(r => setTimeout(r, 1500))

    return root._x_dataStack[0].options.length
  })

  expect(fetched).toBe(246)
})

test('the listbox popover keeps its id across a re-render', async ({ page }) => {
  await page.goto('/atom/e2e/select-morph')

  const menu = select(page, 'callback').locator('[data-atom-menu]')
  const before = await menu.getAttribute('id')

  await bump(page)

  expect(before).toBeTruthy()
  expect(await menu.getAttribute('id')).toBe(before)
})
