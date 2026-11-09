import { test, expect } from '@playwright/test'

// The clearable input's x button clears the field and must tell the model. It used to $dispatch('input', '') from the button, an
// event that starts on the button and bubbles up past the input, so the input's own wire:model / x-model listener never heard it:
// the DOM was empty and the model kept the old text, even through a server round trip.
test.describe('clearable input reaches the model', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/atom/e2e/clearable-input')
    await page.waitForFunction(() => window.Livewire)
    await expect(page.locator('input[data-probe="plain"]')).toBeVisible()
  })

  const clientValue = (page, prop) => page.evaluate(prop => window.Livewire.all().find(c => c.name === 'atom-e2e-clearable-input').$wire[prop], prop)
  const serverValue = async (page, prop) => {
    await page.evaluate(() => window.Livewire.all().find(c => c.name === 'atom-e2e-clearable-input').$wire.$refresh())
    return (await page.locator(`[data-server="${prop}"]`).textContent()).trim()
  }

  async function typeThenClear(page, probe, { heard = false } = {}) {
    const input = page.locator(`input[data-probe="${probe}"]`)
    await input.click()
    await input.pressSequentially('hello')
    await expect(input).toHaveValue('hello')
    // a .live field has told the server by now; wait for that, or an empty readout afterwards proves nothing
    if (heard) await expect(page.locator(`[data-server="${probe}"]`)).toHaveText('hello')
    // the clear button sits in the same wrapper as the input
    const clear = input.locator('xpath=..').getByRole('button', { name: 'Clear' })
    await expect(clear).toBeVisible()
    await clear.click()
    await expect(input).toHaveValue('')
    await expect(input).toBeFocused()
    await expect(clear).toBeHidden()
    return input
  }

  for (const prop of ['plain', 'live']) {
    test(`wire:model${prop === 'live' ? '.live' : ''}: the property is empty after clear, and on the server`, async ({ page }) => {
      await typeThenClear(page, prop)
      await expect.poll(() => clientValue(page, prop)).toBe('')
      await expect.poll(() => serverValue(page, prop)).toBe('')
    })
  }

  test('wire:model.live: the server hears the clear without another nudge', async ({ page }) => {
    await typeThenClear(page, 'live', { heard: true })
    await expect(page.locator('[data-server="live"]')).toHaveText('')
  })

  test('behind an ancestor with x-on:input.stop: the property is still empty after clear', async ({ page }) => {
    await typeThenClear(page, 'stopped', { heard: true })
    await expect(page.locator('[data-server="stopped"]')).toHaveText('')
    await expect.poll(() => clientValue(page, 'stopped')).toBe('')
  })

  test('wire:model.lazy: the property is empty after clear', async ({ page }) => {
    await typeThenClear(page, 'lazy')
    await expect.poll(() => clientValue(page, 'lazy')).toBe('')
    await expect.poll(() => serverValue(page, 'lazy')).toBe('')
  })

  test('wire:model.blur: the property is empty once the field is left', async ({ page }) => {
    await typeThenClear(page, 'blur')
    await page.locator('[data-elsewhere]').click()
    await expect.poll(() => clientValue(page, 'blur')).toBe('')
    await expect.poll(() => serverValue(page, 'blur')).toBe('')
  })

  test('x-model: the Alpine value is empty after clear', async ({ page }) => {
    const input = page.locator('input[data-probe="alpine"]')
    await input.click()
    await input.pressSequentially('hello')
    await expect(page.locator('[data-alpine-model]')).toHaveText('hello')
    await input.locator('xpath=..').getByRole('button', { name: 'Clear' }).click()
    await expect(input).toHaveValue('')
    await expect(page.locator('[data-alpine-model]')).toHaveText('')
  })
})
