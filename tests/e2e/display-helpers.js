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

/**
 * Every visible piece of text under `root`, each measured against its own surface: the ones under `min`, as `{ text, ratio }`.
 * It reads the text nodes (an element's own words), skips what is hidden (the element or an ancestor is `display: none`, or
 * `visibility: hidden`) or screen-reader only (an element with no box of its own, such as `display: contents`, counts as drawn when
 * its nearest ancestor with a box is), and measures the element that owns the node, so an inline child is measured in its own colour. It fails when it finds fewer than `atLeast` of them.
 */
export async function sweepText(page, rootSelector, min = TEXT, atLeast = 3) {
  return page.evaluate(({ rootSelector, min, atLeast, src }) => {
    const measure = new Function(`return ${src}`)()
    const failures = []
    const seen = new Set()
    let measured = 0

    for (const root of document.querySelectorAll(rootSelector)) {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)

      for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        const text = node.textContent.trim()
        const el = node.parentElement

        if (!text || !el || seen.has(el) || ['SCRIPT', 'STYLE', 'TEMPLATE'].includes(el.tagName)) continue
        seen.add(el)

        // an element with no box of its own (display: contents) is drawn by the nearest ancestor that has one: measure it through that
        // one. Hidden is decided on that box: `checkVisibility` is false for a display: none ancestor too, and for visibility: hidden
        let boxed = el
        while (boxed && getComputedStyle(boxed).display === 'contents') boxed = boxed.parentElement

        const hidden = !boxed || !boxed.checkVisibility({ visibilityProperty: true }) || getComputedStyle(el).visibility === 'hidden' || el.closest('[hidden], [aria-hidden="true"], .sr-only, [popover]:not(:popover-open), template')
        if (hidden) continue

        const found = measure(el, { what: 'text' })
        measured++
        if (found.ratio < min) failures.push({ text: text.slice(0, 40), ratio: Math.round(found.ratio * 100) / 100, colour: found.colour, on: String(found.back) })
      }
    }

    // a sweep that read next to nothing proves nothing: say so
    if (measured < atLeast) failures.push({ text: `only ${measured} text nodes found under ${rootSelector}`, ratio: 0 })

    return failures
  }, { rootSelector, min, atLeast, src: contrastOf.toString() })
}
