import { test, expect } from '@playwright/test'

// Drives the slider demos on /atom/docs/slider. The docs pages run on plain
// Alpine (no Livewire), so these cover the value ⇄ bubble ⇄ fill reactive chain;
// the wire:model / x-modelable wiring itself is asserted in the Pest render test.

// The "Value bubble" demo — the only slider carrying a [data-atom-slider-bubble].
const bubbleSlider = (page) => page.locator('[data-atom-slider]:has([data-atom-slider-bubble])').first()

test('arrow keys change the value and update the bubble + fill', async ({ page }) => {
  await page.goto('/atom/docs/slider')

  const slider = bubbleSlider(page)
  const input = slider.locator('[data-atom-slider-input]')
  const bubble = slider.locator('[data-atom-slider-bubble]')

  await input.focus()
  await expect(input).toHaveValue('65') // bubble demo seeds value=65

  await input.press('ArrowRight')
  await expect(input).toHaveValue('66')
  await expect(bubble).toHaveText('66')

  // percent var drives both the fill gradient and the bubble position
  const pct = await slider.evaluate((el) => el.style.getPropertyValue('--atom-slider-percent'))
  expect(pct).toBe('66%')
})

test('the value bubble is hidden at rest and shown on focus', async ({ page }) => {
  await page.goto('/atom/docs/slider')

  const slider = bubbleSlider(page)
  const bubble = slider.locator('[data-atom-slider-bubble]')

  await expect(bubble).toHaveCSS('opacity', '0')

  await slider.locator('[data-atom-slider-input]').focus()
  await expect(bubble).toHaveCSS('opacity', '1')
})

test('the seeded value renders the correct initial fill', async ({ page }) => {
  await page.goto('/atom/docs/slider')

  // first demo (Basic) seeds value=40 → 40% fill
  const basic = page.locator('[data-atom-slider]').first()
  const pct = await basic.evaluate((el) => el.style.getPropertyValue('--atom-slider-percent'))
  expect(pct).toBe('40%')
})

test('a disabled slider cannot be interacted with', async ({ page }) => {
  await page.goto('/atom/docs/slider')

  await expect(page.locator('[data-atom-slider-input][disabled]').first()).toBeDisabled()
})
