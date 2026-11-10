import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// The filter bar's own chips for a control that is disabled (its own `disabled`, or a disabled <atom:form>'s): the chip's x is gone, because the control refuses
// the clear and the click would do nothing, "Clear all" clears what can be cleared and is gone when nothing can, and a clear that is sent anyway
// (the bar's handlers called directly, as a hidden x is no guard) changes nothing.

const state = (page, testid) => page.locator(`[data-testid="${testid}"]`)
const chip = (page, testid, label) => state(page, testid).locator('[data-atom-table-filter-chip]').filter({ hasText: label })
const model = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.Alpine.$data(document.querySelector('[x-data*="filters"]')).filters)))

for (const tw of ['floor', 'latest']) {
  for (const chips of ['inline', 'below']) {
    test.describe(`${chips} chips, ${tw} build`, () => {
      const show = async (page, which = 'off') => {
        await page.setViewportSize({ width: 1440, height: 900 })
        await open(page, { path: 'table-filters-disabled', tw, extra: `&chips=${chips}&case=${which}` })
        await expect(chip(page, which, 'Status')).toHaveCount(1)
      }

      test('a disabled control\'s chip has no x, an enabled one keeps it', async ({ page }) => {
        await show(page)
        for (const label of ['Search', 'Status', 'Issued', 'Tags']) {
          await expect(chip(page, 'off', label), `${label} chip`).toHaveCount(1)
          await expect(chip(page, 'off', label).locator('button'), `${label}: no x in a disabled form`).toBeHidden()
        }

        await expect(chip(page, 'off', 'Free').locator('button'), 'the opt-out: its x stays').toBeVisible()

        await show(page, 'on')
        await expect(chip(page, 'on', 'Status').locator('button'), 'enabled: its x').toBeVisible()
      })

      test('Clear all clears what is enabled, leaves a disabled control as it is, and is gone when nothing is left to clear', async ({ page }) => {
        await show(page)
        const before = await model(page)
        const all = state(page, 'off').getByRole('button', { name: 'Clear all' })

        await expect(all, 'one control can still be cleared').toBeVisible()

        // the bar sends the clear to the control that can take it only
        await page.evaluate(() => {
          window.__cleared = []
          window.addEventListener('table-filter:do-clear', (e) => window.__cleared.push(e.detail.key))
        })
        await all.click()
        expect(await page.evaluate(() => window.__cleared), 'Clear all asks the enabled control only').toEqual(['filters.free'])
        await expect.poll(async () => (await model(page)).free, 'the enabled control was cleared').toBeNull()
        const after = await model(page)
        expect({ ...after, free: before.free }, 'the disabled ones were not').toEqual(before)
        expect(await state(page, 'off').locator('input').first().inputValue(), 'the disabled search keeps its text').toBe('abc')
      })

      test('Clear all is gone when every control is disabled', async ({ page }) => {
        await show(page, 'all-off')
        await expect(state(page, 'all-off').getByRole('button', { name: 'Clear all' })).toBeHidden()
      })

      test('a clear sent to a disabled control\'s key writes nothing, even called straight at the bar', async ({ page }) => {
        await show(page)
        const before = await model(page)

        await page.evaluate(() => {
          window.__cleared = []
          window.addEventListener('table-filter:do-clear', (e) => window.__cleared.push(e.detail.key))
          const bar = window.Alpine.$data(document.querySelector('[data-testid="off"] [data-atom-table-filters]'))
          for (const key of ['filters.search', 'filters.status', 'filters.issued', 'filters.tags']) bar.clear(key)
        })
        expect(await page.evaluate(() => window.__cleared), 'the bar sends nothing for a disabled control').toEqual([])

        // and the controls themselves, told by the event the bar would send
        await page.evaluate(() => {
          for (const key of ['filters.search', 'filters.status', 'filters.issued', 'filters.tags']) window.dispatchEvent(new CustomEvent('table-filter:do-clear', { detail: { key } }))
        })
        await page.waitForTimeout(300)

        expect(await model(page)).toEqual(before)
        expect(await state(page, 'off').locator('input').first().inputValue()).toBe('abc')
      })

      test('the x comes and goes with the marker: a flip is followed without a new chip', async ({ page }) => {
        await show(page)
        const free = chip(page, 'off', 'Free').locator('button')

        await expect(free).toBeVisible()
        await page.evaluate(() => document.querySelector('[data-testid="off"] [data-filter-key="filters.free"], [data-testid="off"] [data-atom-filter-key="filters.free"]').setAttribute('data-atom-disabled', 'self'))
        await expect(free, 'disabled after the fact: the x goes').toBeHidden()
        await page.evaluate(() => document.querySelector('[data-testid="off"] [data-atom-filter-key="filters.free"]').removeAttribute('data-atom-disabled'))
        await expect(free, 'and comes back').toBeVisible()
      })
    })
  }
}
