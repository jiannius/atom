import { test, expect, devices } from '@playwright/test'
import { openDisplay, ratio, style, gate, token, contrastOf, ACCENTS, TEXT, GRAPHIC } from './display-helpers.js'
import { focusByKeyboard } from './controls-helpers.js'

// V4-3b, the rest of the data display: card, callout, dd, list, kbd, avatar, profile, progress, skeleton, pagination and the table's
// parts. Sizes and structure in light, then every text role against ITS OWN surface (every ancestor's fill composited, not the page),
// in both themes and for three accents, on the consumer's Tailwind (`node tests/consumer/build.mjs`), floor and latest.
// Sizes and structure only, never a text width: Linux CI draws DejaVu Sans, which is much wider than the fonts on a Mac.

const { defaultBrowserType, ...ipad } = devices['iPad (gen 7)']

/** Let a hover's transition finish before reading what it ends at. */
const settle = (locator) => locator.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished.catch(() => null))))

for (const tw of ['floor', 'latest']) {
  test.describe(`data display, ${tw} consumer build`, () => {
    test.describe('card', () => {
      test('a fill and a hairline at the panel radius with 16px of padding, and no shadow', async ({ page }) => {
        await openDisplay(page, { tw, section: 'panels' })

        for (const id of ['card', 'card-subtle', 'card-divided', 'card-inset', 'card-stats-up']) {
          const s = await style(page.getByTestId(id), 'border-top-width', 'border-top-left-radius', 'box-shadow', 'outline-style')
          expect([id, s['border-top-width'], s['border-top-left-radius'], s['box-shadow']], id).toEqual([id, '1px', '10px', 'none'])
        }

        expect((await style(page.getByTestId('card'), 'padding-top', 'padding-left'))['padding-top']).toBe('16px')
        expect((await style(page.getByTestId('card'), 'padding-left'))['padding-left']).toBe('16px')
        expect((await style(page.getByTestId('card-inset'), 'padding-top'))['padding-top']).toBe('0px')
      })

      test('the default card is the surface, the subtle one the sunken fill, and both keep the hairline', async ({ page }) => {
        await openDisplay(page, { tw, section: 'panels' })

        expect((await style(page.getByTestId('card'), 'background-color'))['background-color']).toBe(await token(page, 'surface'))
        expect((await style(page.getByTestId('card-subtle'), 'background-color'))['background-color']).toBe(await token(page, 'surface-sunken'))

        for (const id of ['card', 'card-subtle']) {
          expect((await style(page.getByTestId(id), 'border-top-color'))['border-top-color'], id).not.toBe('rgba(0, 0, 0, 0)')
        }
      })

      test('a divided card rules its children with the hairline, and a cover bleeds to the edge', async ({ page }) => {
        await openDisplay(page, { tw, section: 'panels' })

        // Tailwind 4 rules a divided child with its bottom border, on every child but the last
        const [first, second] = [page.getByTestId('card-divided').locator('> div').nth(0), page.getByTestId('card-divided').locator('> div').nth(1)]
        expect((await style(first, 'border-bottom-width'))['border-bottom-width']).toBe('1px')
        expect((await style(second, 'border-bottom-width'))['border-bottom-width']).toBe('0px')
        expect((await style(second, 'padding-top'))['padding-top']).toBe('16px')

        const cover = await page.getByTestId('card-cover').locator('figure').evaluate((el) => {
          const card = el.parentElement.getBoundingClientRect()
          const box = el.getBoundingClientRect()
          return [Math.round(box.left - card.left), Math.round(box.top - card.top)]
        })
        // the card's own 1px border sits outside the padding box the cover bleeds into
        expect(cover).toEqual([1, 1])
      })

      test('the stats variant: a 22px figure in tabular figures at weight 590, a 16px arrow, and a fixed 128px height', async ({ page }) => {
        await openDisplay(page, { tw, section: 'panels' })

        const figure = page.getByTestId('card-stats-up').locator('[class*="text-2xl"]')
        const s = await style(figure, 'font-size', 'font-weight', 'font-variant-numeric')
        expect([s['font-size'], s['font-weight']]).toEqual(['22px', '590'])
        expect(s['font-variant-numeric']).toContain('tabular-nums')

        const arrow = await style(page.getByTestId('card-stats-up').locator('[data-atom-icon]'))
        expect([arrow.width, arrow.height]).toEqual([16, 16])
        expect((await style(page.getByTestId('card-stats-up'))).height).toBe(128)
      })

      test('a stats card tints on hover and draws no outline', async ({ page }) => {
        await openDisplay(page, { tw, section: 'panels' })

        const card = page.getByTestId('card-stats-plain')
        await card.hover()
        await settle(card)

        const s = await style(card, 'background-color', 'outline-style')
        expect(s['background-color']).toBe(await token(page, 'surface-sunken'))
        expect(s['outline-style']).toBe('none')
      })
    })

    test.describe('callout', () => {
      const VARIANTS = ['info', 'success', 'warning', 'danger', 'default']

      test('a tone\'s tint with a hairline of its ink, at the panel radius, no shadow, 16px icon', async ({ page }) => {
        await openDisplay(page, { tw, section: 'panels' })

        const fills = []

        for (const variant of VARIANTS) {
          const root = page.getByTestId(`callout-${variant}`)
          const s = await style(root, 'border-top-width', 'border-top-left-radius', 'box-shadow', 'background-color', 'border-top-color', 'padding-left', 'padding-top')
          expect([variant, s['border-top-width'], s['border-top-left-radius'], s['box-shadow'], s['padding-left'], s['padding-top']]).toEqual([variant, '1px', '10px', 'none', '16px', '12px'])
          expect(s['border-top-color'], variant).not.toBe('rgba(0, 0, 0, 0)')
          fills.push(s['background-color'])

          const icon = await style(root.locator('[data-atom-icon]').first())
          expect([variant, icon.width, icon.height]).toEqual([variant, 16, 16])
        }

        expect(new Set(fills).size).toBe(5)
        expect(fills[0]).toBe(await token(page, 'info-bg'))
        expect(fills[4]).toBe(await token(page, 'neutral-bg'))
      })

      test('the dismiss button is a 28px target with a 16px icon, keeps clear of the text, and closes the callout', async ({ page }) => {
        await openDisplay(page, { tw, section: 'panels' })

        const root = page.getByTestId('callout-closeable')
        const button = root.getByRole('button', { name: 'Dismiss' })
        const box = await style(button)
        expect([box.width, box.height]).toEqual([28, 28])
        expect((await style(button.locator('[data-atom-icon]'))).width).toBe(16)

        const clear = await page.evaluate(() => {
          const callout = document.querySelector('[data-testid="callout-closeable"]')
          const edge = callout.querySelector('button').getBoundingClientRect().left
          return [...callout.querySelectorAll('[data-atom-callout-heading], .flex-col > *')].every((n) => n.getBoundingClientRect().right <= edge)
        })
        expect(clear).toBe(true)

        await button.click()
        await expect(root).toBeHidden()
      })

      test('a heading from a slot is read in the tone\'s ink too', async ({ page }) => {
        await openDisplay(page, { tw, section: 'panels' })

        expect((await style(page.getByTestId('callout-slot-heading'), 'color')).color).toBe((await style(page.getByTestId('callout-info').locator('[data-atom-callout-heading]'), 'color')).color)
      })
    })

    test.describe('description list and list', () => {
      test('a label in the muted token and a value in the foreground, 13px, and a long token wraps inside its card', async ({ page }) => {
        await openDisplay(page, { tw, section: 'lists' })

        const dt = await style(page.getByTestId('dd-email').locator('dt'), 'font-size', 'color')
        const dd = await style(page.getByTestId('dd-email').locator('dd'), 'font-size', 'color')
        expect(dt['font-size']).toBe('13px')
        expect(dt.color).not.toBe(dd.color)
        expect(dd.color).toBe((await style(page.locator('body'), 'color')).color)

        const fits = await page.getByTestId('dd-long').evaluate((el) => {
          const card = el.closest('[data-atom-card]')
          return el.querySelector('dd').getBoundingClientRect().right <= card.getBoundingClientRect().right
        })
        expect(fits).toBe(true)

        await expect(page.getByTestId('dd-empty').locator('dd')).toHaveText('--')
      })

      test('the list heading is a 12px muted label, a row tints on hover, and the rail is the hairline', async ({ page }) => {
        await openDisplay(page, { tw, section: 'lists' })

        const heading = await style(page.locator('[data-atom-list] > div').first(), 'font-size', 'text-transform', 'font-weight')
        expect([heading['font-size'], heading['text-transform']]).toEqual(['12px', 'none'])
        expect(heading['font-weight']).toBe('510')

        const rail = await style(page.getByTestId('list'), 'border-left-width', 'border-left-color')
        expect(rail['border-left-width']).toBe('1px')
        expect(rail['border-left-color']).not.toBe('rgba(0, 0, 0, 0)')

        const row = page.locator('[data-atom-list-item]').first()
        expect((await style(row, 'background-color'))['background-color']).toBe('rgba(0, 0, 0, 0)')
        await row.hover()
        await settle(row)
        expect((await style(row, 'background-color'))['background-color']).not.toBe('rgba(0, 0, 0, 0)')
        expect((await style(row, 'border-top-left-radius'))['border-top-left-radius']).toBe('7px')
      })

      test('the remove button is a labelled 16px icon, muted until hover', async ({ page }) => {
        await openDisplay(page, { tw, section: 'lists' })

        const remove = page.getByRole('button', { name: 'Remove' })
        expect((await style(remove.locator('[data-atom-icon]'))).width).toBe(16)
      })
    })

    test.describe('kbd', () => {
      test('a 20px key cap with a 6px radius and a strong hairline on the sunken fill, 12px text, no shadow', async ({ page }) => {
        await openDisplay(page, { tw, section: 'lists' })

        for (const key of await page.getByTestId('kbd-combo').locator('kbd').all()) {
          const s = await style(key, 'border-top-width', 'border-top-left-radius', 'font-size', 'box-shadow', 'background-color')
          expect([s.height, s['border-top-width'], s['border-top-left-radius'], s['font-size'], s['box-shadow']]).toEqual([20, '1px', '6px', '12px', 'none'])
          expect(s['background-color']).toBe(await token(page, 'surface-sunken'))
        }

        expect((await style(page.getByTestId('kbd-single'))).height).toBe(20)
        expect((await style(page.getByTestId('kbd-single'))).width).toBeGreaterThanOrEqual(20)
      })
    })

    test.describe('avatar and profile', () => {
      const SIZES = { xs: 24, sm: 28, default: 34, lg: 48, xl: 64 }

      test('five sizes: 24, 28, 34, 48 and 64px, squares of radius 7 (10 from lg), or circles', async ({ page }) => {
        await openDisplay(page, { tw, section: 'people' })

        for (const [size, px] of Object.entries(SIZES)) {
          const s = await style(page.getByTestId(`avatar-${size}`), 'border-top-left-radius', 'border-top-width', 'box-shadow')
          expect([size, s.width, s.height, s['border-top-width'], s['box-shadow']]).toEqual([size, px, px, '1px', 'none'])
          expect(s['border-top-left-radius'], size).toBe(['lg', 'xl'].includes(size) ? '10px' : '7px')
        }

        for (const size of ['xs', 'sm', 'default', 'lg']) {
          const s = await style(page.getByTestId(`avatar-round-${size}`), 'border-top-left-radius')
          expect(parseFloat(s['border-top-left-radius']), size).toBeGreaterThanOrEqual(1000)
          expect(s.width).toBe(s.height)
        }
      })

      test('initials on the neutral tone at weight 590; the photo fills the tile and keeps the hairline', async ({ page }) => {
        await openDisplay(page, { tw, section: 'people' })

        const s = await style(page.getByTestId('avatar-default'), 'background-color', 'font-weight', 'font-size')
        expect(s['background-color']).toBe(await token(page, 'neutral-bg'))
        expect([s['font-weight'], s['font-size']]).toEqual(['590', '13px'])

        const img = await page.getByTestId('avatar-photo').locator('img').evaluate((el) => {
          const box = el.getBoundingClientRect()
          const tile = el.closest('[data-atom-avatar]').getBoundingClientRect()
          return [Math.round(box.width), Math.round(box.height), Math.round(tile.width)]
        })
        expect(img[0]).toBeGreaterThanOrEqual(img[2] - 2)
        expect((await style(page.getByTestId('avatar-photo'), 'border-top-width'))['border-top-width']).toBe('1px')
      })

      test('a stack rings each avatar in the surface colour, so the overlap keeps its edge, and counts the rest', async ({ page }) => {
        await openDisplay(page, { tw, section: 'people' })

        const group = page.getByTestId('avatar-group')
        const second = group.locator('[data-atom-avatar]').nth(1)
        const s = await style(second, 'box-shadow', 'margin-left')
        expect(s['box-shadow']).toContain(await token(page, 'surface'))
        expect(s['margin-left']).toBe('-6px')
        await expect(group).toContainText('+2')
      })

      test('a profile names the person in the foreground and the email below in the muted token', async ({ page }) => {
        await openDisplay(page, { tw, section: 'people' })

        const profile = page.getByTestId('profile')
        const name = await style(profile.locator('> div').nth(1).locator('> div').first(), 'font-size', 'font-weight', 'color')
        const email = await style(profile.locator('> div').nth(1).locator('> div').nth(1), 'font-size', 'color')
        expect(name['font-weight']).toBe('510')
        expect(email['font-size']).toBe('12px')
        expect(name.color).not.toBe(email.color)
        expect((await style(page.getByTestId('profile-lg').locator('[data-atom-avatar]'))).width).toBe(48)
      })
    })

    test.describe('progress and placeholders', () => {
      test('a pill track 6px high (4 at sm), filled by the accent ink, or a tone\'s ink, and the figures are tabular', async ({ page }) => {
        await openDisplay(page, { tw, section: 'meters', accent: '#4c55c9' })

        const track = (id) => page.getByTestId(id).locator('[role="progressbar"]')
        const bar = (id) => track(id).locator('> div')

        expect((await style(track('progress-default'), 'border-top-left-radius'))).toMatchObject({ height: 6 })
        expect((await style(track('progress-sm'))).height).toBe(4)
        expect(parseFloat((await style(track('progress-default'), 'border-top-left-radius'))['border-top-left-radius'])).toBeGreaterThanOrEqual(999)

        expect((await style(bar('progress-default'), 'background-color'))['background-color']).toBe(await token(page, 'accent-ink'))
        expect((await style(bar('progress-success'), 'background-color'))['background-color']).toBe(await token(page, 'success-fg'))
        expect((await style(bar('progress-warning'), 'background-color'))['background-color']).toBe(await token(page, 'warning-fg'))
        expect((await style(bar('progress-danger'), 'background-color'))['background-color']).toBe(await token(page, 'danger-fg'))
        expect((await style(bar('progress-info'), 'background-color'))['background-color']).toBe(await token(page, 'info-fg'))

        expect((await style(page.getByTestId('progress-default').locator('span').first(), 'font-variant-numeric'))['font-variant-numeric']).toContain('tabular-nums')
        expect(await track('progress-default').getAttribute('aria-valuenow')).toBe('60')
      })

      test('the bar is as wide as the value, to the pixel of its track', async ({ page }) => {
        await openDisplay(page, { tw, section: 'meters' })

        for (const [id, percent] of [['progress-default', 60], ['progress-full', 100], ['progress-empty', 0]]) {
          const track = page.getByTestId(id).locator('[role="progressbar"]')
          const [bar, whole] = await Promise.all([track.locator('> div').evaluate((el) => el.getBoundingClientRect().width), track.evaluate((el) => el.getBoundingClientRect().width)])
          expect(Math.abs(bar / whole - percent / 100), id).toBeLessThan(0.01)
        }
      })

      test('the indeterminate bar slides, and stands still for a viewer who asked for less motion', async ({ page }) => {
        await openDisplay(page, { tw, section: 'meters' })

        const bar = page.getByTestId('progress-indeterminate').locator('[role="progressbar"] > div')
        expect((await style(bar, 'animation-name'))['animation-name']).toBe('atom-progress-indeterminate')

        await page.emulateMedia({ reducedMotion: 'reduce' })
        expect((await style(bar, 'animation-name'))['animation-name']).toBe('none')
      })

      test('the skeleton pulses for a viewer with no preference and is still under reduced motion', async ({ page }) => {
        await openDisplay(page, { tw, section: 'meters' })

        const skeleton = page.getByTestId('skeleton')
        expect((await style(skeleton, 'animation-name'))['animation-name']).toBe('pulse')

        const bars = await skeleton.locator('> div').evaluateAll((els) => els.map((el) => { const s = getComputedStyle(el); return [el.getBoundingClientRect().height, s.backgroundColor] }))
        expect(bars.map(([height]) => height)).toEqual([8, 8, 8, 8])
        expect(bars[0][1]).toBe(await token(page, 'border-strong'))

        await page.emulateMedia({ reducedMotion: 'reduce' })
        expect((await style(skeleton, 'animation-name'))['animation-name']).toBe('none')
      })

      test('a placeholder bar is a pill on the strong hairline, at the height it was given', async ({ page }) => {
        await openDisplay(page, { tw, section: 'meters' })

        const s = await style(page.getByTestId('placeholder-60'), 'background-color', 'border-top-left-radius')
        expect(s.height).toBe(10)
        expect(s['background-color']).toBe(await token(page, 'border-strong'))
        expect(parseFloat(s['border-top-left-radius'])).toBeGreaterThanOrEqual(999)
      })
    })

    test.describe('pagination', () => {
      const HEIGHT = { compact: 28, comfortable: 32 }

      for (const density of ['compact', 'comfortable']) {
        test(`${density}: every cell is as high as the control token and at least as wide, with a 7px radius`, async ({ page }) => {
          await openDisplay(page, { tw, section: 'pager', density })

          const cells = page.getByTestId('pager-middle').locator('nav button, nav [aria-current], nav [aria-disabled] > span')
          const n = await cells.count()
          expect(n).toBeGreaterThan(8)

          for (let i = 0; i < n; i++) {
            const s = await style(cells.nth(i), 'border-top-left-radius')
            expect([i, s.height, s.width >= HEIGHT[density], s['border-top-left-radius']]).toEqual([i, HEIGHT[density], true, '7px'])
          }
        })
      }

      test.describe('touch (iPad profile)', () => {
        test.use({ ...ipad })

        test('a touch device raises the cells to 40px', async ({ page }) => {
          await openDisplay(page, { tw, section: 'pager' })

          expect(await page.evaluate(() => matchMedia('(pointer: coarse)').matches)).toBe(true)
          expect((await style(page.getByTestId('pager-middle').locator('nav button').first())).height).toBe(40)
          expect((await style(page.getByTestId('pager-middle').locator('[aria-current]'))).height).toBe(40)
        })
      })

      test('the current page is on the neutral fill with a strong hairline and says so to a screen reader; an end is dimmed', async ({ page }) => {
        await openDisplay(page, { tw, section: 'pager' })

        const current = page.getByTestId('pager-middle').locator('[aria-current="page"]')
        await expect(current).toHaveText('5')
        const s = await style(current, 'background-color', 'border-top-width', 'font-variant-numeric')
        expect(s['background-color']).toBe(await token(page, 'neutral-bg'))
        expect(s['border-top-width']).toBe('1px')
        expect(s['font-variant-numeric']).toContain('tabular-nums')

        const first = page.getByTestId('pager-first')
        await expect(first.locator('[aria-disabled="true"] > span')).toHaveCSS('opacity', '0.5')
        await expect(first.locator('[aria-disabled="true"] button')).toHaveCount(0)
        await expect(page.getByTestId('pager-last').locator('[aria-disabled="true"] > span')).toHaveCSS('opacity', '0.5')
      })

      test('the arrows are 16px, and a link tints on hover', async ({ page }) => {
        await openDisplay(page, { tw, section: 'pager' })

        const arrow = await style(page.getByTestId('pager-middle').locator('nav button svg').first())
        expect([arrow.width, arrow.height]).toEqual([16, 16])

        const link = page.getByTestId('pager-middle').getByRole('button', { name: 'Go to page 2' })
        expect((await style(link, 'background-color'))['background-color']).toBe('rgba(0, 0, 0, 0)')
        await link.hover()
        await settle(link)
        expect((await style(link, 'background-color'))['background-color']).not.toBe('rgba(0, 0, 0, 0)')
      })

      test('the keyboard ring is the base 2px outline, offset 1px, and a click draws none', async ({ page }) => {
        await openDisplay(page, { tw, section: 'pager' })

        const link = page.getByTestId('pager-middle').getByRole('button', { name: 'Go to page 2' })
        await focusByKeyboard(page, link)

        const ring = await style(link, 'outline-width', 'outline-style', 'outline-offset')
        expect([ring['outline-width'], ring['outline-style'], ring['outline-offset']]).toEqual(['2px', 'solid', '1px'])
        expect((await link.evaluate(contrastOf, { what: 'outline' })).ratio).toBeGreaterThanOrEqual(GRAPHIC)

        await page.getByTestId('pager-middle').getByRole('button', { name: 'Go to page 3' }).click()
        expect((await style(page.getByTestId('pager-middle').getByRole('button', { name: 'Go to page 3' }), 'outline-style'))['outline-style']).toBe('none')
      })

      test('the summary is 12px and muted; the simple pager has no numbers; the bare pager no summary; the table\'s pager matches', async ({ page }) => {
        await openDisplay(page, { tw, section: 'pager' })

        const summary = page.getByTestId('pager-middle').locator('nav > div').first()
        expect((await style(summary, 'font-size'))['font-size']).toBe('12px')
        await expect(page.getByTestId('pager-simple').locator('nav button')).toHaveCount(2)
        await expect(page.getByTestId('pager-bare').locator('nav > div')).toHaveCount(1)

        const table = page.getByTestId('pager-table')
        expect((await style(table.locator('[aria-current]'))).height).toBe(28)
        expect((await style(table.locator('[aria-current]'), 'background-color'))['background-color']).toBe(await token(page, 'neutral-bg'))
      })

      test('the go-to-page box of the dots is a control: control border, surface, 7px radius', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 })
        await openDisplay(page, { tw, section: 'pager' })

        // a window of 20 pages shows the dots on the middle one
        const dots = page.getByTestId('pager-middle').getByRole('button', { name: 'Has more pages' })
        const count = await dots.count()

        if (count === 0) {
          test.info().annotations.push({ type: 'note', description: 'ten pages show no dots' })
          return
        }

        await dots.first().click()
        const input = page.locator('input[type="number"]:visible').first()
        const s = await style(input, 'border-top-left-radius', 'border-top-width')
        expect([s['border-top-left-radius'], s['border-top-width']]).toEqual(['7px', '1px'])
      })
    })

    test.describe('the table\'s parts', () => {
      test('the row checkbox is the 16px box of the checkbox control, empty or on the accent ink', async ({ page }) => {
        await openDisplay(page, { tw, section: 'table', accent: '#4c55c9' })

        for (const id of ['tcb-off', 'tcb-on']) {
          const s = await style(page.getByTestId(id), 'border-top-left-radius', 'border-top-width', 'box-shadow')
          expect([id, s.width, s.height, s['border-top-left-radius'], s['border-top-width'], s['box-shadow']]).toEqual([id, 16, 16, '4px', '1px', 'none'])
        }

        expect((await style(page.getByTestId('tcb-off'), 'background-color'))['background-color']).toBe(await token(page, 'surface'))
        expect((await style(page.getByTestId('tcb-on'), 'background-color'))['background-color']).toBe(await token(page, 'accent-ink'))
        await expect(page.getByTestId('tcb-off').locator('[data-atom-icon]')).toHaveCSS('opacity', '0')
        await expect(page.getByTestId('tcb-on').locator('[data-atom-icon]')).toHaveCSS('opacity', '1')
      })

      test('a filter chip is a 26px pill on the neutral tone, and "clear" removes it', async ({ page }) => {
        await openDisplay(page, { tw, section: 'table' })

        await page.evaluate(() => window.dispatchEvent(new CustomEvent('table-filter:set', { detail: { key: 'state', label: 'State', display: 'Paid' } })))

        const chip = page.locator('[data-atom-table-filter-chip]')
        await expect(chip).toHaveCount(1)
        const s = await style(chip, 'background-color', 'border-top-left-radius', 'font-size')
        expect([s.height, s['font-size']]).toEqual([26, '12px'])
        expect(s['background-color']).toBe(await token(page, 'neutral-bg'))
        expect(parseFloat(s['border-top-left-radius'])).toBeGreaterThanOrEqual(999)
        expect((await style(chip.locator('button [data-atom-icon]'))).width).toBe(14)
      })

      test('the overflow card of the filters is a panel: surface, hairline, 10px radius', async ({ page }) => {
        await openDisplay(page, { tw, section: 'table' })

        await page.getByRole('button', { name: 'More filters' }).click()

        const card = page.getByTestId('filters').locator('> div').nth(1)
        await expect(card).toBeVisible()
        const s = await style(card, 'background-color', 'border-top-width', 'border-top-left-radius')
        expect([s['border-top-width'], s['border-top-left-radius']]).toEqual(['1px', '10px'])
        expect(s['background-color']).toBe(await token(page, 'surface'))
      })

      test('the table: badges sit in a 40px row, the skeleton bars are the strong hairline and pulse', async ({ page }) => {
        await openDisplay(page, { tw, section: 'table' })

        expect((await style(page.getByTestId('trow-1').locator('td').first())).height).toBeGreaterThanOrEqual(40)
        expect((await style(page.getByTestId('trow-1').locator('[data-atom-badge]'))).height).toBe(18)

        const skeleton = page.locator('[data-atom-table-skeleton]')
        expect((await style(skeleton, 'animation-name'))['animation-name']).toBe('pulse')
        expect((await style(skeleton.locator('[style*="width"]').first(), 'background-color'))['background-color']).toBe(await token(page, 'border-strong'))
      })
    })

    for (const dark of [false, true]) {
      for (const accent of ACCENTS) {
        test(`every text role holds 4.5:1 on its own surface, ${dark ? 'dark' : 'light'}, accent ${accent}`, async ({ page }) => {
          await openDisplay(page, { tw, dark, accent, section: 'panels' })

          // cards: the figure, the indicator, the card's own text; on the surface and on the sunken fill
          await gate(page.getByTestId('card').locator('p'), TEXT, 'text', 'card text')
          await gate(page.getByTestId('card-subtle').locator('p'), TEXT, 'text', 'subtle card text')
          await gate(page.getByTestId('card-stats-up').locator('[class*="text-2xl"]'), TEXT, 'text', 'stats figure')
          await gate(page.getByTestId('card-stats-up').locator('[class*="text-success-fg"]'), TEXT, 'text', 'stats up')
          await gate(page.getByTestId('card-stats-down').locator('[class*="text-danger-fg"]'), TEXT, 'text', 'stats down')

          // callouts: the body, the heading and the icon (3:1), each against its own tint
          for (const variant of ['info', 'success', 'warning', 'danger', 'default']) {
            const root = page.getByTestId(`callout-${variant}`)
            await gate(root.locator('.flex-col > div').last(), TEXT, 'text', `${variant} callout body`)
            await gate(root.locator('[data-atom-callout-heading]'), TEXT, 'text', `${variant} callout heading`)
            await gate(root.locator('[data-atom-icon]').first(), GRAPHIC, 'text', `${variant} callout icon`)
          }

          await gate(page.getByTestId('callout-closeable').getByRole('button', { name: 'Dismiss' }), TEXT, 'text', 'dismiss icon')
          await gate(page.getByTestId('callout-slot-heading'), TEXT, 'text', 'slot heading')

          // the hairline round a callout is the tone's ink at 20%: present, not the text colour
          const border = await page.getByTestId('callout-danger').evaluate(contrastOf, { what: 'border' })
          expect(border.ratio).toBeGreaterThan(1.1)
        })

        test(`dd, list, kbd, avatar, profile, progress hold their contrast, ${dark ? 'dark' : 'light'}, accent ${accent}`, async ({ page }) => {
          await openDisplay(page, { tw, dark, accent, section: 'lists' })

          for (const id of ['dd-email', 'dd-phone', 'dd-long']) {
            await gate(page.getByTestId(id).locator('dt'), TEXT, 'text', `${id} label`)
            await gate(page.getByTestId(id).locator('dd'), TEXT, 'text', `${id} value`)
          }

          await gate(page.getByTestId('dd-block').locator('dt'), TEXT, 'text', 'label on the subtle card')
          await gate(page.getByTestId('dd-block').locator('dd'), TEXT, 'text', 'value on the subtle card')
          await gate(page.locator('[data-atom-list] > div').first(), TEXT, 'text', 'list heading')
          await gate(page.getByTestId('list-item-plain'), TEXT, 'text', 'list item')
          await gate(page.getByRole('button', { name: 'Remove' }).locator('[data-atom-icon]'), TEXT, 'text', 'remove icon')

          for (const key of await page.getByTestId('kbd-combo').locator('kbd').all()) await gate(key, TEXT, 'text', 'kbd on the surface')
          for (const key of await page.getByTestId('kbd-on-sunken').locator('kbd').all()) await gate(key, TEXT, 'text', 'kbd on the sunken fill')
          expect((await page.getByTestId('kbd-single').evaluate(contrastOf, { what: 'border' })).ratio, 'kbd edge').toBeGreaterThan(1.2)

          await openDisplay(page, { tw, dark, accent, section: 'people' })

          for (const id of ['avatar-xs', 'avatar-sm', 'avatar-default', 'avatar-lg', 'avatar-xl', 'avatar-round-default']) {
            await gate(page.getByTestId(id), TEXT, 'text', `${id} initials`)
          }

          await gate(page.getByTestId('profile').locator('> div').nth(1).locator('> div').first(), TEXT, 'text', 'profile name')
          await gate(page.getByTestId('profile').locator('> div').nth(1).locator('> div').nth(1), TEXT, 'text', 'profile email')
          await gate(page.getByTestId('avatar-group-sunken').locator('[data-atom-avatar]').first(), TEXT, 'text', 'avatar on the sunken fill')

          await openDisplay(page, { tw, dark, accent, section: 'meters' })

          for (const id of ['progress-default', 'progress-sm', 'progress-success', 'progress-warning', 'progress-danger', 'progress-info', 'progress-full', 'progress-empty']) {
            await gate(page.getByTestId(id).locator('span').first(), TEXT, 'text', `${id} label`)

            // the fill is told from its track at 3:1 (not text, but the one thing the bar says)
            if (id !== 'progress-empty') {
              await gate(page.getByTestId(id).locator('[role="progressbar"] > div'), GRAPHIC, 'fill', `${id} fill on its track`)
            }
          }

          await gate(page.getByTestId('progress-on-sunken').locator('span').first(), TEXT, 'text', 'progress label on the sunken fill')
          await gate(page.getByTestId('progress-on-sunken').locator('[role="progressbar"] > div'), GRAPHIC, 'fill', 'progress fill on the sunken fill')
        })

        test(`pagination and the table's parts hold their contrast, ${dark ? 'dark' : 'light'}, accent ${accent}`, async ({ page }) => {
          await openDisplay(page, { tw, dark, accent, section: 'pager' })

          for (const id of ['pager-middle', 'pager-first', 'pager-last', 'pager-simple', 'pager-table']) {
            const pager = page.getByTestId(id)

            for (const link of await pager.getByRole('button', { name: /^Go to page/ }).all()) await gate(link, TEXT, 'text', `${id} page link`)
            for (const icon of await pager.locator('nav button svg').all()) await gate(icon, TEXT, 'text', `${id} arrow`)
            // the simple pager has no numbers, so no current page
            if (id !== 'pager-simple') await gate(pager.locator('[aria-current="page"]'), TEXT, 'text', `${id} current page`)

            // a disabled end carries no information a user needs, but it is still found: 3:1
            for (const off of await pager.locator('[aria-disabled="true"] > span').all()) {
              const found = await off.evaluate(contrastOf, { what: 'text' })
              expect(found.ratio, `${id} disabled end (dimmed by opacity: measured at full strength)`).toBeGreaterThanOrEqual(GRAPHIC)
            }
          }

          await gate(page.getByTestId('pager-middle').locator('nav > div').first(), TEXT, 'text', 'summary')

          // a hovered link: text on the tint
          const link = page.getByTestId('pager-middle').getByRole('button', { name: 'Go to page 2' })
          await link.hover()
          await link.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished.catch(() => null))))
          await gate(link, TEXT, 'text', 'hovered page link')

          await openDisplay(page, { tw, dark, accent, section: 'table' })

          // the checked box: its fill against the surface, its mark against its fill (both are shapes, 3:1)
          await gate(page.getByTestId('tcb-on'), GRAPHIC, 'fill', 'checked box on the surface')
          await gate(page.getByTestId('tcb-on').locator('[data-atom-icon]'), GRAPHIC, 'text', 'check mark on the fill')
          await gate(page.getByTestId('tcb-off'), GRAPHIC, 'border', 'empty box edge')

          // the bulk bar's words: a muted count and clear link, and the select-all link in the accent ink
          for (const id of ['bulk-count', 'bulk-link', 'bulk-clear']) await gate(page.getByTestId(id), TEXT, 'text', id)

          await page.evaluate(() => window.dispatchEvent(new CustomEvent('table-filter:set', { detail: { key: 'state', label: 'State', display: 'Paid' } })))
          const chip = page.locator('[data-atom-table-filter-chip]')
          await gate(chip, TEXT, 'text', 'chip value')
          await gate(chip.locator('span').first(), TEXT, 'text', 'chip name')
          await gate(chip.locator('button'), TEXT, 'text', 'chip remove')

          for (const row of ['trow-1', 'trow-2']) {
            await gate(page.getByTestId(row).locator('[data-atom-badge]'), TEXT, 'text', `${row} badge`)
            await gate(page.getByTestId(row).locator('td').first(), TEXT, 'text', `${row} cell`)
          }

          await gate(page.locator('[data-atom-table-columns] th').first().locator('div').first(), TEXT, 'text', 'column header')
        })
      }
    }
  })
}
