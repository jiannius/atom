import { expect } from '@playwright/test'
import { openDisplay, open, ratio, style, gate, token, contrastOf, sweepText, ACCENTS, TEXT, GRAPHIC } from './display-helpers.js'

// Shared by the V4-3c specs (overlays and navigation): the V4-3a/3b helpers, plus a way to open each layer of
// resources/views/e2e/layers.blade.php the way a host does (atom.modal(name).show(), atom.toast(...), a click on a trigger).

export { open, ratio, style, gate, token, contrastOf, sweepText, ACCENTS, TEXT, GRAPHIC }

/** Open the layers sheet (resources/views/e2e/layers.blade.php). */
export const openLayers = (page, options = {}) => open(page, { path: 'layers', ...options })

/** Let every transition under a locator finish before reading what it ends at. */
export const settle = (locator) => locator.evaluate((el) => Promise.all(el.getAnimations({ subtree: true }).map((a) => a.finished.catch(() => null))))

/** Open a modal of the sheet (a slide-over with `slide`) and wait until it is open and still. */
export async function showModal(page, name, { slide = false } = {}) {
  await page.evaluate(([n, s]) => { if (s) atom.modal(n).slide(); else atom.modal(n).show() }, [name, slide])

  const dialog = page.getByTestId(name)
  await expect(dialog).toHaveAttribute('data-open', '')
  await settle(dialog)

  return dialog
}

/** The dialog a prompt (alert or confirm) draws in. */
export const promptDialog = (page, kind) => page.locator(`dialog[x-data*="atom-${kind}"]`)

/** Open an alert or a confirm with a config, and wait until it is open and still. The promise a prompt returns is left alone. */
export async function showPrompt(page, kind, config) {
  await page.evaluate(([k, c]) => { window.atom[k](c).catch?.(() => {}) }, [kind, config])

  const dialog = promptDialog(page, kind)
  await expect(dialog).toHaveAttribute('data-open', '')
  await settle(dialog)

  return dialog
}

/**
 * Show a toast that stays, and wait until THIS toast is drawn. Returns the panel inside the popover. The previous toast is closed
 * first and waited out, because the root keeps `opacity-100` from it until the new one's `nextTick`: that class alone proves nothing.
 * Then the popover must be open and the new text (the heading, else the message) must be in the panel, and so must a visible icon
 * when the variant has one.
 */
export async function showToast(page, config) {
  const root = page.locator('[data-atom-toast]')

  await page.evaluate(() => { window.dispatchEvent(new CustomEvent('atom-toast-close')) })
  await expect(root).not.toHaveClass(/opacity-100/)
  await expect(root).toBeHidden()

  await page.evaluate((c) => { atom.toast({ delay: 0, ...c }) }, config)

  await expect(root).toHaveClass(/opacity-100/)
  await expect(root).toBeVisible()

  const panel = root.locator('> div').first()
  const first = Array.isArray(config.message) ? config.message[0] : config.message
  await expect(panel).toContainText(config.heading ?? config.subheading ?? first)
  if (config.variant) await expect(panel.locator('[data-atom-icon]:visible').first()).toBeVisible()
  await settle(root)

  return panel
}

/** Open the sheet's dropdown and return its menu, once it is open and faded in. */
export async function showMenu(page) {
  await page.getByTestId('dropdown-trigger').click()

  const menu = page.getByTestId('menu')
  await expect(menu).toBeVisible()
  await settle(page.getByTestId('dropdown'))

  return menu
}

/** Open the command palette of the sheet and return its dialog. */
export async function showCommand(page) {
  await page.evaluate(() => { atom.command('cmd').show() })

  const dialog = page.getByTestId('command-root')
  await expect(dialog).toHaveAttribute('data-open', '')
  await settle(dialog)

  return dialog
}

/** Open the lightbox on its photo (or on the file) and return the dialog. */
export async function showLightbox(page, which = 'photo') {
  await page.getByTestId(`lightbox-${which}`).click()

  const dialog = page.locator('dialog[data-atom-lightbox]')
  await expect(dialog).toHaveAttribute('data-open', '')
  await settle(dialog)

  return dialog
}

/** Give the breadcrumbs a trail (the host's component does this through Livewire; the sheet has none). */
export async function setTrail(page) {
  await page.evaluate(() => {
    window.Alpine.$data(document.querySelector('[data-atom-breadcrumbs]')).trails = [
      { title: 'Home', href: '/', url: '/', home: true },
      { title: 'Invoices', href: '/invoices', url: '/invoices' },
      { title: 'INV-0042', href: '/invoices/42', url: '/invoices/42' },
    ]
  })

  await expect(page.locator('[data-atom-breadcrumbs] li')).toHaveCount(3)
}
