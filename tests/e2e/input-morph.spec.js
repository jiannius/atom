import { test, expect } from '@playwright/test'

// <atom:input> and <atom:textarea> carry an id so their <label for> can name them.
// That id is also Livewire's morph key when nothing else keys the element:
//
//   key: (el) => el.hasAttribute('wire:id')  ? el.getAttribute('wire:id')
//              : el.hasAttribute('wire:key') ? el.getAttribute('wire:key')
//              : el.id                                  // livewire.esm.js
//
// so an id minted per render gives the morph a key that never matches, and it
// replaces the control instead of patching it. The Pest suite renders once and
// cannot see that, so the guard has to live here.
test.describe('input across a Livewire morph', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/atom/e2e/input-morph')
    await page.waitForFunction(() => window.Livewire)
    await expect(page.locator('input[data-probe="live"]')).toBeVisible()
  })

  test('keeps the same DOM node, focus and caret through a re-render', async ({ page }) => {
    const input = page.locator('input[data-probe="live"]')
    const idBefore = await input.getAttribute('id')

    // Stamp the live node: a patched node keeps the expando, a replaced one loses it.
    await page.evaluate(() => {
      document.querySelector('input[data-probe="live"]').__atomProbe = 'stamped'
    })

    await input.click()
    await input.type('Acme')
    await page.waitForResponse(r => r.url().includes('livewire'))
    await page.waitForTimeout(400)

    const after = await page.evaluate(() => {
      const el = document.querySelector('input[data-probe="live"]')
      return {
        id: el.getAttribute('id'),
        survived: el.__atomProbe === 'stamped',
        focused: document.activeElement === el,
      }
    })

    expect(after.survived, 'the input node was replaced by the morph').toBe(true)
    expect(after.id, 'the id churned across the re-render').toBe(idBefore)
    expect(after.focused, 'focus was lost mid-typing').toBe(true)

    // the real symptom: everything typed after the round trip goes nowhere
    await page.keyboard.type(' Holdings')
    await expect(input).toHaveValue('Acme Holdings')
  })

  test('keeps the textarea node through a re-render', async ({ page }) => {
    const textarea = page.locator('textarea[data-probe="notes"]')
    const idBefore = await textarea.getAttribute('id')

    await page.evaluate(() => {
      document.querySelector('textarea[data-probe="notes"]').__atomProbe = 'stamped'
    })

    await page.locator('[data-bump]').click()
    await expect(page.locator('[data-renders]')).toHaveText('1')

    const after = await page.evaluate(() => {
      const el = document.querySelector('textarea[data-probe="notes"]')
      return { id: el.getAttribute('id'), survived: el.__atomProbe === 'stamped' }
    })

    expect(after.survived, 'the textarea node was replaced by the morph').toBe(true)
    expect(after.id).toBe(idBefore)
  })

  // input.general's clearable button captures the input node once in x-init. The button
  // is patched (it has no id, so its key matches), so if the input it captured is
  // replaced the button keeps rendering and clears a node that is no longer on the page.
  test('the clearable button still clears the input after a re-render', async ({ page }) => {
    const input = page.locator('input[data-probe="clearable"]')
    const clear = page.locator('button[aria-label="Clear"]').first()

    await input.click()
    await input.type('hello')
    await expect(clear).toBeVisible()

    await page.locator('[data-bump]').click()
    await expect(page.locator('[data-renders]')).toHaveText('1')
    await page.waitForTimeout(300)

    await clear.click()
    await expect(input).toHaveValue('')
  })

  // The tel widget's dial code lives in Alpine state that never reaches the server, so
  // replacing the wrapper loses the user's pick with nothing to restore it from. This one
  // only goes red when BOTH halves regress — a churning id and that id back on the Alpine
  // wrapper — which is exactly the shape the original fix shipped in; either half alone
  // leaves the wrapper's key stable enough to be patched.
  test('the tel input keeps the dial code the user picked', async ({ page }) => {
    const country = page.locator('select[data-atom-input-tel-country]')

    await country.selectOption('+65')
    await expect(country).toHaveValue('+65')

    await page.locator('[data-bump]').click()
    await expect(page.locator('[data-renders]')).toHaveText('1')
    await page.waitForTimeout(300)

    await expect(country).toHaveValue('+65')
  })
})
