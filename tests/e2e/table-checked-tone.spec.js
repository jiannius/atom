import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'
import { sweepText, gate, ACCENTS, TEXT, GRAPHIC } from './display-helpers.js'

// The table's checked (bulk) bar: `<x-slot:checked tone="accent">` tints it with the accent-soft token and sets its text and
// icon in the accent ink; without it the bar is unchanged. Measured on the consumer's Tailwind (`node tests/consumer/build.mjs`),
// floor and latest, framed and frameless, light and dark, with three accents (one light), every text role the bar can print
// ("N selected", "Select all", "Show selected", "Clear selection") at 4.5:1 and the icon at 3:1.

const bar = (page) => page.locator('[data-atom-table-checked]')

const show = async (page, { tw, dark = false, accent = null, frameless = false, tone = 'accent' }) => {
  await open(page, { path: 'table-checked', tw, dark, accent, extra: `&sticky=1${frameless ? '&frameless=1' : ''}${tone ? `&tone=${tone}` : ''}` })
  await expect(bar(page)).toBeVisible()
}

const fill = (el) => getComputedStyle(el).backgroundColor

for (const tw of ['floor', 'latest']) {
  test.describe(`table checked bar, ${tw} consumer build`, () => {
    for (const frameless of [false, true]) {
      const shape = frameless ? 'frameless' : 'framed'

      test(`${shape}: tone="accent" tints the bar and does not leak the attribute`, async ({ page }) => {
        await show(page, { tw, frameless })

        expect(await bar(page).evaluate(fill)).not.toBe('rgba(0, 0, 0, 0)')
        await expect(bar(page)).toHaveAttribute('data-tone', 'accent')
        // the slot's own wrapper takes the other attributes, not `tone`
        await expect(bar(page).locator('[tone]')).toHaveCount(0)
        await expect(page.getByTestId('bulk-action')).toBeVisible()

        const box = await bar(page).evaluate((el) => { const s = getComputedStyle(el); return { top: s.borderTopLeftRadius, left: s.paddingLeft } })
        // framed: its own rounded strip; frameless: a square strip inside the host's panel, like the untinted one
        if (frameless) expect(parseFloat(box.top)).toBe(0)
        else expect(parseFloat(box.top)).toBeGreaterThan(0)
        expect(parseFloat(box.left)).toBeGreaterThanOrEqual(12)
      })

      test(`${shape}: without the attribute the bar is unchanged (no fill, no radius)`, async ({ page }) => {
        await show(page, { tw, frameless, tone: null })

        expect(await bar(page).evaluate(fill)).toBe('rgba(0, 0, 0, 0)')
        expect(await bar(page).evaluate((el) => getComputedStyle(el).borderTopLeftRadius)).toBe('0px')
        await expect(bar(page)).not.toHaveAttribute('data-tone', /.*/)
      })

      test(`${shape}: any other tone is the default bar`, async ({ page }) => {
        await show(page, { tw, frameless, tone: 'danger' })

        expect(await bar(page).evaluate(fill)).toBe('rgba(0, 0, 0, 0)')
      })

      for (const dark of [false, true]) {
        for (const accent of [null, ...ACCENTS]) {
          test(`${shape}: every text and the icon read in ${dark ? 'dark' : 'light'} with ${accent ?? 'the default accent'}`, async ({ page }) => {
            await show(page, { tw, dark, accent, frameless })

            // the four text roles are on screen: "1 Selected", "Select all 12", "Show selected", "Clear selection"
            await expect(bar(page)).toContainText('Select all')
            await expect(bar(page)).toContainText('Show selected')
            await expect(bar(page)).toContainText('Clear selection')

            expect(await sweepText(page, '[data-atom-table-checked]', TEXT, 5)).toEqual([])
            await gate(bar(page).locator('svg').first(), GRAPHIC, 'color', 'the selected icon')
          })
        }
      }
    }
  })
}

// The bulk delete counts what is ticked: "Delete this record?" for one, "Delete 2 records?" for two, the total for select-all.
test.describe('table checked bar: the delete confirm counts the selection', () => {
  const dialog = (page) => page.locator('dialog[x-data*="atom-confirm"]')
  const heading = (page) => dialog(page).locator('.text-lg')

  const ask = async (page) => {
    await bar(page).getByRole('button', { name: 'Delete' }).click()
    await expect(dialog(page)).toHaveAttribute('data-open', '')
  }

  test('one ticked row keeps the single wording, two say how many, select-all says the total', async ({ page }) => {
    await open(page, { path: 'table-checked', tw: 'latest', extra: '&delete=1' })
    await expect(bar(page)).toBeVisible()

    await ask(page)
    await expect(heading(page)).toHaveText('Delete this record?')
    await expect(dialog(page)).toContainText("This can't be undone.")
    await dialog(page).getByRole('button', { name: 'Cancel' }).click()
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')

    await page.getByRole('checkbox', { name: 'Select row' }).nth(1).click()
    await ask(page)
    await expect(heading(page)).toHaveText('Delete 2 records?')
    await dialog(page).getByRole('button', { name: 'Cancel' }).click()
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')

    await bar(page).locator('[data-atom-table-select-all]').click()
    await expect(bar(page)).toContainText('12 Selected')
    await ask(page)
    await expect(heading(page)).toHaveText('Delete 12 records?')
  })
})
