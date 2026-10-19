import { test, expect, devices } from '@playwright/test'
import { open, ratio, style, contrastOf } from './controls-helpers.js'

// V4-3a, the button: three tiers (primary = the solid accent, default = bordered, ghost), the control token for height,
// 16px icons, no shadow, a :focus-visible ring; light and dark, measured on the consumer's Tailwind (`node tests/consumer/build.mjs`).

const { defaultBrowserType, ...ipad } = devices['iPad (gen 7)']

// the control token is 28px compact, 32 comfortable, 40 on a touch device; xs/sm sit 8px/4px under it, md/lg 4px/8px over
const HEIGHTS = {
  compact: { xs: 20, sm: 24, default: 28, md: 32, lg: 36 },
  comfortable: { xs: 24, sm: 28, default: 32, md: 36, lg: 40 },
  touch: { xs: 32, sm: 36, default: 40, md: 44, lg: 48 },
}

const SIZE_TESTIDS = { xs: 'btn-xs', sm: 'btn-sm', default: 'btn-md-default', md: 'btn-md', lg: 'btn-lg' }

for (const tw of ['floor', 'latest']) {
  test.describe(`button, ${tw} consumer build`, () => {
    for (const density of ['compact', 'comfortable']) {
      test(`${density}: heights come from the control token`, async ({ page }) => {
        await open(page, { tw, density, section: 'buttons' })

        for (const [size, testid] of Object.entries(SIZE_TESTIDS)) {
          expect((await style(page.getByTestId(testid))).height, `${size} at ${density}`).toBe(HEIGHTS[density][size])
        }

        // an icon-only button is a square of its size
        const square = await style(page.getByTestId('btn-icon-only'))
        expect(square.width).toBe(square.height)
        expect(square.height).toBe(HEIGHTS[density].default)
        expect((await style(page.getByTestId('btn-sm-icon'))).height).toBe(HEIGHTS[density].sm)
      })
    }

    test.describe('touch (iPad profile)', () => {
      test.use({ ...ipad })

      test('a touch device raises every size to the touch control height', async ({ page }) => {
        await open(page, { tw, section: 'buttons' })

        expect(await page.evaluate(() => matchMedia('(pointer: coarse)').matches)).toBe(true)

        for (const [size, testid] of Object.entries(SIZE_TESTIDS)) {
          expect((await style(page.getByTestId(testid))).height, size).toBe(HEIGHTS.touch[size])
        }
      })
    })

    test('13px at weight 510, a 7px radius, a hairline border, 16px icons beside the text, and no shadow', async ({ page }) => {
      await open(page, { tw, section: 'buttons' })

      const button = await style(page.getByTestId('btn-default'), 'font-size', 'font-weight', 'border-radius', 'border-top-width', 'box-shadow', 'font-family')
      expect(button['font-size']).toBe('13px')
      expect(button['font-weight']).toBe('510')
      expect(button['border-radius']).toBe('7px')
      expect(button['border-top-width']).toBe('1px')
      expect(button['font-family']).toContain('Geist')

      for (const testid of ['btn-primary', 'btn-default', 'btn-ghost', 'btn-danger', 'btn-delete', 'btn-warning', 'btn-success', 'btn-link', 'btn-icon-text', 'btn-icon-only', 'btn-google']) {
        expect((await style(page.getByTestId(testid), 'box-shadow'))['box-shadow'], testid).toBe('none')
      }

      const icon = await style(page.getByTestId('btn-icon-text').locator('[data-atom-icon]:visible').first())
      expect([icon.width, icon.height]).toEqual([16, 16])
      expect((await style(page.getByTestId('btn-xs'))).height).toBe(20)
    })

    test('the tiers: primary is the accent, the default is bordered, ghost is bare until hover', async ({ page }) => {
      await open(page, { tw, section: 'buttons', accent: '#4c55c9' })

      const primary = await style(page.getByTestId('btn-primary'), 'background-color', 'color', 'border-top-color')
      expect(primary['background-color']).toBe('rgb(76, 85, 201)')
      expect(primary['border-top-color']).toBe('rgba(0, 0, 0, 0)')
      expect((await page.getByTestId('btn-primary').evaluate(contrastOf)).front, 'white on the accent').toEqual([255, 255, 255])

      const plain = await style(page.getByTestId('btn-default'), 'background-color', 'border-top-width')
      expect(plain['background-color']).toBe('rgb(255, 255, 255)')
      expect(plain['border-top-width']).toBe('1px')
      expect(await ratio(page.getByTestId('btn-default'), 'border')).toBeGreaterThan(1.2)

      const ghost = page.getByTestId('btn-ghost')
      expect((await style(ghost, 'background-color'))['background-color']).toBe('rgba(0, 0, 0, 0)')
      await ghost.hover()
      await expect.poll(async () => (await style(ghost, 'background-color'))['background-color']).not.toBe('rgba(0, 0, 0, 0)')

      // the default tier answers a hover too
      const plainButton = page.getByTestId('btn-default')
      const before = (await style(plainButton, 'background-color'))['background-color']
      await plainButton.hover()
      await expect.poll(async () => (await style(plainButton, 'background-color'))['background-color']).not.toBe(before)
    })

    test('type="submit" and type="delete" add no icon of their own', async ({ page }) => {
      await open(page, { tw, section: 'buttons' })

      // the loading spinner sits in a hidden overlay; a check or a trash can would be a second, visible icon
      for (const testid of ['btn-submit', 'btn-delete']) {
        const visible = await page.getByTestId(testid).locator('[data-atom-icon]').evaluateAll((icons) => icons.filter((i) => i.getClientRects().length > 0).length)
        expect(visible, testid).toBe(0)
      }
    })

    test('a disabled button takes no pointer and is dimmed', async ({ page }) => {
      await open(page, { tw, section: 'buttons' })

      for (const testid of ['btn-primary-disabled', 'btn-default-disabled', 'btn-ghost-disabled']) {
        const s = await style(page.getByTestId(testid), 'pointer-events', 'opacity')
        expect(s['pointer-events'], testid).toBe('none')
        expect(s.opacity, testid).toBe('0.5')
      }
    })

    test('the ring is a :focus-visible outline, 2px and offset 1px, and a click does not draw it', async ({ page }) => {
      await open(page, { tw, section: 'buttons' })

      const target = page.getByTestId('btn-default')
      await target.click()
      expect((await style(target, 'outline-style'))['outline-style'], 'a mouse click').toBe('none')

      await page.getByTestId('btn-primary').focus()
      await page.keyboard.press('Tab')

      const ring = await style(target, 'outline-width', 'outline-style', 'outline-offset')
      expect(ring['outline-width']).toBe('2px')
      expect(ring['outline-style']).toBe('solid')
      expect(ring['outline-offset']).toBe('1px')
    })

    test('a button in a group keeps its ring uncut, and only the ends are rounded', async ({ page }) => {
      await open(page, { tw, section: 'buttons' })

      const group = page.getByTestId('btn-group')
      const radii = await group.locator('[data-atom-button]').evaluateAll((els) => els.map((el) => {
        const s = getComputedStyle(el)
        return [s.borderStartStartRadius, s.borderEndEndRadius]
      }))

      expect(radii).toEqual([['7px', '0px'], ['0px', '0px'], ['0px', '7px']])
      expect(await group.evaluate((el) => getComputedStyle(el).overflow)).toBe('visible')
    })
  })
}

const TEXT = ['btn-primary', 'btn-default', 'btn-ghost', 'btn-ghost-accent', 'btn-danger', 'btn-delete', 'btn-warning', 'btn-success', 'btn-link', 'btn-icon-text', 'btn-submit', 'btn-google', 'btn-facebook']

for (const dark of [false, true]) {
  for (const accent of ['#4c55c9', '#f5c518', '#9f5afd']) {
    test(`contrast, ${dark ? 'dark' : 'light'}, accent ${accent}: every tier's text on its own fill is at least 4.5:1`, async ({ page }) => {
      await open(page, { dark, accent, section: 'buttons' })

      for (const testid of TEXT) {
        expect(await ratio(page.getByTestId(testid)), `${testid} text on its own surface`).toBeGreaterThanOrEqual(4.5)
      }
    })
  }

  test(`focus ring, ${dark ? 'dark' : 'light'}: the ring on the page is at least 3:1`, async ({ page }) => {
    await open(page, { dark, section: 'buttons' })

    await page.getByTestId('btn-primary').focus()
    await page.keyboard.press('Tab')

    const ring = await page.getByTestId('btn-default').evaluate(contrastOf, { what: 'outline' })
    expect(ring.ratio).toBeGreaterThanOrEqual(3)
  })
}
