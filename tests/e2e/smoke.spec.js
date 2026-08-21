import { test, expect } from '@playwright/test'

test('atom docs button page boots Alpine and renders the ghost-color demo', async ({ page }) => {
  await page.goto('/atom/docs/button')
  await expect(page.getByRole('button', { name: 'Primary' }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Danger' }).first()).toBeVisible()
})

test('the sidebar layout gives the page one h1, hidden but exposed to AT', async ({ page }) => {
  // Every atom app rendered zero h1-h3, so a screen reader got no outline at
  // all. The layout now emits the outline root from its title. Asserted in a
  // browser because the point is that it is *invisible* — the rule is written
  // into atom.css rather than left to Tailwind's sr-only, which a consumer
  // build may not compile.
  await page.goto('/atom/docs/button')

  const h1 = page.locator('h1[data-atom-page-title]')

  await expect(h1).toHaveCount(1)
  await expect(h1).toHaveText(/Button/)

  // clipped to nothing rather than display:none — it has to stay in the
  // accessibility tree, which is the whole point
  const hidden = await h1.evaluate(el => {
    const rect = el.getBoundingClientRect()
    const cs = getComputedStyle(el)

    return {
      width: Math.ceil(rect.width),
      height: Math.ceil(rect.height),
      clipPath: cs.clipPath,
      display: cs.display,
      visibility: cs.visibility,
    }
  })

  expect(hidden.width).toBeLessThanOrEqual(1)
  expect(hidden.height).toBeLessThanOrEqual(1)
  expect(hidden.clipPath).toBe('inset(50%)')
  expect(hidden.display).not.toBe('none')
  expect(hidden.visibility).toBe('visible')
})
