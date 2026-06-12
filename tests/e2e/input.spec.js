import { test, expect } from '@playwright/test'

// Drives the live input demos on /atom/docs/input. The password visibility
// toggle and clear affixes are now real <button>s (was click-only <div>s);
// getByRole('button', ...) only resolves because of that a11y fix.
test.describe('password toggle', () => {
  test('reveals and hides the value via the labelled button', async ({ page }) => {
    await page.goto('/atom/docs/input')

    // Match the label with a regex — it flips between Show/Hide on toggle, so
    // a fixed-string filter would stop matching after the first click.
    const toggle = page.getByRole('button', { name: /password/i }).first()
    const input = page
      .locator('[data-atom-input]')
      .filter({ has: page.getByRole('button', { name: /password/i }) })
      .first()
      .locator('input')

    await expect(input).toHaveAttribute('type', 'password')
    await expect(toggle).toHaveAccessibleName('Show password')

    await toggle.click()
    await expect(input).toHaveAttribute('type', 'text')
    await expect(toggle).toHaveAccessibleName('Hide password')

    await toggle.click()
    await expect(input).toHaveAttribute('type', 'password')
  })
})
