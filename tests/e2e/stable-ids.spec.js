import { test, expect } from '@playwright/test'

// An accordion item, a slider and a rating that carry no id, name or wire:model have to mint an id from
// what the markup is. A random one changes on every render: Livewire's morph falls back to `el.id` as the
// key, so the slider's input and the rating's track are replaced instead of patched (focus gone), and an
// accordion item's open state, which Alpine keys by that id, is lost (the item closes). The Pest suite
// renders once and cannot see that.
test.describe('unnamed controls across a Livewire morph', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/atom/e2e/stable-ids')
    await page.waitForFunction(() => window.Livewire)
    await expect(page.locator('[data-probe="slider"] input[type="range"]')).toBeVisible()
  })

  // a server round trip that re-renders the component, without moving focus the way a click on a button would
  async function rerender (page, renders) {
    await page.evaluate(() => {
      const id = document.querySelector('[data-renders]').closest('[wire\\:id]').getAttribute('wire:id')
      return window.Livewire.find(id).bump()
    })
    await expect(page.locator('[data-renders]')).toHaveText(String(renders))
  }

  test('an open accordion item stays open through a re-render', async ({ page }) => {
    const item = page.locator('[data-probe="accordion"] [data-atom-accordion-item]').first()
    const trigger = item.getByRole('button', { name: 'First' })
    const region = item.locator('> div').last()

    await trigger.click()
    await expect(trigger).toHaveAttribute('aria-expanded', 'true')
    const handlerBefore = await trigger.getAttribute('x-on:click')

    await rerender(page, 1)

    await expect(trigger).toHaveAttribute('aria-expanded', 'true')
    await expect(region).not.toHaveCSS('grid-template-rows', '0px')
    expect(await trigger.getAttribute('x-on:click')).toBe(handlerBefore)

    // the item that was never opened is still closed, and the two ids differ
    const other = page.locator('[data-probe="accordion"] [data-atom-accordion-item]').nth(1).getByRole('button', { name: 'Second' })
    await expect(other).toHaveAttribute('aria-expanded', 'false')
    expect(await other.getAttribute('x-on:click')).not.toBe(handlerBefore)
  })

  test('the slider keeps its node, id and focus through a re-render', async ({ page }) => {
    const input = page.locator('[data-probe="slider"] input[type="range"]')
    const idBefore = await input.getAttribute('id')
    expect(idBefore).toBeTruthy()

    await page.evaluate(() => { document.querySelector('[data-probe="slider"] input[type="range"]').__atomProbe = 'stamped' })
    await input.focus()

    await rerender(page, 1)

    const after = await page.evaluate(() => {
      const el = document.querySelector('[data-probe="slider"] input[type="range"]')
      return { id: el.id, survived: el.__atomProbe === 'stamped', focused: document.activeElement === el }
    })

    expect(after.id).toBe(idBefore)
    expect(after.survived).toBe(true)
    expect(after.focused).toBe(true)
    await expect(page.locator('[data-probe="slider"] label')).toHaveAttribute('for', idBefore)
  })

  test('the rating keeps its node, id and focus through a re-render', async ({ page }) => {
    const track = page.locator('[data-probe="rating"] [data-atom-rating-track]')
    const idBefore = await track.getAttribute('id')
    expect(idBefore).toBeTruthy()

    await page.evaluate(() => { document.querySelector('[data-probe="rating"] [data-atom-rating-track]').__atomProbe = 'stamped' })
    await track.focus()

    await rerender(page, 1)

    const after = await page.evaluate(() => {
      const el = document.querySelector('[data-probe="rating"] [data-atom-rating-track]')
      return { id: el.id, survived: el.__atomProbe === 'stamped', focused: document.activeElement === el }
    })

    expect(after.id).toBe(idBefore)
    expect(after.survived).toBe(true)
    expect(after.focused).toBe(true)
    await expect(page.locator('[data-probe="rating"] label')).toHaveAttribute('for', idBefore)
  })
})
