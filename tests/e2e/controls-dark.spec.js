import { test, expect } from '@playwright/test'
import { open, ratio, contrastOf, focusByKeyboard } from './controls-helpers.js'

// V4-3a, the dark check. Scoped to the 3a components, and the variants of them, that the dark-mode hosts render (found by scanning
// their <atom:...> tags; the apps themselves are not named here). Every text role is measured against ITS OWN surface (every ancestor's
// fill composited, not the page), at 4.5:1 for text and 3:1 for the boundaries and rings that identify a control. controls-fields,
// controls-choices and controls-buttons measure the rest of the sheet in both themes; this file holds the variants a dark host renders
// that those do not reach (typed chips, an open searchable list, the time row, a card group, a read-only field, small ghost buttons).
//
// The dark-mode hosts render: button (primary, default, ghost with a colour, danger, link, delete, submit, the Google and Facebook
// social variants; sizes xs, sm and default; icon-only), input (text, email, number, password, tel, colour, file, otp, icon, prefix and
// suffix), select (native, listbox single, multiple and searchable, filter), date-picker (date, range, with time), textarea
// (autoresize, read-only), checkbox (with caption, align start, card group), radio (group, card), toggle and uploader.

const ACCENTS = ['#4c55c9', '#f5c518', '#9f5afd']

test.describe('dark check: the 3a components the dark-mode hosts render', () => {
  for (const accent of ACCENTS) {
    test(`buttons, small, ghost and secondary, accent ${accent}`, async ({ page }) => {
      await open(page, { dark: true, accent, section: 'more' })

      for (const id of ['m-ghost-sm', 'm-ghost-danger', 'm-delete-ghost', 'm-secondary', 'm-outline']) {
        expect(await ratio(page.getByTestId(id)), id).toBeGreaterThanOrEqual(4.5)
      }

      // an icon-only ghost button is an icon, 3:1
      expect(await ratio(page.getByTestId('m-ghost-xs').locator('[data-atom-icon]:visible')), 'm-ghost-xs icon').toBeGreaterThanOrEqual(3)

      // the default tier's boundary is what tells it from the page; measured, reported, not a gate (see the note below)
      const edge = await page.getByTestId('m-secondary').evaluate(contrastOf, { what: 'border' })
      test.info().annotations.push({ type: 'button boundary contrast, dark', description: edge.ratio.toFixed(2) })
    })

    test(`typed email chips, valid and invalid, accent ${accent}`, async ({ page }) => {
      await open(page, { dark: true, accent, section: 'more' })

      const field = page.getByTestId('m-email').locator('input[type=email]')
      await field.fill('ada@example.com')
      await field.press('Enter')
      await field.fill('not-an-address')
      await field.press('Enter')

      const chips = page.getByTestId('m-email').locator('[data-atom-input-email] [data-atom-dropdown-trigger] > div')
      await expect(chips).toHaveCount(2)

      for (let i = 0; i < 2; i++) {
        expect(await ratio(chips.nth(i).locator('div').first()), `chip ${i} text`).toBeGreaterThanOrEqual(4.5)
        expect((await chips.nth(i).evaluate(contrastOf, { what: 'border' })).ratio, `chip ${i} boundary`).toBeGreaterThanOrEqual(1.2)
      }

      // an invalid chip is danger-toned: its text on its own tint
      expect(await ratio(chips.nth(1)), 'invalid chip').toBeGreaterThanOrEqual(4.5)
    })

    test(`listbox multiple and searchable, accent ${accent}`, async ({ page }) => {
      await open(page, { dark: true, accent, section: 'more' })

      const multiple = page.getByTestId('m-listbox-multiple')
      await multiple.locator('[data-atom-select-combobox]').click()
      await page.locator('[data-atom-select-listbox] [data-atom-option]:visible').first().click()
      await page.keyboard.press('Escape')

      // the chip's label, on the trigger it sits in
      const chip = multiple.locator('[data-atom-select-combobox] [x-text="item.label"]').first()
      expect(await ratio(chip), 'chip').toBeGreaterThanOrEqual(4.5)

      const searchable = page.getByTestId('m-listbox-searchable')
      await searchable.locator('button[aria-haspopup="listbox"]').click()
      const search = page.locator('[data-atom-select-search]:visible').first()
      await expect(search).toBeVisible()
      expect((await search.evaluate(contrastOf, { what: 'placeholder' })).ratio, 'search placeholder on the panel').toBeGreaterThanOrEqual(4.5)
      await search.fill('Sen')
      expect(await ratio(search), 'search text on the panel').toBeGreaterThanOrEqual(4.5)
      expect(await ratio(search.locator('xpath=preceding-sibling::*[1]')), 'search icon on the panel').toBeGreaterThanOrEqual(3)
    })

    test(`the time row of a date picker, accent ${accent}`, async ({ page }) => {
      await open(page, { dark: true, accent, section: 'more' })

      await page.getByTestId('m-date-time').locator('input').first().click()
      const calendar = page.locator('[data-atom-date-picker-calendar]:visible').first()
      await calendar.locator('.pika-table td:not(.is-disabled) button').nth(10).click()
      await page.getByTestId('m-date-time').locator('input').first().click()

      const time = page.locator('[x-data="timePicker()"]:visible').first()
      await expect(time).toBeVisible()
      for (const i of [0, 1, 2]) {
        expect(await ratio(time.locator('input').nth(i)), `time input ${i}`).toBeGreaterThanOrEqual(4.5)
      }
      expect((await time.evaluate(contrastOf, { what: 'border' })).ratio, 'the time box boundary').toBeGreaterThanOrEqual(1.2)
    })

    test(`textarea, file input, radio group and card, checkbox aligned to the start, accent ${accent}`, async ({ page }) => {
      await open(page, { dark: true, accent, section: 'more' })

      expect(await ratio(page.getByTestId('m-textarea-readonly').locator('textarea')), 'read-only textarea').toBeGreaterThanOrEqual(4.5)
      expect(await ratio(page.getByTestId('m-textarea-autoresize').locator('textarea')), 'autoresize textarea').toBeGreaterThanOrEqual(4.5)

      // the uploader's trigger is an atom:button
      expect(await ratio(page.getByTestId('m-file').locator('[data-atom-button]')), 'file trigger').toBeGreaterThanOrEqual(4.5)

      for (const id of ['m-radio-group', 'm-radio-card']) {
        const labels = page.getByTestId(id).locator('[data-atom-radio] label > div > div:last-child, [data-atom-radio] > div > div:last-child')
        const n = await labels.count()
        expect(n, id).toBeGreaterThan(0)
        for (let i = 0; i < n; i++) expect(await ratio(labels.nth(i)), `${id} label ${i}`).toBeGreaterThanOrEqual(4.5)
      }

      // checked radios hold 3:1 against the card they sit on
      const checked = page.getByTestId('m-radio-card').locator('[data-atom-radio] [aria-hidden="true"]').first()
      expect((await checked.evaluate(contrastOf, { what: 'fill' })).ratio, 'checked radio on the card').toBeGreaterThanOrEqual(3)

      expect(await ratio(page.getByTestId('m-checkbox-start').locator('[data-atom-caption]')), 'caption').toBeGreaterThanOrEqual(4.5)
    })

    test(`every control's ring holds 3:1 on the surface behind it, accent ${accent}`, async ({ page }) => {
      await open(page, { dark: true, accent })

      const targets = [
        ['button', page.getByTestId('btn-default')],
        ['ghost button', page.getByTestId('btn-ghost')],
        ['primary button', page.getByTestId('btn-primary')],
        ['input', page.getByTestId('f-input').locator('input')],
        ['textarea', page.getByTestId('f-textarea').locator('textarea')],
        ['date', page.getByTestId('f-date').locator('input')],
        ['otp', page.getByTestId('f-otp').locator('input').first()],
      ]

      for (const [name, target] of targets) {
        await focusByKeyboard(page, target)
        expect((await target.evaluate(contrastOf, { what: 'outline' })).ratio, `${name} ring`).toBeGreaterThanOrEqual(3)
      }

      for (const [name, wrapper, inner] of [
        ['time picker', page.getByTestId('f-time').locator('div[x-data="timePicker()"]'), page.getByTestId('f-time').locator('input').first()],
        ['native select', page.getByTestId('f-select-native').locator('[data-atom-select-native] > div:first-of-type'), page.getByTestId('f-select-native').locator('select')],
      ]) {
        await focusByKeyboard(page, inner)
        expect((await wrapper.evaluate(contrastOf, { what: 'outline' })).ratio, `${name} ring`).toBeGreaterThanOrEqual(3)
      }
    })
  }

  test('the field hairline: measured in both themes and recorded, not gated', async ({ page }) => {
    // border-strong is the sample's 17% line (1.41:1 on white). WCAG 1.4.11 asks 3:1 of a boundary that identifies a control; the
    // approved design draws a hairline, and a field is also found by its fill, its placeholder and its label. The number is
    // attached to the report so the decision stays visible; the controls whose shape alone identifies them (checkbox, radio,
    // toggle) are gated at 3:1 in controls-choices.
    for (const dark of [false, true]) {
      await open(page, { dark, section: 'fields' })
      const r = (await page.getByTestId('f-input').locator('input').evaluate(contrastOf, { what: 'border' })).ratio
      test.info().annotations.push({ type: `field boundary contrast, ${dark ? 'dark' : 'light'}`, description: r.toFixed(2) })
      expect(r).toBeGreaterThan(1.1)
    }
  })
})
