import { test, expect } from '@playwright/test'

// Verifies the keyboard-a11y rework: the real <input> is sr-only (focusable +
// in the a11y tree) so Space toggles natively. Before the fix the input was
// display:none and the visible swatch was a role/tabindex <div> with no
// handler — keyboard users could not toggle at all.

test('checkbox is keyboard-focusable and Space toggles it', async ({ page }) => {
  await page.goto('/atom/docs/checkbox')

  const checkbox = page.locator('[data-atom-checkbox] input[type="checkbox"]').first()

  await expect(checkbox).not.toBeChecked()

  await checkbox.focus()
  await expect(checkbox).toBeFocused()

  await page.keyboard.press('Space')
  await expect(checkbox).toBeChecked()

  await page.keyboard.press('Space')
  await expect(checkbox).not.toBeChecked()
})

test('toggle is keyboard-focusable and Space toggles it', async ({ page }) => {
  await page.goto('/atom/docs/toggle')

  const toggle = page.locator('[data-atom-toggle] input[type="checkbox"]').first()

  await expect(toggle).not.toBeChecked()

  await toggle.focus()
  await expect(toggle).toBeFocused()

  await page.keyboard.press('Space')
  await expect(toggle).toBeChecked()
})
