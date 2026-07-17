import { test, expect } from '@playwright/test'

// Drives the rating demos on /atom/docs/rating (plain Alpine — no Livewire).
// The value ⇄ fill (--atom-rating-percent) ⇄ aria-valuenow chain is the tested
// path; the wire:model / x-modelable wiring is asserted in the Pest render test.

const pct = (locator) =>
  locator.evaluate((el) => el.style.getPropertyValue('--atom-rating-percent'))

// role=slider tracks in document order: basic, half, clearable, icon, count.
const sliders = (page) => page.locator('[data-atom-rating-track][role="slider"]')

test('hover previews the fill and a click commits the value', async ({ page }) => {
  await page.goto('/atom/docs/rating')

  const wrapper = page.locator('[data-atom-rating]').first() // basic, seeded 3/5
  const track = wrapper.locator('[data-atom-rating-track]')

  await expect.poll(() => pct(wrapper)).toBe('60%')

  await track.scrollIntoViewIfNeeded()
  const box = await track.boundingBox()
  const y = box.y + box.height / 2

  // hover ~30% across → previews 2 stars (40%)
  await page.mouse.move(box.x + box.width * 0.3, y)
  await expect.poll(() => pct(wrapper)).toBe('40%')

  // leaving the track restores the committed value
  await page.mouse.move(box.x - 40, y)
  await expect.poll(() => pct(wrapper)).toBe('60%')

  // click ~80% across → commits 4 stars
  await page.mouse.click(box.x + box.width * 0.8, y)
  await expect(track).toHaveAttribute('aria-valuenow', '4')
  await expect.poll(() => pct(wrapper)).toBe('80%')
})

test('arrow keys step the value', async ({ page }) => {
  await page.goto('/atom/docs/rating')

  const track = sliders(page).first() // basic, 3/5
  await track.focus()
  await expect(track).toHaveAttribute('aria-valuenow', '3')

  await track.press('ArrowRight')
  await expect(track).toHaveAttribute('aria-valuenow', '4')

  await track.press('ArrowLeft')
  await expect(track).toHaveAttribute('aria-valuenow', '3')
})

test('clearable resets to zero when the current value is clicked again', async ({ page }) => {
  await page.goto('/atom/docs/rating')

  const track = sliders(page).nth(2) // clearable demo, seeded 2/5
  await expect(track).toHaveAttribute('aria-valuenow', '2')

  await track.scrollIntoViewIfNeeded()
  const box = await track.boundingBox()
  // click on the 2nd star (~30% across → value 2) — same as current → resets
  await page.mouse.click(box.x + box.width * 0.3, box.y + box.height / 2)
  await expect(track).toHaveAttribute('aria-valuenow', '0')
})

test('readonly renders a fixed fractional fill and ignores interaction', async ({ page }) => {
  await page.goto('/atom/docs/rating')

  const wrapper = page.locator('[data-atom-rating]:has([role="img"])').first() // readonly, 4.3/5
  const track = wrapper.locator('[data-atom-rating-track]')

  await expect(track).toHaveAttribute('role', 'img')
  await expect.poll(() => pct(wrapper)).toBe('86%')

  await track.scrollIntoViewIfNeeded()
  const box = await track.boundingBox()
  await page.mouse.click(box.x + box.width * 0.2, box.y + box.height / 2)

  // still the seeded value — clicks do nothing when readonly
  await expect.poll(() => pct(wrapper)).toBe('86%')
})
