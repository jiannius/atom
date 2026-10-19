import { test, expect } from '@playwright/test'
import { openDisplay, ratio, style, gate, token, contrastOf, ACCENTS, TEXT, TONE_NAMES, HEXES } from './display-helpers.js'

// V4-3b, the badge: a borderless pill on the tone tokens at 4.5:1 or more, in both themes and for three accents; a colour name a
// host passes maps to a tone (amber and indigo no longer fall back to grey); a hex is derived from the hex; <atom:status> stays the
// dot plus the word. Measured on the consumer's Tailwind (`node tests/consumer/build.mjs`), floor and latest.

for (const tw of ['floor', 'latest']) {
  test.describe(`badge, ${tw} consumer build`, () => {
    test('a borderless pill: no border, no shadow, a 999px radius, weight 510, the control token\'s text', async ({ page }) => {
      await openDisplay(page, { tw, section: 'badges' })

      const s = await style(page.getByTestId('badge-size-default'), 'border-top-width', 'box-shadow', 'border-top-left-radius', 'font-weight', 'font-size', 'font-family', 'white-space')
      expect(s['border-top-width']).toBe('0px')
      expect(s['box-shadow']).toBe('none')
      expect(parseFloat(s['border-top-left-radius'])).toBeGreaterThanOrEqual(999)
      expect(s['font-weight']).toBe('510')
      expect(s['font-size']).toBe('12px')
      expect(s['font-family']).toContain('Geist')
      expect(s['white-space']).toBe('nowrap')
    })

    test('three sizes: 18, 22 and 26px high, and the comfortable density reads 13px', async ({ page }) => {
      await openDisplay(page, { tw, section: 'badges' })

      expect((await style(page.getByTestId('badge-size-xs'))).height).toBe(18)
      expect((await style(page.getByTestId('badge-size-sm'))).height).toBe(22)
      expect((await style(page.getByTestId('badge-size-default'))).height).toBe(22)
      expect((await style(page.getByTestId('badge-size-lg'))).height).toBe(26)
      expect((await style(page.getByTestId('badge-size-lg'), 'font-size'))['font-size']).toBe('13px')

      await openDisplay(page, { tw, section: 'badges', density: 'comfortable' })

      expect((await style(page.getByTestId('badge-size-default'), 'font-size'))['font-size']).toBe('13px')
    })

    test('every colour name draws the pair of the tone it maps to', async ({ page }) => {
      await openDisplay(page, { tw, section: 'badges' })

      const bg = async (id) => (await style(page.getByTestId(id), 'background-color', 'color'))
      const canonical = { neutral: 'badge-neutral', info: 'badge-info', success: 'badge-success', warning: 'badge-warning', danger: 'badge-danger', accent: 'badge-accent', inverse: 'badge-black' }

      for (const [tone, names] of Object.entries(TONE_NAMES)) {
        const expected = await bg(canonical[tone])

        for (const name of names) {
          const got = await bg(`badge-${name}`)
          expect([name, got['background-color'], got.color]).toEqual([name, expected['background-color'], expected.color])
        }
      }

      // and the tones are different from each other, so the table is not a no-op
      const fills = await Promise.all(Object.values(canonical).map(async (id) => (await bg(id))['background-color']))
      expect(new Set(fills).size).toBe(7)
    })

    test('amber and indigo are no longer the grey fallback', async ({ page }) => {
      await openDisplay(page, { tw, section: 'badges' })

      const grey = (await style(page.getByTestId('badge-gray'), 'background-color'))['background-color']

      for (const name of ['amber', 'indigo', 'purple', 'orange', 'teal', 'rose']) {
        expect((await style(page.getByTestId(`badge-${name}`), 'background-color'))['background-color'], name).not.toBe(grey)
      }
    })

    test('the tones are the role tokens: a badge and a status of one tone share their fill and ink', async ({ page }) => {
      await openDisplay(page, { tw, section: 'badges' })

      const badge = await style(page.getByTestId('vs-badge'), 'background-color', 'color')
      const status = await style(page.getByTestId('vs-status'), 'background-color', 'color')
      expect(badge['background-color']).toBe(status['background-color'])
      expect(badge.color).toBe(status.color)
      expect(badge['background-color']).toBe(await token(page, 'success-bg'))
    })

    test('a status stays a dot and a word; a badge is neither a dot nor a status', async ({ page }) => {
      await openDisplay(page, { tw, section: 'badges' })

      await expect(page.getByTestId('vs-status').locator('[data-atom-status-dot]')).toHaveCount(1)
      await expect(page.getByTestId('vs-badge').locator('[data-atom-status-dot]')).toHaveCount(0)
      expect(await page.getByTestId('vs-badge').evaluate((el) => el.hasAttribute('data-atom-status'))).toBe(false)
    })

    test('the nav badge reads the same names, at nav size', async ({ page }) => {
      await openDisplay(page, { tw, section: 'badges' })

      const fill = async (id) => (await style(page.getByTestId(id), 'background-color'))['background-color']

      expect(await fill('navbadge-amber')).toBe(await token(page, 'warning-bg'))
      expect(await fill('navbadge-indigo')).toBe(await token(page, 'info-bg'))
      expect(await fill('navbadge-red')).toBe(await token(page, 'danger-bg'))
      expect(await fill('navbadge-gray')).toBe(await token(page, 'neutral-bg'))
      expect((await style(page.getByTestId('navbadge-amber'), 'font-variant-numeric'))['font-variant-numeric']).toContain('tabular-nums')
    })

    test('a hex badge keeps the pill, and its tint and ink follow the hex', async ({ page }) => {
      await openDisplay(page, { tw, section: 'badges' })

      for (const hex of HEXES) {
        const s = await style(page.getByTestId(`badge-hex-${hex}`), 'border-top-width', 'box-shadow', 'background-color', 'color')
        expect([hex, s['border-top-width'], s['box-shadow']]).toEqual([hex, '0px', 'none'])
      }

      // two hexes, two tints
      const red = (await style(page.getByTestId('badge-hex-ff0000'), 'background-color'))['background-color']
      const blue = (await style(page.getByTestId('badge-hex-0000ff'), 'background-color'))['background-color']
      expect(red).not.toBe(blue)
    })

    for (const dark of [false, true]) {
      for (const accent of ACCENTS) {
        test(`every badge holds 4.5:1, ${dark ? 'dark' : 'light'}, accent ${accent}`, async ({ page }) => {
          await openDisplay(page, { tw, section: 'badges', dark, accent })

          const names = Object.values(TONE_NAMES).flat()

          for (const name of names) {
            await gate(page.getByTestId(`badge-${name}`), TEXT, 'text', `${name} badge`)
          }

          for (const size of ['xs', 'sm', 'default', 'lg']) {
            await gate(page.getByTestId(`badge-size-${size}`), TEXT, 'text', `${size} badge`)
          }

          // every hex a host could pass: the ink is read from the hex but mixed into the foreground, so it holds on its own tint
          for (const hex of HEXES) {
            await gate(page.getByTestId(`badge-hex-${hex}`), TEXT, 'text', `#${hex} badge`)
          }

          await gate(page.getByTestId('badge-from-status'), TEXT, 'text', 'badge from a status')

          // on a sunken surface too, and the count in the nav
          for (const tone of ['neutral', 'info', 'success', 'warning', 'danger', 'accent', 'black']) {
            await gate(page.getByTestId(`sunken-${tone}`), TEXT, 'text', `${tone} on sunken`)
          }

          for (const name of ['gray', 'amber', 'indigo', 'red', 'green', 'purple', 'black']) {
            await gate(page.getByTestId(`navbadge-${name}`), TEXT, 'text', `${name} nav badge`)
          }
        })
      }
    }

    for (const dark of [false, true]) {
      test(`any hex a host could pass holds 4.5:1 on its own tint, ${dark ? 'dark' : 'light'}: 216 colours across the cube`, async ({ page }) => {
        await openDisplay(page, { tw, section: 'badges', dark })

        const levels = ['00', '33', '66', '99', 'cc', 'ff']
        const hexes = levels.flatMap((r) => levels.flatMap((g) => levels.map((b) => `#${r}${g}${b}`)))

        // clone a rendered hex badge and swap its colour into the style it was given, so the recipe measured is the server's
        await page.evaluate((hexes) => {
          const source = document.querySelector('[data-testid="badge-hex-9f5afd"]')
          const host = document.createElement('div')
          host.setAttribute('data-testid', 'sweep')
          for (const hex of hexes) {
            const badge = source.cloneNode(true)
            badge.removeAttribute('data-testid')
            badge.setAttribute('style', source.getAttribute('style').replaceAll('#9f5afd', hex))
            host.append(badge)
          }
          source.parentElement.after(host)
        }, hexes)

        const results = await page.getByTestId('sweep').locator('[data-atom-badge]').evaluateAll(
          (badges, src) => {
            const measure = new Function(`return ${src}`)()
            return badges.map((badge) => measure(badge, { what: 'text' }).ratio)
          },
          contrastOf.toString(),
        )

        expect(results).toHaveLength(216)

        const low = results.map((ratio, i) => [hexes[i], ratio]).filter(([, ratio]) => ratio < TEXT)
        expect(low, `${low.length} of 216 under 4.5:1`).toEqual([])
      })
    }

    test('a group with max 2 over three badges shows two of them and +1', async ({ page }) => {
      await openDisplay(page, { tw, section: 'badges' })

      const group = page.getByTestId('badge-group')
      await expect(group.locator('[data-atom-badge]')).toHaveCount(3)
      await expect(group.locator('[data-atom-badge]:visible')).toHaveCount(2)
      await expect(group.locator('[data-atom-badge]:visible')).toHaveText(['One', 'Two'])
      await expect(group.locator('[data-atom-badge-overflow]')).toHaveText('+1')
      await expect(group.locator('[data-atom-badge]').nth(2)).toBeHidden()
    })

    test('the +N of a badge group is muted text at 4.5:1', async ({ page }) => {
      for (const dark of [false, true]) {
        await openDisplay(page, { tw, section: 'badges', dark })

        const more = page.getByTestId('badge-group').locator('[data-atom-badge-overflow] div').first()
        expect(await ratio(more), `dark ${dark}`).toBeGreaterThanOrEqual(TEXT)
      }
    })
  })
}
