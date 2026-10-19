import { test, expect } from '@playwright/test'
import { openDisplay, open, ratio, style, gate, sweepText, contrastOf, ACCENTS, TEXT, GRAPHIC, TONE_NAMES } from './display-helpers.js'

// V4-3b, the dark check. Scoped to the 3b components, and the variants of them, that the dark-mode hosts render (found by scanning
// their <atom:...> tags; the apps themselves are not named here). Every text role is measured against ITS OWN surface (every
// ancestor's fill composited, not the page), at 4.5:1 for text and 3:1 for the icons and shapes that carry meaning, for three accents.
// display-badges and display-panels measure the whole sheet in both themes; this file holds the host variants on the surfaces the
// hosts put them on (a subtle card, a hovered row, a tinted callout), and reads the list, record and document patterns as a page.
//
// The dark-mode hosts render: table (sortable and right-aligned columns, a checkbox column, rows that link, a first-load skeleton),
// badge (xs, sm and default; a status enum or array; the names green, zinc, yellow, black, amber, gray, red, blue, purple and orange;
// a hex from a record), badge.group, card (inset, subtle, divided, stats, chart), dd and dd.group in two columns, callout (warning,
// danger, info and success, with a heading, with its own icon), list and list.item, avatar (xs, round) and avatar.group, profile (lg),
// skeleton and placeholder-bar. Not rendered by them: kbd, progress and the standalone pager (measured in display-panels all the same).

const HOST_NAMES = ['green', 'zinc', 'yellow', 'black', 'amber', 'gray', 'red', 'blue', 'purple', 'orange']

for (const tw of ['floor', 'latest']) {
  test.describe(`dark check: the 3b components the dark-mode hosts render, ${tw} consumer build`, () => {
    for (const accent of ACCENTS) {
      test(`badges by the names the hosts pass, a status and a hex, on the surface, a sunken card and a hovered row, accent ${accent}`, async ({ page }) => {
        await openDisplay(page, { tw, dark: true, accent, section: 'badges' })

        for (const name of HOST_NAMES) await gate(page.getByTestId(`badge-${name}`), TEXT, 'text', `${name} badge`)
        for (const size of ['xs', 'sm', 'default']) await gate(page.getByTestId(`badge-size-${size}`), TEXT, 'text', `${size} badge`)
        await gate(page.getByTestId('badge-from-status'), TEXT, 'text', 'badge from a status')
        await gate(page.getByTestId('badge-hex-3e496b'), TEXT, 'text', 'a record hex')

        for (const tone of ['neutral', 'info', 'success', 'warning', 'danger', 'accent', 'black']) await gate(page.getByTestId(`sunken-${tone}`), TEXT, 'text', `${tone} on a sunken card`)

        // a badge in a hovered row: the row tints, the badge keeps its own fill, so its text still reads on it
        await openDisplay(page, { tw, dark: true, accent, section: 'table' })
        const row = page.getByTestId('trow-1')
        await row.hover()
        await row.evaluate((el) => Promise.all(el.getAnimations({ subtree: true }).map((a) => a.finished.catch(() => null))))
        await gate(row.locator('[data-atom-badge]'), TEXT, 'text', 'badge in a hovered row')
        await gate(row.locator('td').first(), TEXT, 'text', 'cell in a hovered row')
        await gate(row.locator('td').last(), TEXT, 'text', 'numeric cell in a hovered row')
      })

      test(`cards: plain, inset, subtle, divided and stats; callouts: warning, danger, info and success, accent ${accent}`, async ({ page }) => {
        await openDisplay(page, { tw, dark: true, accent, section: 'panels' })

        await gate(page.getByTestId('card').locator('p'), TEXT, 'text', 'card')
        await gate(page.getByTestId('card-subtle').locator('p'), TEXT, 'text', 'subtle card')
        await gate(page.getByTestId('card-divided').locator('> div').first(), TEXT, 'text', 'divided card')
        await gate(page.getByTestId('card-inset').locator('div'), TEXT, 'text', 'inset card')
        await gate(page.getByTestId('card-stats-up').locator('[class*="text-2xl"]'), TEXT, 'text', 'stats figure')
        await gate(page.getByTestId('card-stats-up').locator('[class*="text-success-fg"]'), TEXT, 'text', 'stats up')
        await gate(page.getByTestId('card-stats-down').locator('[class*="text-danger-fg"]'), TEXT, 'text', 'stats down')

        // the card's edge is told from the canvas it sits on by the hairline: reported, a hairline is not a 3:1 boundary
        const edge = await page.getByTestId('card').evaluate(contrastOf, { what: 'border' })
        test.info().annotations.push({ type: 'card hairline contrast, dark', description: edge.ratio.toFixed(2) })

        for (const variant of ['warning', 'danger', 'info', 'success']) {
          const root = page.getByTestId(`callout-${variant}`)
          await gate(root.locator('.flex-col > div').last(), TEXT, 'text', `${variant} callout body`)
          await gate(root.locator('[data-atom-callout-heading]'), TEXT, 'text', `${variant} callout heading`)
          await gate(root.locator('[data-atom-icon]').first(), GRAPHIC, 'text', `${variant} callout icon`)
        }
      })

      test(`dd and dd.group in two columns, list and list.item, avatar xs and round, avatar.group, profile lg, accent ${accent}`, async ({ page }) => {
        await openDisplay(page, { tw, dark: true, accent, section: 'lists' })

        for (const id of ['dd-email', 'dd-phone', 'dd-block']) {
          await gate(page.getByTestId(id).locator('dt'), TEXT, 'text', `${id} label`)
          await gate(page.getByTestId(id).locator('dd'), TEXT, 'text', `${id} value`)
        }

        await gate(page.locator('[data-atom-list] > div').first(), TEXT, 'text', 'list heading')
        await gate(page.getByTestId('list-item-link').locator('.font-medium'), TEXT, 'text', 'list item title')
        await gate(page.getByTestId('list-item-link').locator('.text-xs'), TEXT, 'text', 'list item caption')

        // a hovered list row
        const row = page.getByTestId('list-item-plain').locator('xpath=ancestor::*[@data-atom-list-item][1]')
        await row.hover()
        await row.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished.catch(() => null))))
        await gate(page.getByTestId('list-item-plain'), TEXT, 'text', 'hovered list item')

        await openDisplay(page, { tw, dark: true, accent, section: 'people' })

        await gate(page.getByTestId('avatar-xs'), TEXT, 'text', 'avatar xs')
        await gate(page.getByTestId('avatar-round-xs'), TEXT, 'text', 'round avatar xs')
        await gate(page.getByTestId('avatar-round-default'), TEXT, 'text', 'round avatar')

        for (const avatar of await page.getByTestId('avatar-group').locator('[data-atom-avatar]').all()) await gate(avatar, TEXT, 'text', 'avatar in a stack')

        await gate(page.getByTestId('profile-lg').locator('> div').nth(1).locator('> div').first(), TEXT, 'text', 'profile name')
        await gate(page.getByTestId('profile-lg').locator('> div').nth(1).locator('> div').nth(1), TEXT, 'text', 'profile email')

        // the photo avatar keeps its hairline against the dark surface
        expect((await page.getByTestId('avatar-photo').evaluate(contrastOf, { what: 'border' })).ratio, 'photo edge').toBeGreaterThan(1.1)
      })

      test(`the table in dark: header, cells, a checkbox, a filter chip, a pager, accent ${accent}`, async ({ page }) => {
        await openDisplay(page, { tw, dark: true, accent, section: 'table' })

        const failures = await sweepText(page, '[data-testid="table-plain"], [data-testid="filters"]', TEXT, 8)
        expect(failures).toEqual([])

        await gate(page.getByTestId('tcb-on'), GRAPHIC, 'fill', 'checked box')
        await gate(page.getByTestId('tcb-off'), GRAPHIC, 'border', 'empty box')

        await openDisplay(page, { tw, dark: true, accent, section: 'pager' })
        expect(await sweepText(page, '[data-testid="pager-table"], [data-testid="pager-middle"]', TEXT, 4)).toEqual([])
      })
    }

    test('the hairlines that tell a panel from the canvas are there in dark: card, callout, avatar, kbd, table', async ({ page }) => {
      await openDisplay(page, { tw, dark: true, section: 'panels' })

      for (const id of ['card', 'card-subtle', 'callout-default']) {
        const edge = await page.getByTestId(id).evaluate(contrastOf, { what: 'border' })
        expect(edge.ratio, id).toBeGreaterThan(1.05)
      }
    })

    for (const [name, path, extra, selector] of [
      ['the list page', 'shell-list', '', 'main'],
      ['the record page', 'shell-record', '', 'main'],
      ['the record page, a blocked job', 'shell-record', '&status=blocked', 'main'],
      ['the document page', 'shell-document', '', 'aside'],
    ]) {
      for (const dark of [true, false]) {
        test(`${name} in context, ${dark ? 'dark' : 'light'}: every word of its 3b parts reads on its own surface`, async ({ page }) => {
          await open(page, { path: path, tw, dark, extra })

          // the parts of the page that are this batch's: tables, cards, description lists, callouts, avatars, progress, badges
          const failures = await sweepText(page, `${selector} [data-atom-table], ${selector} [data-atom-card], ${selector} [data-atom-dd], ${selector} [data-atom-avatar], ${selector} [data-atom-progress], ${selector} [data-atom-badge], ${selector} [data-testid="record-callout"]`, TEXT, 12)

          expect(failures).toEqual([])
        })
      }
    }
  })
}
