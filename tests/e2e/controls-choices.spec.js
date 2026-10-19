import { test, expect } from '@playwright/test'
import { open, ratio, style, contrastOf, focusByKeyboard } from './controls-helpers.js'

// V4-3a, checkbox, radio, toggle, slider, rating and the uploader: the swatch sizes, no shadow, the ring, and the 3:1 that a
// control's boundary and its checked state owe the surface (a control with no text of its own is found by its shape alone),
// light and dark, for three accents (one of them light), on the consumer's Tailwind.

const swatch = (page, id) => page.getByTestId(id).locator('[aria-hidden="true"]').first()
const input = (page, id) => page.getByTestId(id).locator('input').first()
const ACCENTS = ['#4c55c9', '#f5c518', '#9f5afd']

for (const tw of ['floor', 'latest']) {
  test.describe(`choice controls, ${tw} consumer build`, () => {
    test('a checkbox is a 16px square of radius 4, a radio a 16px circle, a toggle 36 by 20; none has a shadow', async ({ page }) => {
      await open(page, { tw, section: 'choices' })

      for (const id of ['c-checkbox', 'c-checkbox-checked']) {
        const s = await style(swatch(page, id), 'border-top-left-radius', 'border-top-width', 'box-shadow')
        expect([s.width, s.height, s['border-top-left-radius'], s['border-top-width'], s['box-shadow']], id).toEqual([16, 16, '4px', '1px', 'none'])
      }

      for (const id of ['c-radio', 'c-radio-checked']) {
        const s = await style(swatch(page, id), 'border-top-left-radius', 'box-shadow')
        // Tailwind's `rounded-full` is a very large radius, which a browser reports in a form of its own
        expect([s.width, s.height, parseFloat(s['border-top-left-radius']) >= 1000, s['box-shadow']], id).toEqual([16, 16, true, 'none'])
      }

      for (const id of ['c-toggle', 'c-toggle-checked']) {
        const s = await style(swatch(page, id), 'border-top-left-radius', 'box-shadow')
        expect([s.width, s.height, parseFloat(s['border-top-left-radius']) >= 1000, s['box-shadow']], id).toEqual([36, 20, true, 'none'])
      }
    })

    test('the label is 13px; a click on it toggles the control', async ({ page }) => {
      await open(page, { tw, section: 'choices' })

      expect((await style(page.getByTestId('c-checkbox').locator('label > div > div > div').last(), 'font-size'))['font-size']).toBe('13px')

      for (const id of ['c-checkbox', 'c-toggle', 'c-radio']) {
        await page.getByTestId(id).locator('label').click()
        await expect(input(page, id), id).toBeChecked()
      }
    })

    test('the thumb sits clear of the track ends, off and on', async ({ page }) => {
      await open(page, { tw, section: 'choices' })

      for (const id of ['c-toggle', 'c-toggle-checked']) {
        const gaps = await swatch(page, id).evaluate((track) => {
          const thumb = track.querySelector('span').getBoundingClientRect()
          const box = track.getBoundingClientRect()
          return [Math.round(thumb.left - box.left - 1), Math.round(box.right - 1 - thumb.right)]
        })

        expect(gaps[0] + gaps[1], id).toBe(22)
        expect(Math.min(...gaps), id).toBeGreaterThanOrEqual(3)
      }
    })

    test('the ring is a :focus-visible outline of 2px, offset 1px, around the swatch', async ({ page }) => {
      await open(page, { tw, section: 'choices' })

      for (const id of ['c-checkbox', 'c-radio-checked', 'c-toggle']) {
        await input(page, id).focus()
        await page.keyboard.press('Shift+Tab')
        await page.keyboard.press('Tab')

        const ring = await style(swatch(page, id), 'outline-width', 'outline-style', 'outline-offset')
        expect([ring['outline-width'], ring['outline-style'], ring['outline-offset']], id).toEqual(['2px', 'solid', '1px'])
      }

      // a click draws none
      await page.getByTestId('c-checkbox-checked').locator('label').click()
      expect((await style(swatch(page, 'c-checkbox-checked'), 'outline-style'))['outline-style']).toBe('none')
    })

    test('a disabled control is dimmed and takes no click', async ({ page }) => {
      await open(page, { tw, section: 'choices' })

      for (const id of ['c-checkbox-disabled', 'c-radio-disabled', 'c-toggle-disabled']) {
        expect((await style(swatch(page, id), 'opacity')).opacity, id).toBe('0.5')
        await expect(input(page, id)).toBeDisabled()
      }
    })

    test('a card group divides its rows with the border token, not currentColor', async ({ page }) => {
      await open(page, { tw, section: 'choices' })

      const first = page.getByTestId('c-card-group').locator('[data-atom-checkbox]').first()
      const s = await style(first, 'border-bottom-color', 'border-bottom-width', 'color')
      expect(s['border-bottom-width']).toBe('1px')
      expect(s['border-bottom-color']).not.toBe(s.color)
    })

    test('the slider: a 4px track, a 16px thumb with no shadow, a 2px ring on the thumb, the fill in accent-ink', async ({ page }) => {
      await open(page, { tw, section: 'ranges', accent: '#4c55c9' })

      const range = page.getByTestId('r-slider').locator('input[type=range]')
      expect((await style(range)).height).toBe(4)

      // the thumb is a pseudo-element a browser does not report through getComputedStyle (it answers with the input's own
      // style), so its size, border, fill and ring are held by the RestyleGuardsTest on the rules in atom.css, and what is
      // measured here is what the page can see: the track, no shadow, the keyboard ring on the control
      expect((await style(range, 'box-shadow'))['box-shadow']).toBe('none')

      await range.focus()
      await page.keyboard.press('Shift+Tab')
      await page.keyboard.press('Tab')
      expect(await range.evaluate((el) => el.matches(':focus-visible'))).toBe(true)

      // the disabled one is dimmed
      expect((await style(page.getByTestId('r-slider-disabled').locator('input[type=range]'), 'opacity')).opacity).toBe('0.5')
    })

    test('the rating: 20px stars, the fill the accent as text on the canvas, a 2px ring', async ({ page }) => {
      await open(page, { tw, section: 'ranges', accent: '#4c55c9' })

      const star = page.getByTestId('r-rating').locator('[data-atom-rating-icon]').first()
      const box = await style(star)
      expect([box.width, box.height]).toEqual([20, 20])

      const fill = page.getByTestId('r-rating').locator('[data-atom-rating-fill]')
      expect((await fill.evaluate(contrastOf)).front).toEqual([...(await page.getByTestId('r-rating').evaluate(() => {
        const cv = document.createElement('canvas'); cv.width = cv.height = 1
        const g = cv.getContext('2d', { willReadFrequently: true })
        g.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--atom-accent-ink'); g.fillRect(0, 0, 1, 1)
        return [...g.getImageData(0, 0, 1, 1).data].slice(0, 3)
      }))])

      await page.getByTestId('r-rating').locator('[data-atom-rating-track]').focus()
      await page.keyboard.press('Shift+Tab')
      await page.keyboard.press('Tab')
      expect((await style(page.getByTestId('r-rating').locator('[data-atom-rating-track]'), 'outline-width'))['outline-width']).toBe('2px')
    })

    test('the dropzone: a dashed hairline at radius 7, 13px muted text, and the upload button is a control', async ({ page }) => {
      await open(page, { tw, section: 'ranges' })

      const zone = page.getByTestId('r-uploader').locator('button')
      const s = await style(zone, 'border-top-style', 'border-top-width', 'border-top-left-radius', 'font-size', 'box-shadow')
      expect([s['border-top-style'], s['border-top-width'], s['border-top-left-radius'], s['font-size'], s['box-shadow']]).toEqual(['dashed', '1px', '7px', '13px', 'none'])

      expect((await style(page.getByTestId('r-uploader-button').locator('[data-atom-button]'))).height).toBe(28)
    })
  })
}

for (const dark of [false, true]) {
  const theme = dark ? 'dark' : 'light'

  for (const accent of ACCENTS) {
    test(`contrast, ${theme}, accent ${accent}: the boundary and the checked state of each control hold 3:1, its text 4.5:1`, async ({ page }) => {
      await open(page, { dark, accent, section: 'choices' })

      // an unchecked box, radio and toggle are found by their boundary alone
      for (const id of ['c-checkbox', 'c-radio', 'c-toggle']) {
        expect((await swatch(page, id).evaluate(contrastOf, { what: 'border' })).ratio, `${id} boundary`).toBeGreaterThanOrEqual(3)
      }

      // checked: the fill against the surface, and the mark against the fill
      for (const id of ['c-checkbox-checked', 'c-radio-checked', 'c-toggle-checked']) {
        expect((await swatch(page, id).evaluate(contrastOf, { what: 'fill' })).ratio, `${id} fill`).toBeGreaterThanOrEqual(3)
      }

      const mark = page.getByTestId('c-checkbox-checked').locator('svg')
      expect((await mark.evaluate(contrastOf, { what: 'color' })).ratio, 'the check mark on its fill').toBeGreaterThanOrEqual(3)
      expect((await page.getByTestId('c-radio-checked').locator('[aria-hidden="true"] > div').evaluate(contrastOf, { what: 'fill' })).ratio, 'the radio dot on its fill').toBeGreaterThanOrEqual(3)

      // the toggle's thumb on its track, off and on
      for (const id of ['c-toggle', 'c-toggle-checked']) {
        expect((await swatch(page, id).locator('span').evaluate(contrastOf, { what: 'fill' })).ratio, `${id} thumb`).toBeGreaterThanOrEqual(3)
      }

      // the text beside them, on the surface it is drawn on
      for (const id of ['c-checkbox', 'c-radio', 'c-toggle', 'c-checkbox-caption']) {
        expect(await ratio(page.getByTestId(id).locator('label > div > div > div').last()), `${id} text`).toBeGreaterThanOrEqual(4.5)
      }
      expect(await ratio(page.getByTestId('c-checkbox-caption').locator('[data-atom-caption]')), 'the caption').toBeGreaterThanOrEqual(4.5)

      // the ring on the page
      await focusByKeyboard(page, input(page, 'c-checkbox'))
      expect((await swatch(page, 'c-checkbox').evaluate(contrastOf, { what: 'outline' })).ratio, 'the focus ring').toBeGreaterThanOrEqual(3)
    })

    test(`contrast, ${theme}, accent ${accent}: the slider, the rating and the dropzone`, async ({ page }) => {
      await open(page, { dark, accent, section: 'ranges' })

      const range = page.getByTestId('r-slider').locator('input[type=range]')
      expect((await range.evaluate(contrastOf, { what: 'color' })).ratio, 'the slider fill and thumb').toBeGreaterThanOrEqual(3)
      expect((await page.getByTestId('r-rating').locator('[data-atom-rating-fill]').evaluate(contrastOf, { what: 'color' })).ratio, 'a filled star').toBeGreaterThanOrEqual(3)

      await page.getByTestId('r-slider').hover()
      const bubble = page.getByTestId('r-slider').locator('[data-atom-slider-bubble]')
      await expect(bubble).toHaveCSS('opacity', '1')
      expect(await ratio(bubble), 'the value bubble').toBeGreaterThanOrEqual(4.5)

      expect(await ratio(page.getByTestId('r-slider').locator('[data-atom-slider-labels] span').first()), 'the end label').toBeGreaterThanOrEqual(4.5)
      expect(await ratio(page.getByTestId('r-uploader').locator('button')), 'the dropzone text').toBeGreaterThanOrEqual(4.5)
    })
  }
}
