import { test, expect, devices } from '@playwright/test'
import { open, ratio, style, contrastOf } from './controls-helpers.js'

// V4-3a, the text controls: input (every type), textarea, select (native, listbox, filter), date and time pickers, otp.
// Heights from the control token, the 13px body size, the strong hairline, no shadow, a :focus-visible ring hugging the border,
// invalid in the danger tone; text, placeholder, icon and ring contrast in light and dark. On the consumer's Tailwind.

const { defaultBrowserType, ...ipad } = devices['iPad (gen 7)']

// the box that is the control: the input itself, or the wrapper that draws the border
const BOX = {
  'f-input': 'input',
  'f-input-icon': 'input',
  'f-password': 'input',
  'f-number': 'input',
  'f-tel': 'input[type=tel]',
  'f-select-native': '[data-atom-select-native] > div:first-of-type',
  'f-select-listbox': '[data-atom-select-combobox]',
  'f-select-filter': '[data-atom-select-combobox]',
  'f-date': 'input',
  'f-date-range': 'input',
  'f-time': '[data-atom-time-picker], div[x-data="timePicker()"]',
}

const heights = { compact: 28, comfortable: 32, touch: 40 }

for (const tw of ['floor', 'latest']) {
  test.describe(`text controls, ${tw} consumer build`, () => {
    for (const density of ['compact', 'comfortable']) {
      test(`${density}: every single-line control is the control token high`, async ({ page }) => {
        await open(page, { tw, density, section: 'fields' })

        for (const [id, selector] of Object.entries(BOX)) {
          const box = page.getByTestId(id).locator(selector).first()
          expect((await style(box)).height, `${id} at ${density}`).toBe(heights[density])
        }
      })
    }

    test.describe('touch (iPad profile)', () => {
      test.use({ ...ipad })

      test('a touch device makes them 40px', async ({ page }) => {
        await open(page, { tw, section: 'fields' })

        for (const [id, selector] of Object.entries(BOX)) {
          expect((await style(page.getByTestId(id).locator(selector).first())).height, id).toBe(heights.touch)
        }
      })
    })

    test('13px Geist, 7px radius, a 1px strong hairline on the surface, no shadow', async ({ page }) => {
      await open(page, { tw, section: 'fields', accent: '#4c55c9' })

      for (const id of ['f-input', 'f-textarea', 'f-date', 'f-tel']) {
        const control = page.getByTestId(id).locator('input, textarea').first()
        const s = await style(control, 'font-size', 'font-family', 'border-radius', 'border-top-width', 'box-shadow', 'background-color')
        expect(s['font-size'], id).toBe('13px')
        expect(s['font-family'], id).toContain('Geist')
        expect(s['border-radius'], id).toBe('7px')
        expect(s['border-top-width'], id).toBe('1px')
        expect(s['box-shadow'], id).toBe('none')
        expect(s['background-color'], id).toBe('rgb(255, 255, 255)')
      }

      // the same on the boxes that draw their border on a wrapper
      for (const id of ['f-select-native', 'f-select-listbox', 'f-time']) {
        const s = await style(page.getByTestId(id).locator(BOX[id]).first(), 'border-radius', 'border-top-width', 'box-shadow')
        expect([s['border-radius'], s['border-top-width'], s['box-shadow']], id).toEqual(['7px', '1px', 'none'])
      }
    })

    test('a keyboard focus rings the field: 2px, hugging the border, the border in the focus colour', async ({ page }) => {
      await open(page, { tw, section: 'fields', accent: '#4c55c9' })

      const input = page.getByTestId('f-input-empty').locator('input')
      await input.focus()

      const ring = await style(input, 'outline-width', 'outline-style', 'outline-offset', 'border-top-color', 'outline-color')
      expect(ring['outline-width']).toBe('2px')
      expect(ring['outline-style']).toBe('solid')
      expect(ring['outline-offset']).toBe('0px')
      expect(ring['border-top-color']).toBe(ring['outline-color'])

      // a composite draws it on its box, and the three inputs inside the time picker none of their own
      const time = page.getByTestId('f-time')
      await time.locator('input').first().focus()
      expect(await time.locator('input').first().evaluate((el) => getComputedStyle(el).outlineStyle)).toBe('none')
      expect(await time.locator(BOX['f-time']).first().evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')

      const native = page.getByTestId('f-select-native')
      await native.locator('select').focus()
      await page.keyboard.press('Shift+Tab')
      await page.keyboard.press('Tab')
      expect(await native.locator(BOX['f-select-native']).evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')
    })

    test('disabled is sunken, invalid is the danger tone', async ({ page }) => {
      await open(page, { tw, section: 'fields' })

      const sunken = 'rgb(243, 241, 237)'
      expect((await style(page.getByTestId('f-input-disabled').locator('input'), 'background-color', 'cursor'))).toMatchObject({ 'background-color': sunken, cursor: 'not-allowed' })
      expect((await style(page.getByTestId('f-textarea-disabled').locator('textarea'), 'background-color'))['background-color']).toBe(sunken)
      expect((await style(page.getByTestId('f-select-native-disabled').locator('[data-atom-select-native] > div:first-of-type'), 'background-color'))['background-color']).toBe(sunken)
      expect((await style(page.getByTestId('f-date-disabled').locator('input'), 'background-color'))['background-color']).toBe(sunken)

      const danger = 'rgb(163, 38, 28)'
      for (const [id, selector] of [['f-input-invalid', 'input'], ['f-textarea-invalid', 'textarea'], ['f-select-native-invalid', '[data-atom-select-native] > div:first-of-type'], ['f-select-listbox-invalid', '[data-atom-select-combobox]']]) {
        expect((await style(page.getByTestId(id).locator(selector), 'border-top-color'))['border-top-color'], id).toBe(danger)
      }
    })

    test('the listbox option rows are 28px, 13px, with the same hover as a menu', async ({ page }) => {
      await open(page, { tw, section: 'fields' })

      await page.getByTestId('f-select-listbox').locator('[data-atom-select-combobox]').click()
      const row = page.locator('[data-atom-select-listbox] [data-atom-option]:visible').first()
      await expect(row).toBeVisible()

      const s = await style(row, 'font-size', 'border-radius', 'box-shadow')
      expect(s.height).toBeGreaterThanOrEqual(28)
      expect(s['font-size']).toBe('13px')
      expect(s['border-radius']).toBe('6px')

      await row.hover()
      await expect.poll(async () => (await style(row, 'background-color'))['background-color']).not.toBe('rgba(0, 0, 0, 0)')
    })

    test('otp boxes are square, 40px, on the same recipe', async ({ page }) => {
      await open(page, { tw, section: 'fields' })

      const box = page.getByTestId('f-otp').locator('input').first()
      const s = await style(box, 'border-radius', 'border-top-width', 'box-shadow')
      expect([s.width, s.height, s['border-radius'], s['border-top-width'], s['box-shadow']]).toEqual([40, 40, '7px', '1px', 'none'])
    })
  })
}

for (const dark of [false, true]) {
  const theme = dark ? 'dark' : 'light'

  test.describe(`contrast, ${theme}`, () => {
    for (const accent of ['#4c55c9', '#f5c518']) {
      test(`text, placeholder and icons of every field, accent ${accent}`, async ({ page }) => {
        await open(page, { dark, accent, section: 'fields' })

        // the value, on the field's own surface
        for (const [id, selector] of [['f-input', 'input'], ['f-textarea', 'textarea'], ['f-tel', 'input[type=tel]'], ['f-password', 'input'], ['f-number', 'input'], ['f-select-native', 'select'], ['f-select-filter', '[data-atom-select-combobox]']]) {
          expect(await ratio(page.getByTestId(id).locator(selector).first()), `${id} value`).toBeGreaterThanOrEqual(4.5)
        }

        // the placeholder, which is text the user has to read
        for (const [id, selector] of [['f-input-empty', 'input'], ['f-input-icon', 'input'], ['f-date', 'input'], ['f-date-range', 'input'], ['f-tel', 'input[type=tel]']]) {
          const r = (await page.getByTestId(id).locator(selector).first().evaluate(contrastOf, { what: 'placeholder' })).ratio
          expect(r, `${id} placeholder`).toBeGreaterThanOrEqual(4.5)
        }

        // the icons are UI, 3:1
        for (const id of ['f-input-icon', 'f-password', 'f-select-native', 'f-date', 'f-time']) {
          const icon = page.getByTestId(id).locator('[data-atom-icon]').first()
          expect(await ratio(icon), `${id} icon`).toBeGreaterThanOrEqual(3)
        }
      })

      test(`the ring and the invalid border are 3:1 against the surface, accent ${accent}`, async ({ page }) => {
        await open(page, { dark, accent, section: 'fields' })

        const input = page.getByTestId('f-input-empty').locator('input')
        await input.focus()
        expect((await input.evaluate(contrastOf, { what: 'outline' })).ratio, 'the focus ring').toBeGreaterThanOrEqual(3)

        for (const [id, selector] of [['f-input-invalid', 'input'], ['f-textarea-invalid', 'textarea'], ['f-select-native-invalid', '[data-atom-select-native] > div:first-of-type']]) {
          expect((await page.getByTestId(id).locator(selector).evaluate(contrastOf, { what: 'border' })).ratio, `${id} border`).toBeGreaterThanOrEqual(3)
        }
      })
    }

    test('the open listbox and the open calendar: every text on its own surface', async ({ page }) => {
      await open(page, { dark, section: 'fields' })

      await page.getByTestId('f-select-listbox').locator('[data-atom-select-combobox]').click()
      const rows = page.locator('[data-atom-select-listbox] [data-atom-option]:visible')
      await expect(rows.first()).toBeVisible()
      for (let i = 0; i < await rows.count(); i++) {
        expect(await ratio(rows.nth(i)), `option ${i}`).toBeGreaterThanOrEqual(4.5)
      }
      await page.keyboard.press('Escape')

      await page.getByTestId('f-date').locator('input').click()
      const calendar = page.locator('[data-atom-date-picker-calendar]:visible').first()
      await expect(calendar.locator('.pika-table td button').first()).toBeVisible()

      // a day, a weekday heading, the title and the selected day
      const day = calendar.locator('.pika-table td:not(.is-disabled):not(.is-selected) button').nth(10)
      expect(await ratio(day), 'a day').toBeGreaterThanOrEqual(4.5)
      expect(await ratio(calendar.locator('.pika-table th').first()), 'a weekday').toBeGreaterThanOrEqual(4.5)
      expect(await ratio(calendar.locator('.pika-label').first()), 'the month').toBeGreaterThanOrEqual(4.5)

      await day.click()
      await page.getByTestId('f-date').locator('input').click()
      const selected = page.locator('[data-atom-date-picker-calendar]:visible .pika-table td.is-selected button').first()
      await expect(selected).toBeVisible()
      expect(await ratio(selected), 'the selected day').toBeGreaterThanOrEqual(4.5)
      expect((await selected.evaluate(contrastOf, { what: 'fill' })).ratio, 'the selected day fill against the panel').toBeGreaterThanOrEqual(3)
    })
  })
}
