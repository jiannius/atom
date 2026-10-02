import { test, expect } from '@playwright/test'

// A caller `class` on checkbox / toggle / radio lands on the visible <label>, and the
// hidden input keeps `name`, `id` and the keyboard behaviour: Space, and arrow keys
// across a radio group, still work with a class passed.

test('checkbox and toggle: class on the label, Space still toggles the input', async ({ page }) => {
  await page.goto('/atom/e2e/attr-sweep')

  for (const kind of ['checkbox', 'toggle']) {
    const label = page.locator(`[data-atom-${kind}]`)
    const input = label.locator('input')

    await expect(label).toHaveClass(/\bmt-3\b/)
    await expect(input).not.toHaveClass(/mt-3/)
    await expect(input).toHaveAttribute('data-sweep', kind)

    await input.focus()
    await page.keyboard.press('Space')
    await expect(input).toBeChecked()

    await page.keyboard.press('Space')
    await expect(input).not.toBeChecked()
  }
})

test('radio: class on the label, arrow keys still move through the group', async ({ page }) => {
  await page.goto('/atom/e2e/attr-sweep')

  const free = page.locator('[data-atom-radio] input[value="free"]')
  const pro = page.locator('[data-atom-radio] input[value="pro"]')

  await expect(page.locator('[data-atom-radio]').first()).toHaveClass(/\bmt-3\b/)
  await expect(free).not.toHaveClass(/mt-3/)

  await free.focus()
  await page.keyboard.press('Space')
  await expect(free).toBeChecked()

  await page.keyboard.press('ArrowDown')
  await expect(pro).toBeChecked()
  await expect(pro).toBeFocused()
  await expect(free).not.toBeChecked()
})
