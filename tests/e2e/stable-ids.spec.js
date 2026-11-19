import { test, expect } from '@playwright/test'

// An accordion item, a slider and a rating that carry no id, name or wire:model have to mint an id from
// what the markup is. A random one changes on every render: Livewire's morph falls back to `el.id` as the
// key, so the slider's input and the rating's track are replaced instead of patched (focus gone), and an
// accordion item's open state, which Alpine keys by that id, is lost (the item closes). The Pest suite
// renders once and cannot see that.
//
// Two instances of the same component sit on the page with identical markup, and each test re-renders one of
// them alone, as a Livewire update does. The second instance is the case that broke when repeats were numbered
// by a ledger shared across the page load: its ids were numbered on the load and not on an update of it alone.
for (const instance of [1, 2]) {
  test.describe(`unnamed controls across a Livewire morph, instance ${instance}`, () => {
    const scope = (page) => page.locator(`[data-instance="${instance}"]`)

    test.beforeEach(async ({ page }) => {
      await page.goto('/atom/e2e/stable-ids')
      await page.waitForFunction(() => window.Livewire)
      await expect(scope(page).locator('[data-probe="slider"] input[type="range"]')).toBeVisible()
    })

    // a server round trip that re-renders this instance, without moving focus the way a click on a button would
    async function rerender (page, renders) {
      await page.evaluate((n) => {
        const id = document.querySelector(`[data-instance="${n}"] [data-renders]`).closest('[wire\\:id]').getAttribute('wire:id')
        return window.Livewire.find(id).bump()
      }, instance)
      await expect(scope(page).locator('[data-renders]')).toHaveText(String(renders))
    }

    test('an open accordion item stays open through a re-render', async ({ page }) => {
      const item = scope(page).locator('[data-probe="accordion"] [data-atom-accordion-item]').first()
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
      const other = scope(page).locator('[data-probe="accordion"] [data-atom-accordion-item]').nth(1).getByRole('button', { name: 'Second' })
      await expect(other).toHaveAttribute('aria-expanded', 'false')
      expect(await other.getAttribute('x-on:click')).not.toBe(handlerBefore)
    })

    test('the slider keeps its node, id and focus through a re-render', async ({ page }) => {
      const input = scope(page).locator('[data-probe="slider"] input[type="range"]')
      const idBefore = await input.getAttribute('id')
      expect(idBefore).toBeTruthy()

      await input.evaluate((el) => { el.__atomProbe = 'stamped' })
      await input.focus()

      await rerender(page, 1)

      const after = await input.evaluate((el) => ({ id: el.id, survived: el.__atomProbe === 'stamped', focused: document.activeElement === el }))

      expect(after.id).toBe(idBefore)
      expect(after.survived).toBe(true)
      expect(after.focused).toBe(true)
      await expect(scope(page).locator('[data-probe="slider"] label')).toHaveAttribute('for', idBefore)
    })

    test('the rating keeps its node, id and focus through a re-render', async ({ page }) => {
      const track = scope(page).locator('[data-probe="rating"] [data-atom-rating-track]')
      const idBefore = await track.getAttribute('id')
      expect(idBefore).toBeTruthy()

      await track.evaluate((el) => { el.__atomProbe = 'stamped' })
      await track.focus()

      await rerender(page, 1)

      const after = await track.evaluate((el) => ({ id: el.id, survived: el.__atomProbe === 'stamped', focused: document.activeElement === el }))

      expect(after.id).toBe(idBefore)
      expect(after.survived).toBe(true)
      expect(after.focused).toBe(true)
      await expect(scope(page).locator('[data-probe="rating"] label')).toHaveAttribute('for', idBefore)
    })
  })
}

test('the two instances print different ids, so a label points at its own control', async ({ page }) => {
  await page.goto('/atom/e2e/stable-ids')
  const ids = await page.locator('[data-probe="slider"] input[type="range"]').evaluateAll((els) => els.map((el) => el.id))

  expect(ids).toHaveLength(2)
  expect(ids[0]).not.toBe(ids[1])
})
