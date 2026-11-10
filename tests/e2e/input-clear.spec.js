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

  // heard: wait until the server has "hello" before clearing, or an empty readout afterwards proves nothing. A .live field tells it while
  // typing; a field bound to `change` only when it is left (heard: 'blur'), and it is then focused again to use its Clear button.
  async function typeThenClear(page, probe, { heard = false } = {}) {
    const input = page.locator(`input[data-probe="${probe}"]`)
    await input.click()
    await input.pressSequentially('hello')
    await expect(input).toHaveValue('hello')
    if (heard === 'blur') {
      await page.locator('[data-elsewhere]').click()
      await input.click()
    }
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

  test('wire:model.live: one clear sends one request', async ({ page }) => {
    const input = page.locator('input[data-probe="live"]')
    await input.click()
    await input.pressSequentially('hello')
    await expect(page.locator('[data-server="live"]')).toHaveText('hello')
    // input and change both fire on a clear: they must not each send an update
    const posts = []
    page.on('request', r => { if (r.method() === 'POST' && r.url().includes('livewire')) posts.push(r) })
    await input.locator('xpath=..').getByRole('button', { name: 'Clear' }).click()
    await expect(page.locator('[data-server="live"]')).toHaveText('')
    await page.waitForTimeout(800)
    expect(posts).toHaveLength(1)
  })

  // .lazy and .change listen for `change` only, which a script setting the value never fires: the clear has to send it. The server is read
  // without a $refresh (it would send the client's state anyway and hide a missing event).
  for (const [probe, binding] of [['lazy', 'wire:model.lazy'], ['changed', 'wire:model.live.change']]) {
    test(`${binding}: the server hears the clear without another nudge`, async ({ page }) => {
      await typeThenClear(page, probe, { heard: 'blur' })
      await expect(page.locator(`[data-server="${probe}"]`)).toHaveText('')
      expect(await clientValue(page, probe)).toBe('')
    })
  }

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
