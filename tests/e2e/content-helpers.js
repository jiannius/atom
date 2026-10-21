import { expect } from '@playwright/test'
import { open, ratio, style, gate, token, contrastOf, sweepText, ACCENTS, TEXT, GRAPHIC } from './display-helpers.js'

// Shared by the V4-3d specs (text, editor, chart): the V4-3a/3b helpers, plus a way to open the content sheet
// (resources/views/e2e/content.blade.php) and to wait for what is drawn by script.

export { open, ratio, style, gate, token, contrastOf, sweepText, ACCENTS, TEXT, GRAPHIC }

/** Open the content sheet. */
export const openContent = (page, options = {}) => open(page, { path: 'content', ...options })

/** A role token as `#rrggbb` (or `rgba()`), the way the chart resolves it, so a series can be compared with the token it reads. */
export const tokenHex = (page, name) => page.evaluate((n) => {
  const probe = document.createElement('span')
  probe.style.cssText = `position: absolute; color: var(--atom-${n})`
  document.body.append(probe)
  const css = getComputedStyle(probe).color
  probe.remove()

  const g = document.createElement('canvas').getContext('2d', { willReadFrequently: true })
  g.fillStyle = css
  g.fillRect(0, 0, 1, 1)
  const [r, gr, b] = g.getImageData(0, 0, 1, 1).data

  return '#' + [r, gr, b].map((c) => c.toString(16).padStart(2, '0')).join('')
}, name)

/** An SVG paint as `#rrggbb`, whatever syntax Apex wrote it in. */
export const hexOf = (locator, property) => locator.evaluate((el, p) => {
  const g = document.createElement('canvas').getContext('2d', { willReadFrequently: true })
  g.fillStyle = getComputedStyle(el)[p]
  g.fillRect(0, 0, 1, 1)
  const [r, gr, b] = g.getImageData(0, 0, 1, 1).data

  return '#' + [r, gr, b].map((c) => c.toString(16).padStart(2, '0')).join('')
}, property)

/**
 * Wait until THIS chart is drawn: Apex has put its canvas in the element and its series is there (a bar, or the area's path), and
 * the colours are applied (`setColors()` runs one tick after the render, so a path with Apex's default blue is not drawn yet).
 */
export async function chartReady(page, testid, type = 'bar') {
  const root = page.getByTestId(testid)
  const shape = type === 'bar' ? '.apexcharts-bar-area' : '.apexcharts-area'

  await expect(root.locator('.apexcharts-canvas')).toBeVisible()
  const first = root.locator(shape).first()
  await expect(first).toBeAttached()
  // the default series colour of Apex is #008FFB: a chart that reads the tokens is never that
  await expect.poll(() => first.evaluate((el) => (getComputedStyle(el).fill + getComputedStyle(el).stroke).toLowerCase())).not.toContain('rgb(0, 143, 251)')
  await page.waitForTimeout(250)

  return { root, series: first }
}
