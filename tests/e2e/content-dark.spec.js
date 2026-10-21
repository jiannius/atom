import { test, expect } from '@playwright/test'
import { openContent, gate, sweepText, contrastOf, settled, chartReady, ACCENTS, TEXT, GRAPHIC } from './content-helpers.js'
import { openLook, measureSweep } from './calendar-look.js'

// V4-3d, the dark check. Scoped to the 3d components, and the variants of them, that the dark-mode hosts render (found by scanning
// their <atom:...> tags; the apps themselves are not named here). Every text role is measured against ITS OWN surface (every
// ancestor's fill composited, not the page), at 4.5:1 for text and 3:1 for the icons, lines and series that carry meaning, for three
// accents, in dark AND light, on the consumer's Tailwind, floor and latest.
//
// The dark-mode hosts render: heading, subheading, caption, label and error (in form fields), link, separator, copy, sharer, embed and
// whatsapp, the editor (field, toolbar, content) and the chat composer, and the calendar (month and week, items of every colour, the
// day list). Not rendered by them, and measured all the same: the chart (bar, area and the stats card's sparkline).

const THEMES = [['dark', true], ['light', false]]

for (const tw of ['floor', 'latest']) {
  for (const [theme, dark] of THEMES) {
    test.describe(`text, editor and chart, ${theme}, ${tw} consumer build`, () => {
      for (const accent of ACCENTS) {
        test(`text: every heading, caption, label, error, separator and the small parts, on their own surface, accent ${accent}`, async ({ page }) => {
          await openContent(page, { tw, dark, accent, section: 'text' })

          expect(await sweepText(page, '[data-testid="text"]', TEXT, 20)).toEqual([])

          // the glyphs that carry meaning: the label's icon, the share buttons' and the copy icon, at 3:1
          await gate(page.getByTestId('label-icon').locator('[data-atom-icon]'), GRAPHIC, 'text', 'label icon')
          for (const icon of await page.getByTestId('sharer').locator('[data-atom-icon]').all()) await gate(icon, GRAPHIC, 'text', 'share icon')
          await gate(page.getByTestId('copy').locator('[data-atom-icon]'), GRAPHIC, 'text', 'copy icon')
          await gate(page.getByTestId('embed').locator('[data-atom-icon]'), GRAPHIC, 'text', 'embed icon')
          await gate(page.getByTestId('separator').locator('> div'), 1.1, 'fill', 'separator rule is told from the canvas')

          // hover tint under the pointer: the share button keeps its text colour readable
          const first = page.getByTestId('sharer').locator('button[data-sharer]').first()
          await first.hover()
          await gate(first.locator('[data-atom-icon]'), GRAPHIC, 'text', 'share icon on hover')
        })

        test(`links: every link on its own surface, on the canvas, in a card and in a callout of each tone, accent ${accent}`, async ({ page }) => {
          await openContent(page, { tw, dark, accent, section: 'links' })

          const ids = ['link', 'link-accent', 'link-icon', 'card-link-a', 'card-link-subtle-a', ...['info', 'success', 'warning', 'danger', 'neutral'].map((tone) => `callout-link-${tone}`)]

          for (const id of ids) await gate(page.getByTestId(id), TEXT, 'text', id)
          expect(await sweepText(page, '[data-testid="links"]', TEXT, 20)).toEqual([])
        })

        test(`editor content: the text, the quote, the table, the code, a link and a mention, on their own surface, accent ${accent}`, async ({ page }) => {
          await openContent(page, { tw, dark, accent, section: 'editor' })

          expect(await sweepText(page, '[data-testid="content"]', TEXT, 15)).toEqual([])
        })

        test(`editor: the placeholder, the toolbar glyphs, the field frame and the chat composer, accent ${accent}`, async ({ page }) => {
          await openContent(page, { tw, dark, accent, section: 'editor' })

          const frame = page.getByTestId('tiptap').locator('.editor').first()
          await expect(frame).toBeVisible({ timeout: 15000 })

          // the field's boundary: 3:1 in dark, where the control border is a solid grey; in light it is the design's hairline (about 1.4:1,
          // as every field's is, see --atom-control-border), so there it is told from the canvas by that and its surface step. The
          // placeholder is read at 4.5:1 below.
          const boundary = dark ? GRAPHIC : 1.3
          await gate(frame, boundary, 'border', 'editor frame')
          await gate(page.getByTestId('chat').locator('.editor').first(), boundary, 'border', 'chat frame')

          const placeholder = page.getByTestId('tiptap').locator('p.is-editor-empty')
          const found = await placeholder.evaluate(contrastOf, { what: 'text', pseudo: '::before' })
          expect(found.ratio, `placeholder ${found.colour} on ${found.back}`).toBeGreaterThanOrEqual(TEXT)

          for (const button of await page.getByTestId('tiptap').getByRole('toolbar').locator('button:visible').all()) await gate(button.locator('svg').first(), GRAPHIC, 'text', 'toolbar glyph')

          expect(await sweepText(page, '[data-testid="editor"] [role="toolbar"], [data-testid="editor"] label', TEXT, 1)).toEqual([])
        })

        test(`chart: the series, the line and the labels against the surface behind them, accent ${accent}`, async ({ page }) => {
          await openContent(page, { tw, dark, accent, section: 'chart' })

          for (const id of ['chart-bar', 'chart-bar-default']) {
            const { root } = await chartReady(page, id)

            for (const bar of await root.locator('.apexcharts-bar-area').all()) await gate(bar, GRAPHIC, 'svg-fill', `${id} bar`)
            await gate(root.locator('.apexcharts-xaxis-label').first(), TEXT, 'svg-fill', `${id} label`)
            // the fixture has four points, and one bar is drawn for each
            expect(await root.locator('.apexcharts-bar-area').count()).toBe(4)
          }

          const area = await chartReady(page, 'chart-area', 'area')
          // the line is the area's path that is stroked (the point markers, size 0, are drawn with a white stroke and are not what is read)
          const lines = await area.root.locator('.apexcharts-series path.apexcharts-area').evaluateAll((els) => els.filter((el) => getComputedStyle(el).stroke !== 'none').length)
          expect(lines).toBeGreaterThan(0)
          for (const path of await area.root.locator('.apexcharts-series path.apexcharts-area').all()) {
            if (await path.evaluate((el) => getComputedStyle(el).stroke !== 'none')) await gate(path, GRAPHIC, 'svg-stroke', 'area line')
          }

          // the stats cards: the sparkline sits on the card's own surface, the indicator is text
          for (const id of ['stat-up', 'stat-down', 'stat-flat']) {
            const card = page.getByTestId(id)
            await expect(card.locator('.apexcharts-canvas')).toBeVisible({ timeout: 15000 })
            await expect.poll(() => card.locator('.apexcharts-series path.apexcharts-area').evaluateAll((els) => els.some((el) => getComputedStyle(el).stroke !== 'none')), id).toBe(true)

            for (const path of await card.locator('.apexcharts-series path.apexcharts-area').all()) {
              if (await path.evaluate((el) => getComputedStyle(el).stroke !== 'none')) await gate(path, GRAPHIC, 'svg-stroke', `${id} sparkline`)
            }
          }

          expect(await sweepText(page, '[data-testid="stat-up"], [data-testid="stat-down"], [data-testid="stat-flat"]', TEXT, 6)).toEqual([])
        })

        test(`calendar: every text and every cue against its own ground, in the states that draw the accent, accent ${accent}`, async ({ page }) => {
          test.setTimeout(90000)
          await page.setViewportSize({ width: 1280, height: 900 })

          for (const view of ['month', 'week']) {
            await openLook(page, { view, theme, tw, accent })

            for (const state of ['none', 'select', 'pending']) {
              await page.evaluate((s) => window.atomLook.apply(s), state)
              await page.waitForTimeout(150)

              const sweep = await measureSweep(page)
              const low = (rows, min) => rows.filter((row) => row.ratio < min).map((row) => `${view} ${state}: ${row.ratio.toFixed(2)} ${row.name} ${row.fg} on ${row.bg} "${row.example}"`)

              expect(sweep.text.length + sweep.graphics.length, `${view} ${state}: something was measured`).toBeGreaterThan(30)
              expect(low(sweep.text, TEXT)).toEqual([])
              expect(low(sweep.graphics, GRAPHIC)).toEqual([])
            }
          }
        })
      }
    })
  }
}

// the docs chrome, in both themes and three accents, floor and latest: the pages a host's Claude reads. Local-only routes; the stylesheet is
// the consumer build added after load, as the calendar look sheet does it.
for (const tw of ['floor', 'latest']) {
  for (const [theme, dark] of THEMES) {
    for (const accent of ACCENTS) {
      for (const path of ['/atom/docs', '/atom/docs/button', '/atom/docs/link']) {
        test(`docs ${path}, ${theme}, ${tw}, accent ${accent}: the chrome, the examples and their source, on their own surface`, async ({ page }) => {
          await page.emulateMedia({ colorScheme: dark ? 'dark' : 'light' })
          await page.goto(path)
          await page.addStyleTag({ url: `/atom/e2e/consumer-css/${tw}` })
          await page.addStyleTag({ content: `:root { --color-accent: ${accent}; }` })
          await page.evaluate((d) => document.documentElement.classList.toggle('dark', d), dark)
          await page.evaluate(() => document.fonts.ready)
          // the theme and the accent were set after load, so the colours transition: read them at their end, not in the middle of the fade
          await settled(page)

          expect(await sweepText(page, 'body', TEXT, 15)).toEqual([])
        })
      }
    }
  }
}
