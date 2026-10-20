import { test, expect, devices } from '@playwright/test'
import { openLayers, style, token, settle, showModal, showPrompt, showToast, showMenu, showCommand, showLightbox, setTrail, promptDialog } from './layers-helpers.js'

// V4-3c, the overlays and the navigation: sizes and structure, in light, on the consumer's Tailwind (`node tests/consumer/build.mjs`),
// floor and latest. Radii, hairlines, which layers cast a shadow, heights from the control token and 16px icons beside the text.
// Sizes and structure only, never a text width: Linux CI draws DejaVu Sans, which is much wider than the fonts on a Mac.

const { defaultBrowserType, ...ipad } = devices['iPad (gen 7)']

/** The element the tabs component draws its track or its rule on: the testid is on the inner row. */
const strip = (locator) => locator.locator('xpath=ancestor::*[@data-atom-tabs][1]')

for (const tw of ['floor', 'latest']) {
  test.describe(`overlays and navigation, ${tw} consumer build`, () => {
    test.describe('modal', () => {
      test('a raised surface with a strong hairline, a 12px radius and the shadow token', async ({ page }) => {
        await openLayers(page, { tw, section: 'modals' })
        const dialog = await showModal(page, 'm-basic')

        const s = await style(dialog, 'border-top-left-radius', 'border-top-width', 'box-shadow', 'background-color', 'color', 'padding-top', 'border-top-color')
        expect(s['border-top-left-radius']).toBe('12px')
        expect(s['border-top-width']).toBe('1px')
        expect(s['padding-top']).toBe('24px')
        expect(s['box-shadow']).not.toBe('none')
        expect(s['background-color']).toBe(await token(page, 'surface-raised'))
        expect(s.color).toBe(await token(page, 'fg'))
        expect(s['border-top-color']).toBe(await token(page, 'border-strong'))
      })

      test('the scrim is the warm near-black, not the black of v3', async ({ page }) => {
        await openLayers(page, { tw, section: 'modals' })
        const dialog = await showModal(page, 'm-basic')

        expect(await dialog.evaluate((el) => getComputedStyle(el, '::backdrop').backgroundColor)).toBe('rgba(21, 20, 18, 0.5)')
      })

      test('the close button is a control-high square with a 16px icon, and is not what the focus lands on', async ({ page }) => {
        await openLayers(page, { tw, section: 'modals' })
        const dialog = await showModal(page, 'm-basic')
        const close = dialog.getByRole('button', { name: 'Close' })

        const box = await style(close, 'border-top-left-radius')
        expect([box.width, box.height]).toEqual([28, 28])
        expect(box['border-top-left-radius']).toBe('7px')
        expect((await style(close.locator('[data-atom-icon]'))).width).toBe(16)

        // after the content in the tab order, so showModal() puts the focus on the first field and no ring is drawn on the close button
        await expect(dialog.getByTestId('m-basic-input')).toBeFocused()
        await expect(close).not.toBeFocused()
        expect((await style(close, 'outline-style'))['outline-style']).toBe('none')
      })

      test('the close button is 32px at comfortable density and 40px on a touch device', async ({ page }) => {
        await openLayers(page, { tw, section: 'modals', density: 'comfortable' })
        const dialog = await showModal(page, 'm-basic')

        expect((await style(dialog.getByRole('button', { name: 'Close' }))).height).toBe(32)
      })

      test('a modal with closeable off has no close button', async ({ page }) => {
        await openLayers(page, { tw, section: 'modals' })
        const dialog = await showModal(page, 'm-persistent')

        await expect(dialog.getByRole('button', { name: 'Close' })).toHaveCount(0)
      })

      test('the slide-over keeps its mechanics: full height, no radius, one hairline on its edge, the same surface and shadow', async ({ page }) => {
        await openLayers(page, { tw, section: 'modals' })
        const dialog = await showModal(page, 'm-slide', { slide: true })

        const s = await style(dialog, 'border-top-left-radius', 'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width', 'box-shadow', 'background-color', 'position')
        expect(s.position).toBe('fixed')
        expect(s['border-top-left-radius']).toBe('0px')
        expect([s['border-top-width'], s['border-right-width'], s['border-bottom-width'], s['border-left-width']]).toEqual(['0px', '0px', '0px', '1px'])
        expect(s['box-shadow']).not.toBe('none')
        expect(s['background-color']).toBe(await token(page, 'surface-raised'))
        expect(s.height).toBe(page.viewportSize().height)

        // it slid in: its right edge is the viewport's
        expect(await dialog.evaluate((el) => Math.round(el.getBoundingClientRect().right))).toBe(page.viewportSize().width)
      })

      test('a modal closes with the button, with Escape and from the backdrop, as before', async ({ page }) => {
        await openLayers(page, { tw, section: 'modals' })

        let dialog = await showModal(page, 'm-basic')
        await dialog.getByRole('button', { name: 'Close' }).click()
        await expect(dialog).not.toHaveAttribute('data-open', '')

        dialog = await showModal(page, 'm-basic')
        await page.keyboard.press('Escape')
        await expect(dialog).not.toHaveAttribute('data-open', '')

        dialog = await showModal(page, 'm-basic')
        await page.mouse.click(5, 5)
        await expect(dialog).not.toHaveAttribute('data-open', '')
      })
    })

    test.describe('alert and confirm', () => {
      test('the heading is 16px beside a 20px tone icon, the text is muted, and the buttons are the controls', async ({ page }) => {
        await openLayers(page, { tw, section: 'prompts' })
        const dialog = await showPrompt(page, 'confirm', { variant: 'danger', heading: 'Delete customer?', subheading: 'This cannot be undone.', message: ['Their invoices stay', 'Their statements go'] })

        expect((await style(dialog.getByText('Delete customer?'), 'font-size', 'font-weight'))['font-size']).toBe('16px')
        expect((await style(dialog.locator('[data-atom-icon]').first())).width).toBe(20)
        expect((await style(dialog.getByText('This cannot be undone.'), 'color')).color).toBe(await token(page, 'fg-muted'))

        for (const name of ['Cancel', 'Confirm']) expect((await style(dialog.getByRole('button', { name, exact: true }))).height).toBe(28)

        expect((await style(dialog, 'border-top-left-radius'))['border-top-left-radius']).toBe('12px')
      })

      test('the tone icon reads the tone token: danger, success and warning', async ({ page }) => {
        await openLayers(page, { tw, section: 'prompts' })

        for (const [variant, name] of [['danger', 'danger-fg'], ['success', 'success-fg'], ['warning', 'warning-fg']]) {
          const dialog = await showPrompt(page, 'alert', { variant, heading: 'Heads up', message: 'Something to read.' })
          const icon = dialog.locator('[data-atom-icon]:visible').first()

          expect((await style(icon, 'color')).color, variant).toBe(await token(page, name))
          await dialog.getByRole('button', { name: 'Ok' }).click()
          await expect(dialog).not.toHaveAttribute('data-open', '')
        }
      })

      test('a confirm still resolves on Confirm and rejects on Cancel', async ({ page }) => {
        await openLayers(page, { tw, section: 'prompts' })

        await page.evaluate(() => { window.__answer = null; atom.confirm({ heading: 'Sure?' }).then(() => { window.__answer = 'accepted' }).catch(() => { window.__answer = 'rejected' }) })
        const dialog = promptDialog(page, 'confirm')
        await expect(dialog).toHaveAttribute('data-open', '')
        await dialog.getByRole('button', { name: 'Confirm' }).click()
        await expect.poll(() => page.evaluate(() => window.__answer)).toBe('accepted')

        await page.evaluate(() => { window.__answer = null; atom.confirm({ heading: 'Sure?' }).then(() => { window.__answer = 'accepted' }).catch(() => { window.__answer = 'rejected' }) })
        await expect(dialog).toHaveAttribute('data-open', '')
        await dialog.getByRole('button', { name: 'Cancel' }).click()
        await expect.poll(() => page.evaluate(() => window.__answer)).toBe('rejected')
      })
    })

    test.describe('dropdown, menu and context menu', () => {
      test('the panel is a raised layer: the raised surface, a strong hairline, a 10px radius, 4px of padding and the shadow token', async ({ page }) => {
        await openLayers(page, { tw, section: 'menus' })
        const menu = await showMenu(page)

        const s = await style(menu, 'border-top-left-radius', 'border-top-width', 'padding-top', 'padding-left', 'box-shadow', 'background-color', 'border-top-color', 'color')
        expect(s['border-top-left-radius']).toBe('10px')
        expect(s['border-top-width']).toBe('1px')
        expect([s['padding-top'], s['padding-left']]).toEqual(['4px', '4px'])
        expect(s['box-shadow']).not.toBe('none')
        expect(s['background-color']).toBe(await token(page, 'surface-raised'))
        expect(s['border-top-color']).toBe(await token(page, 'border-strong'))
        expect(s.color).toBe(await token(page, 'fg'))
      })

      test('an item is the control height (28 compact, 32 comfortable) with a 6px radius and 16px icons', async ({ page }) => {
        await openLayers(page, { tw, section: 'menus' })
        const menu = await showMenu(page)

        for (const id of ['item-edit', 'item-icon-suffix', 'item-badge', 'item-danger', 'item-warning']) {
          const item = menu.getByTestId(id)
          const s = await style(item, 'border-top-left-radius', 'font-size')
          expect([id, s.height, s['border-top-left-radius']], id).toEqual([id, 28, '6px'])
        }

        for (const icon of await menu.getByTestId('item-icon-suffix').locator('[data-atom-icon]').all()) expect((await style(icon)).width).toBe(16)
        expect((await style(menu.getByTestId('item-edit'), 'font-size'))['font-size']).toBe('13px')

        await openLayers(page, { tw, section: 'menus', density: 'comfortable' })
        await showMenu(page)
        expect((await style(page.getByTestId('item-edit'))).height).toBe(32)
      })

      test('a tone item reads the tone, and its hover is the tone fill; a plain one tints with the foreground', async ({ page }) => {
        await openLayers(page, { tw, section: 'menus' })
        const menu = await showMenu(page)

        expect((await style(menu.getByTestId('item-danger'), 'color')).color).toBe(await token(page, 'danger-fg'))
        expect((await style(menu.getByTestId('item-warning'), 'color')).color).toBe(await token(page, 'warning-fg'))

        await menu.getByTestId('item-danger').hover()
        await settle(menu.getByTestId('item-danger'))
        expect((await style(menu.getByTestId('item-danger'), 'background-color'))['background-color']).toBe(await token(page, 'danger-bg'))

        await menu.getByTestId('item-edit').hover()
        await settle(menu.getByTestId('item-edit'))
        const hover = (await style(menu.getByTestId('item-edit'), 'background-color'))['background-color']
        // the foreground at 6%, whichever colour space the browser reports it in
        expect(hover).toMatch(/\/ 0\.06\)$|, 0\.06\)$/)
      })

      test('a disabled item is dimmed and takes no pointer', async ({ page }) => {
        await openLayers(page, { tw, section: 'menus' })
        const menu = await showMenu(page)
        const s = await style(menu.getByTestId('item-disabled'), 'opacity', 'pointer-events')

        expect([s.opacity, s['pointer-events']]).toEqual(['0.5', 'none'])
      })

      test('a menu item that has the keyboard draws the ring inside its own box, so the panel does not clip it', async ({ page }) => {
        await openLayers(page, { tw, section: 'menus' })
        const menu = await showMenu(page)
        const item = menu.getByTestId('item-edit')

        await item.focus()
        await page.keyboard.press('Shift+Tab')
        await page.keyboard.press('Tab')
        await settle(item)

        const s = await style(item, 'outline-style', 'outline-width', 'outline-offset')
        expect([s['outline-style'], s['outline-width'], s['outline-offset']]).toEqual(['solid', '2px', '-2px'])
      })

      test('the context menu opens the same panel at the pointer', async ({ page }) => {
        await openLayers(page, { tw, section: 'menus' })
        // a synthetic contextmenu event: a real right-click puts the panel's rounded corner under the pointer, and the mouse-up that
        // follows lands outside the rounded shape, which the browser reads as a click away and closes the popover (v3 does the same)
        await page.getByTestId('context-area').dispatchEvent('contextmenu', { clientX: 400, clientY: 120 })

        const panel = page.getByTestId('context-menu').locator('[data-atom-menu]')
        await expect(panel).toBeVisible()
        expect((await style(panel, 'border-top-left-radius', 'box-shadow'))['border-top-left-radius']).toBe('10px')
      })

      test.describe('touch (iPad profile)', () => {
        test.use({ ...ipad })

        test('a touch device raises an item to 40px', async ({ page }) => {
          await openLayers(page, { tw, section: 'menus' })
          expect(await page.evaluate(() => matchMedia('(pointer: coarse)').matches)).toBe(true)

          await page.getByTestId('dropdown-trigger').tap()
          await expect(page.getByTestId('menu')).toBeVisible()
          expect((await style(page.getByTestId('item-edit'))).height).toBe(40)
        })
      })
    })

    test.describe('tooltip', () => {
      test('the inverse of the surface: the foreground as the fill, the canvas as the text, 12px, a 6px radius, no shadow', async ({ page }) => {
        await openLayers(page, { tw, section: 'tooltips' })
        await page.getByTestId('tooltip-kbd-trigger').hover()

        const tip = page.getByTestId('tooltip-kbd').locator('[data-atom-tooltip-content]')
        await expect(tip).toBeVisible()

        const s = await style(tip, 'background-color', 'color', 'font-size', 'border-top-left-radius', 'box-shadow', 'border-top-width')
        expect(s['background-color']).toBe(await token(page, 'fg'))
        expect(s.color).toBe(await token(page, 'canvas'))
        expect([s['font-size'], s['border-top-left-radius'], s['box-shadow'], s['border-top-width']]).toEqual(['12px', '6px', 'none', '0px'])
      })

      test('it shows on focus too, and Escape hides it', async ({ page }) => {
        await openLayers(page, { tw, section: 'tooltips' })
        await page.getByTestId('tooltip-plain-trigger').focus()

        const tip = page.getByTestId('tooltip-plain').locator('[data-atom-tooltip-content]')
        await expect(tip).toBeVisible()
        await page.keyboard.press('Escape')
        await expect(tip).toBeHidden()
      })
    })

    test.describe('toast', () => {
      test('a raised panel at the bottom: raised surface, strong hairline, 10px radius, the shadow token, a 16px tone icon', async ({ page }) => {
        await openLayers(page, { tw, section: 'prompts' })
        const panel = await showToast(page, { variant: 'success', heading: 'Invoice sent', message: 'INV-0042 went to Northwind Traders.' })

        const s = await style(panel, 'border-top-left-radius', 'border-top-width', 'box-shadow', 'background-color', 'border-top-color', 'color', 'font-size')
        expect(s['border-top-left-radius']).toBe('10px')
        expect(s['border-top-width']).toBe('1px')
        expect(s['box-shadow']).not.toBe('none')
        expect(s['background-color']).toBe(await token(page, 'surface-raised'))
        expect(s['border-top-color']).toBe(await token(page, 'border-strong'))
        expect(s['font-size']).toBe('13px')

        const icon = panel.locator('[data-atom-icon]:visible').first()
        expect((await style(icon)).width).toBe(16)
        expect((await style(icon, 'color')).color).toBe(await token(page, 'success-fg'))

        // placement is unchanged: bottom, centred
        const box = await panel.evaluate((el) => el.getBoundingClientRect().toJSON())
        expect(Math.round(box.left + box.width / 2)).toBe(page.viewportSize().width / 2)
        expect(Math.round(box.bottom)).toBeGreaterThan(page.viewportSize().height - 40)
      })

      test('each variant has its own tone, a message alone is a single line, and the close button dismisses it', async ({ page }) => {
        await openLayers(page, { tw, section: 'prompts' })

        for (const [variant, name] of [['danger', 'danger-fg'], ['warning', 'warning-fg']]) {
          const panel = await showToast(page, { variant, message: `A ${variant} message` })
          expect((await style(panel.locator('[data-atom-icon]:visible').first(), 'color')).color, variant).toBe(await token(page, name))
        }

        const panel = await showToast(page, { variant: 'success', message: 'Saved' })
        await panel.getByRole('button', { name: 'Close' }).click()
        await expect(page.locator('[data-atom-toast]')).toBeHidden()
      })
    })

    test.describe('command palette', () => {
      test('a raised panel at the modal radius; the search row is 48px with a 16px icon; items are the control height', async ({ page }) => {
        await openLayers(page, { tw, section: 'command' })
        const dialog = await showCommand(page)

        const s = await style(dialog, 'border-top-left-radius', 'border-top-width', 'box-shadow', 'background-color', 'border-top-color')
        expect(s['border-top-left-radius']).toBe('12px')
        expect(s['border-top-width']).toBe('1px')
        expect(s['box-shadow']).not.toBe('none')
        expect(s['background-color']).toBe(await token(page, 'surface-raised'))
        expect(s['border-top-color']).toBe(await token(page, 'border-strong'))

        expect((await style(dialog.locator('[data-atom-command-search]'))).height).toBe(48)
        expect((await style(dialog.locator('[data-atom-icon]').first())).width).toBe(16)

        for (const id of ['command-item-home', 'command-item-settings', 'command-item-invoice']) {
          const item = dialog.getByTestId(id)
          expect([id, (await style(item, 'border-top-left-radius'))['border-top-left-radius'], (await style(item)).height]).toEqual([id, '6px', 28])
        }
      })

      test('the active item is tinted, the search field has no ring of its own, and the keyboard still drives it', async ({ page }) => {
        await openLayers(page, { tw, section: 'command' })
        const dialog = await showCommand(page)

        await expect(dialog.getByTestId('command-item-home')).toHaveAttribute('data-active', '')
        expect((await style(dialog.getByTestId('command-item-home'), 'background-color'))['background-color']).not.toBe('rgba(0, 0, 0, 0)')
        expect((await style(dialog.locator('[data-atom-command-search]'), 'outline-style'))['outline-style']).toBe('none')

        await page.keyboard.press('ArrowDown')
        await expect(dialog.getByTestId('command-item-settings')).toHaveAttribute('data-active', '')
      })
    })

    test.describe('lightbox', () => {
      test('the controls are 40px round buttons on the raised surface with a 20px icon, so they read against themselves and not the scrim', async ({ page }) => {
        await openLayers(page, { tw, section: 'lightbox' })
        const dialog = await showLightbox(page)

        for (const name of ['Close', 'Previous', 'Next']) {
          const button = dialog.getByRole('button', { name })
          const s = await style(button, 'border-top-left-radius', 'background-color', 'box-shadow')
          expect([name, s.width, s.height, s['border-top-left-radius']], name).toEqual([name, 40, 40, '999px'])
          expect(s['background-color']).toBe(await token(page, 'surface-raised'))
          expect((await style(button.locator('[data-atom-icon]'))).width).toBe(20)
        }

        expect(await dialog.evaluate((el) => getComputedStyle(el, '::backdrop').backgroundColor)).toBe('rgba(0, 0, 0, 0.8)')
      })

      test('the focus lands on the close button, and a photo is drawn', async ({ page }) => {
        await openLayers(page, { tw, section: 'lightbox' })
        const dialog = await showLightbox(page)

        await expect(dialog.getByRole('button', { name: 'Close' })).toBeFocused()
        expect((await style(dialog.locator('img').first())).width).toBeGreaterThan(100)
      })

      test('a file that is not a picture is a panel of its own: raised, hairline, modal radius, with a Download button', async ({ page }) => {
        await openLayers(page, { tw, section: 'lightbox' })
        const dialog = await showLightbox(page, 'file')
        const card = dialog.getByText('Statement.pdf').locator('xpath=..')

        const s = await style(card, 'border-top-left-radius', 'background-color', 'border-top-width')
        expect([s['border-top-left-radius'], s['border-top-width']]).toEqual(['12px', '1px'])
        expect(s['background-color']).toBe(await token(page, 'surface-raised'))
        await expect(card.getByRole('button', { name: 'Download' })).toBeVisible()
      })

      test('Escape closes it and clears data-open', async ({ page }) => {
        await openLayers(page, { tw, section: 'lightbox' })
        const dialog = await showLightbox(page)

        await page.keyboard.press('Escape')
        await expect(dialog).not.toHaveAttribute('data-open', '')
      })
    })

    test.describe('tabs', () => {
      test('the line tabs: ruled underneath, 36px, 13px labels, and the current one underlined in the foreground', async ({ page }) => {
        await openLayers(page, { tw, section: 'nav' })
        const s = await style(strip(page.getByTestId('tabs-line')), 'border-bottom-width', 'border-bottom-color', 'box-shadow')
        expect([s['border-bottom-width'], s.height, s['box-shadow']]).toEqual(['1px', 36, 'none'])
        expect(s['border-bottom-color']).toBe(await token(page, 'border'))

        const current = await style(page.getByTestId('tab-current'), 'border-bottom-width', 'border-bottom-color', 'color', 'font-size', 'box-shadow')
        expect([current['border-bottom-width'], current['font-size'], current['box-shadow']]).toEqual(['2px', '13px', 'none'])
        expect(current['border-bottom-color']).toBe(await token(page, 'fg'))
        expect(current.color).toBe(await token(page, 'fg'))

        const idle = await style(page.getByTestId('tab-idle'), 'border-bottom-color', 'color', 'font-weight')
        expect(idle['border-bottom-color']).toBe('rgba(0, 0, 0, 0)')
        expect(idle.color).toBe(await token(page, 'fg-muted'))
        // one weight for every tab, so the row does not shift when the current one changes
        expect(idle['font-weight']).toBe((await style(page.getByTestId('tab-current'), 'font-weight'))['font-weight'])
      })

      test('the small line is 32px, the segments 32px (28 small), and a segment inside the track is one control high', async ({ page }) => {
        await openLayers(page, { tw, section: 'nav' })

        expect((await style(strip(page.getByTestId('tabs-line-sm')))).height).toBe(32)

        const track = await style(strip(page.getByTestId('tabs-button')), 'background-color', 'border-top-left-radius', 'padding-top', 'box-shadow')
        expect([track.height, track['border-top-left-radius'], track['padding-top'], track['box-shadow']]).toEqual([32, '7px', '2px', 'none'])
        expect(track['background-color']).toBe(await token(page, 'surface-sunken'))
        expect((await style(page.getByTestId('seg-current'))).height).toBe(28)

        expect((await style(strip(page.getByTestId('tabs-button-sm')))).height).toBe(28)
      })

      test('the current segment is the surface with a strong hairline and no shadow; an idle one has neither', async ({ page }) => {
        await openLayers(page, { tw, section: 'nav' })

        const current = await style(page.getByTestId('seg-current'), 'background-color', 'border-top-width', 'border-top-color', 'box-shadow', 'border-top-left-radius', 'color')
        expect(current['background-color']).toBe(await token(page, 'surface'))
        expect(current['border-top-color']).toBe(await token(page, 'border-strong'))
        expect([current['border-top-width'], current['box-shadow'], current['border-top-left-radius']]).toEqual(['1px', 'none', '6px'])
        expect(current.color).toBe(await token(page, 'fg'))

        const idle = await style(page.getByTestId('seg-idle'), 'background-color', 'border-top-color', 'color')
        expect(idle['background-color']).toBe('rgba(0, 0, 0, 0)')
        expect(idle['border-top-color']).toBe('rgba(0, 0, 0, 0)')
        expect(idle.color).toBe(await token(page, 'fg-muted'))
      })

      test('an icon in a tab is 16px, and a link tab is an anchor', async ({ page }) => {
        await openLayers(page, { tw, section: 'nav' })

        expect((await style(page.getByTestId('tab-icon').locator('[data-atom-icon]'))).width).toBe(16)
        expect(await page.getByTestId('tab-link').evaluate((el) => el.tagName)).toBe('A')
      })

      test('a tab that has the keyboard draws the ring inside its own box', async ({ page }) => {
        await openLayers(page, { tw, section: 'nav' })
        const tab = page.getByTestId('tab-idle')

        await tab.focus()
        await page.keyboard.press('Shift+Tab')
        await page.keyboard.press('Tab')
        await settle(tab)

        const s = await style(tab, 'outline-style', 'outline-offset')
        expect([s['outline-style'], s['outline-offset']]).toEqual(['solid', '-2px'])
      })

      test.describe('touch (iPad profile)', () => {
        test.use({ ...ipad })

        test('a touch device raises the tabs with the control token: 48px line, 44px track', async ({ page }) => {
          await openLayers(page, { tw, section: 'nav' })

          expect((await style(strip(page.getByTestId('tabs-line')))).height).toBe(48)
          expect((await style(strip(page.getByTestId('tabs-button')))).height).toBe(44)
        })
      })
    })

    test.describe('breadcrumbs', () => {
      test('13px, the ancestors muted and the page you are on in the foreground, with 16px separators', async ({ page }) => {
        await openLayers(page, { tw, section: 'nav' })
        await setTrail(page)

        const crumbs = page.getByTestId('crumbs')
        const [home, invoices] = [crumbs.getByRole('link', { name: 'Home' }), crumbs.getByRole('link', { name: 'Invoices' })]
        const current = crumbs.locator('[aria-current="page"]')

        expect((await style(home, 'color', 'font-size'))['font-size']).toBe('13px')
        expect((await style(home, 'color')).color).toBe(await token(page, 'fg-muted'))
        expect((await style(invoices, 'color')).color).toBe(await token(page, 'fg-muted'))
        expect((await style(current, 'color')).color).toBe(await token(page, 'fg'))
        await expect(current).toHaveText('INV-0042')

        const separator = crumbs.locator('li').first().locator('[data-atom-icon]')
        expect((await style(separator)).width).toBe(16)
        expect((await style(separator, 'color')).color).toBe(await token(page, 'fg-subtle'))
      })

      test('a link darkens to the foreground on hover', async ({ page }) => {
        await openLayers(page, { tw, section: 'nav' })
        await setTrail(page)

        const home = page.getByTestId('crumbs').getByRole('link', { name: 'Home' })
        await home.hover()
        await settle(home)

        expect((await style(home, 'color')).color).toBe(await token(page, 'fg'))
      })
    })

    test.describe('accordion', () => {
      test('rows ruled by the hairline, 40px high at least, 13px medium headings, a 16px chevron, no shadow', async ({ page }) => {
        await openLayers(page, { tw, section: 'nav' })
        const accordion = page.getByTestId('accordion')
        await settle(accordion)
        const [first, second] = [accordion.getByRole('button', { name: 'What is included?' }), accordion.getByRole('button', { name: 'Can I export it?' })]

        const s = await style(first, 'font-size', 'font-weight', 'box-shadow', 'color')
        expect([s['font-size'], s['box-shadow']]).toEqual(['13px', 'none'])
        expect(s.height).toBeGreaterThanOrEqual(40)
        expect(Number(s['font-weight'])).toBeGreaterThanOrEqual(500)
        expect(s.color).toBe(await token(page, 'fg'))
        expect((await style(first.locator('[data-atom-icon]'))).width).toBe(16)

        // Tailwind 4 rules the items with the bottom border of every one but the last, in the border token
        const item = await style(accordion.locator('[data-atom-accordion-item]').first(), 'border-bottom-width', 'border-bottom-color')
        expect([item['border-bottom-width'], item['border-bottom-color']]).toEqual(['1px', await token(page, 'border')])
        expect((await style(second)).height).toBeGreaterThanOrEqual(40)
      })

      test('it still opens and closes, and its heading carries aria-expanded', async ({ page }) => {
        await openLayers(page, { tw, section: 'nav' })
        const second = page.getByTestId('accordion').getByRole('button', { name: 'Can I export it?' })

        await expect(second).toHaveAttribute('aria-expanded', 'false')
        await second.click()
        await expect(second).toHaveAttribute('aria-expanded', 'true')
        await second.click()
        await expect(second).toHaveAttribute('aria-expanded', 'false')
      })
    })
  })
}
