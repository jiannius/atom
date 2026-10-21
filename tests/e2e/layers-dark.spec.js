import { test, expect } from '@playwright/test'
import { openLayers, gate, ratio, sweepText, settle, showModal, showPrompt, showToast, showMenu, showCommand, showLightbox, setTrail, ACCENTS, TEXT, GRAPHIC } from './layers-helpers.js'
import { focusByKeyboard } from './controls-helpers.js'

// V4-3c, the dark check. Scoped to the 3c components, and the variants of them, that the dark-mode hosts render (found by scanning
// their <atom:...> tags; the apps themselves are not named here). Every text role is measured against ITS OWN surface (every
// ancestor's fill composited, not the page), at 4.5:1 for text and 3:1 for the icons and rings that carry meaning, for three
// accents, in dark AND light, on the consumer's Tailwind, floor and latest.
//
// The dark-mode hosts render: modal (centred, inset, not dismissible, not closeable, and a slide-over), confirm (danger and delete),
// alert, toast (success, danger and warning), dropdown with a popover menu (items with an icon, a badge, danger and delete),
// tooltip (with and without a custom content element), tabs (the line and the button variant), breadcrumbs and the lightbox.
// Not rendered by them, and measured all the same: the command palette, the context menu and the accordion.

const THEMES = [['dark', true], ['light', false]]

for (const tw of ['floor', 'latest']) {
  for (const [theme, dark] of THEMES) {
    test.describe(`overlays and navigation, ${theme}, ${tw} consumer build`, () => {
      for (const accent of ACCENTS) {
        test(`modal and slide-over: the title, the text, the buttons and the close icon, accent ${accent}`, async ({ page }) => {
          await openLayers(page, { tw, dark, accent, section: 'modals' })

          let dialog = await showModal(page, 'm-basic')
          expect(await sweepText(page, '[data-testid="m-basic"]', TEXT, 5)).toEqual([])
          await gate(dialog.getByRole('button', { name: 'Close' }).locator('[data-atom-icon]'), GRAPHIC, 'text', 'close icon')
          await dialog.getByRole('button', { name: 'Close' }).hover()
          await settle(dialog)
          await gate(dialog.getByRole('button', { name: 'Close' }).locator('[data-atom-icon]'), GRAPHIC, 'text', 'close icon on hover')
          await page.keyboard.press('Escape')

          dialog = await showModal(page, 'm-slide', { slide: true })
          expect(await sweepText(page, '[data-testid="m-slide"]', TEXT, 2)).toEqual([])
          await gate(dialog.getByRole('button', { name: 'Close' }).locator('[data-atom-icon]'), GRAPHIC, 'text', 'slide-over close icon')
        })

        test(`confirm and alert: every variant's heading, text, message list, icon and buttons, accent ${accent}`, async ({ page }) => {
          await openLayers(page, { tw, dark, accent, section: 'prompts' })

          for (const variant of ['danger', 'success', 'warning', null]) {
            const dialog = await showPrompt(page, 'confirm', { variant, heading: 'Delete customer?', subheading: 'This cannot be undone.', message: ['Their invoices stay', 'Their statements go'] })

            expect(await sweepText(page, 'dialog[x-data*="atom-confirm"]', TEXT, 5), `confirm ${variant}`).toEqual([])
            for (const icon of await dialog.locator('[data-atom-icon]:visible').all()) await gate(icon, GRAPHIC, 'text', `confirm ${variant} icon`)

            await dialog.getByRole('button', { name: 'Cancel' }).click()
            await expect(dialog).not.toHaveAttribute('data-open', '')
          }

          for (const variant of ['danger', 'success', 'warning']) {
            const dialog = await showPrompt(page, 'alert', { variant, heading: 'Invoice is overdue', message: 'It was due 12 days ago.' })

            expect(await sweepText(page, 'dialog[x-data*="atom-alert"]', TEXT, 3), `alert ${variant}`).toEqual([])
            await gate(dialog.locator('[data-atom-icon]:visible').first(), GRAPHIC, 'text', `alert ${variant} icon`)

            await dialog.getByRole('button', { name: 'Ok' }).click()
            await expect(dialog).not.toHaveAttribute('data-open', '')
          }
        })

        test(`toast: success, danger and warning, with a heading and without, accent ${accent}`, async ({ page }) => {
          await openLayers(page, { tw, dark, accent, section: 'prompts' })

          for (const variant of ['success', 'danger', 'warning']) {
            for (const config of [
              { variant, heading: 'Invoice sent', message: 'INV-0042 went to Northwind Traders.' },
              { variant, message: 'Saved' },
              { variant, heading: 'Check these', message: ['The name is required', 'The date is in the past'] },
            ]) {
              const panel = await showToast(page, config)

              expect(await sweepText(page, '[data-atom-toast]', TEXT, 1), `${variant} ${config.heading ?? 'plain'}`).toEqual([])
              await gate(panel.locator('[data-atom-icon]:visible').first(), GRAPHIC, 'text', `${variant} toast icon`)
              await gate(panel.getByRole('button', { name: 'Close' }).locator('[data-atom-icon]'), GRAPHIC, 'text', 'toast close icon')
            }
          }
        })

        test(`menu: every item on the panel, on its hover tint and with the ring, accent ${accent}`, async ({ page }) => {
          await openLayers(page, { tw, dark, accent, section: 'menus' })
          const menu = await showMenu(page)

          // the disabled item is dimmed on purpose (WCAG 1.4.3 exempts a disabled control); the rest are read in full
          const failures = (await sweepText(page, '[data-testid="menu"]', TEXT, 6)).filter((f) => f.text !== 'Archive')
          expect(failures).toEqual([])

          for (const id of ['item-edit', 'item-icon-suffix', 'item-badge', 'item-warning', 'item-danger']) {
            const item = menu.getByTestId(id)

            await item.hover()
            await settle(item)
            await gate(item, TEXT, 'text', `${id} hovered`)
            for (const icon of await item.locator('> [data-atom-icon]').all()) await gate(icon, GRAPHIC, 'text', `${id} icon hovered`)
          }

          await focusByKeyboard(page, menu.getByTestId('item-edit'))
          await gate(menu.getByTestId('item-edit'), TEXT, 'text', 'focused item')
          await gate(menu.getByTestId('item-edit'), GRAPHIC, 'outline', 'the ring on a menu item')
        })

        test(`tooltip, with and without a shortcut, accent ${accent}`, async ({ page }) => {
          await openLayers(page, { tw, dark, accent, section: 'tooltips' })

          for (const id of ['tooltip-plain', 'tooltip-kbd']) {
            await page.getByTestId(`${id}-trigger`).hover()
            const tip = page.getByTestId(id).locator('[data-atom-tooltip-content]')
            await expect(tip).toBeVisible()

            expect(await sweepText(page, `[data-testid="${id}"] [data-atom-tooltip-content]`, TEXT, 1), id).toEqual([])
            await page.mouse.move(0, 0)
            await expect(tip).toBeHidden()
          }
        })

        test(`command palette: the placeholder, group headings, items, the active item, the shortcut and the icons, accent ${accent}`, async ({ page }) => {
          await openLayers(page, { tw, dark, accent, section: 'command' })
          const dialog = await showCommand(page)

          expect(await sweepText(page, '[data-testid="command-root"]', TEXT, 6)).toEqual([])
          await gate(dialog.locator('[data-atom-command-search]'), TEXT, 'placeholder', 'placeholder')

          // the active item and a hovered one carry a tint: the label and the shortcut are read on it
          await gate(dialog.getByTestId('command-item-home'), TEXT, 'text', 'the active item')
          await gate(dialog.getByTestId('command-item-home').locator('kbd'), TEXT, 'text', 'the shortcut on the active item')
          await dialog.getByTestId('command-item-settings').hover()
          await settle(dialog.getByTestId('command-item-settings'))
          await gate(dialog.getByTestId('command-item-settings'), TEXT, 'text', 'a hovered item')

          for (const icon of await dialog.locator('[data-atom-command-item] [data-atom-icon]').all()) await gate(icon, GRAPHIC, 'text', 'item icon')
          await gate(dialog.locator('[data-atom-icon]').first(), GRAPHIC, 'text', 'search icon')

          // type something that matches nothing: the empty text is read on the panel
          await page.keyboard.type('zzzz')
          await expect(dialog.locator('[data-atom-command-empty]')).toBeVisible()
          await gate(dialog.locator('[data-atom-command-empty]'), TEXT, 'text', 'empty text')
        })

        test(`lightbox: the controls against themselves, and the file panel against its own surface, accent ${accent}`, async ({ page }) => {
          await openLayers(page, { tw, dark, accent, section: 'lightbox' })

          let dialog = await showLightbox(page)
          for (const name of ['Close', 'Previous', 'Next']) {
            const button = dialog.getByRole('button', { name })
            await gate(button.locator('[data-atom-icon]'), GRAPHIC, 'text', `${name} icon`)
          }

          await page.keyboard.press('Escape')
          dialog = await showLightbox(page, 'file')
          expect(await sweepText(page, 'dialog[data-atom-lightbox] [data-atom-button], dialog[data-atom-lightbox] .rounded-modal', TEXT, 2)).toEqual([])
          await gate(dialog.getByText('Statement.pdf'), TEXT, 'text', 'file name')
          await gate(dialog.locator('.size-32 [data-atom-icon]'), GRAPHIC, 'text', 'file icon')
        })

        test(`tabs, breadcrumbs and accordion on the page and on the track, with the ring, accent ${accent}`, async ({ page }) => {
          await openLayers(page, { tw, dark, accent, section: 'nav' })
          await setTrail(page)
          await settle(page.getByTestId('accordion'))

          // idle and current, on the page (the line) and on the sunken track (the segments)
          for (const id of ['tab-current', 'tab-idle', 'tab-icon', 'tab-link', 'seg-current', 'seg-idle']) await gate(page.getByTestId(id), TEXT, 'text', id)
          await gate(page.getByTestId('tab-icon').locator('[data-atom-icon]'), GRAPHIC, 'text', 'tab icon')

          // the underline of the current tab, and the edge of the current segment against the track
          await gate(page.getByTestId('tab-current'), GRAPHIC, 'border-bottom-color', 'the current tab underline')

          // hover
          for (const id of ['tab-idle', 'seg-idle']) {
            await page.getByTestId(id).hover()
            await settle(page.getByTestId(id))
            await gate(page.getByTestId(id), TEXT, 'text', `${id} hovered`)
          }

          // the ring
          for (const id of ['tab-idle', 'seg-idle']) {
            await focusByKeyboard(page, page.getByTestId(id))
            await gate(page.getByTestId(id), GRAPHIC, 'outline', `${id} ring`)
          }

          // breadcrumbs
          const crumbs = page.getByTestId('crumbs')
          await gate(crumbs.getByRole('link', { name: 'Home' }), TEXT, 'text', 'ancestor crumb')
          await gate(crumbs.locator('[aria-current="page"]'), TEXT, 'text', 'current crumb')
          await gate(crumbs.locator('[data-atom-icon]').first(), GRAPHIC, 'text', 'crumb separator')
          await crumbs.getByRole('link', { name: 'Home' }).hover()
          await settle(crumbs)
          await gate(crumbs.getByRole('link', { name: 'Home' }), TEXT, 'text', 'hovered crumb')

          // accordion
          const accordion = page.getByTestId('accordion')
          await gate(accordion.getByRole('button', { name: 'What is included?' }), TEXT, 'text', 'accordion heading')
          await gate(accordion.getByTestId('accordion-body'), TEXT, 'text', 'accordion body')
          await gate(accordion.getByRole('button', { name: 'Can I export it?' }).locator('[data-atom-icon]'), GRAPHIC, 'text', 'accordion chevron')
          await focusByKeyboard(page, accordion.getByRole('button', { name: 'Can I export it?' }))
          await gate(accordion.getByRole('button', { name: 'Can I export it?' }), GRAPHIC, 'outline', 'accordion ring')
        })
      }

      test('the edge of every raised layer is told from what is behind it: a strong hairline, in the theme it sits in', async ({ page }) => {
        await openLayers(page, { tw, dark, section: 'menus' })
        const menu = await showMenu(page)

        // reported, not gated: a hairline is not a 3:1 boundary (the shadow and the surface step carry the layer); it must at least be there
        const edge = await menu.evaluate((el) => getComputedStyle(el).borderTopColor)
        expect(edge).not.toBe('rgba(0, 0, 0, 0)')
        expect(await ratio(menu, 'border')).toBeGreaterThan(1.05)
      })
    })
  }
}
