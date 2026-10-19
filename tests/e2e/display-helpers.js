import { expect } from '@playwright/test'
import { open, ratio, style, contrastOf } from './controls-helpers.js'

// Shared by the V4-3b display specs: the V4-3a helpers (open, ratio, style, contrastOf), plus the gates they all use.

export { open, ratio, style, contrastOf }

export const ACCENTS = ['#4c55c9', '#f5c518', '#9f5afd']

/** Text is read at 4.5:1 against the surface it sits on; a boundary or an icon that carries meaning at 3:1. */
export const TEXT = 4.5
export const GRAPHIC = 3

/** Open the display sheet (resources/views/e2e/display.blade.php). */
export const openDisplay = (page, options = {}) => open(page, { path: 'display', ...options })

/** Assert one colour of a locator against the surface it is painted on. */
export async function gate(locator, min, what = 'text', label = '') {
  const found = await locator.evaluate(contrastOf, { what })
  expect(found.ratio, `${label || what}: ${found.colour} on ${found.back}`).toBeGreaterThanOrEqual(min)
  return found.ratio
}

/** The resolved colour of a role token (a probe element carrying `background-color: var(--atom-<name>)`), as a browser reports it. */
export const token = (page, name) => page.evaluate((n) => {
  const probe = document.createElement('div')
  probe.style.backgroundColor = `var(--atom-${n})`
  document.body.append(probe)
  const colour = getComputedStyle(probe).backgroundColor
  probe.remove()
  return colour
}, name)

/** Every tone's badge, by the colour names that map to it (the table in UPGRADE.md). */
export const TONE_NAMES = {
  neutral: ['gray', 'grey', 'zinc', 'slate', 'stone', 'neutral', 'white', 'nonsense'],
  info: ['blue', 'sky', 'cyan', 'indigo', 'info'],
  success: ['green', 'emerald', 'teal', 'lime', 'success'],
  warning: ['yellow', 'amber', 'orange', 'warning'],
  danger: ['red', 'rose', 'danger', 'error'],
  accent: ['purple', 'violet', 'fuchsia', 'pink', 'accent', 'primary'],
  inverse: ['black'],
}

export const HEXES = ['f5c518', 'ff0000', '00ff00', '0000ff', '808080', 'ffffff', '000000', '3e496b', '9f5afd']
