import { expect } from '@playwright/test'

// Shared by the V4-3 control specs: open the fixture the way a host's page renders it, and measure what a browser computes.
// Both run inside the page (Locator.evaluate serialises them), so each is self-contained.

/** Open /atom/e2e/<path> on the consumer's Tailwind. `dark` also emulates the OS scheme, as a dark-mode host does. */
export async function open(page, { path = 'controls', tw = 'latest', dark = false, density = 'compact', accent = null, section = null, extra = '' } = {}) {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.emulateMedia({ colorScheme: dark ? 'dark' : 'light' })

  const query = new URLSearchParams({ tw, density })
  if (dark) query.set('dark', '1')
  if (accent) query.set('accent', accent)
  if (section) query.set('section', section)

  await page.goto(`/atom/e2e/${path}?${query}${extra}`)
  await page.evaluate(() => document.fonts.ready)
}

/**
 * The contrast ratio of one colour of an element against the surface it is painted on: its own text colour against its own
 * background (every ancestor's fill, composited), a border against the same, or an outline against the parent's.
 * `what`: 'text' (color), 'border' (border-top-color, over the element's own fill), 'outline' (outline-color, over the parent's),
 * 'fill' (the element's own background against the parent's: a checked box, a track), or a CSS property name for a colour.
 * `pseudo` reads a pseudo-element (for a thumb: ::-webkit-slider-thumb is not reachable, so a control draws its own parts).
 */
export function contrastOf(el, { what = 'text', pseudo = null } = {}) {
  const cv = document.createElement('canvas')
  cv.width = cv.height = 1
  const g = cv.getContext('2d', { willReadFrequently: true })
  const read = () => [...g.getImageData(0, 0, 1, 1).data].slice(0, 3)
  const none = (c) => !c || c === 'transparent' || c === 'rgba(0, 0, 0, 0)'
  const paint = (c) => { g.fillStyle = '#000'; g.fillStyle = c; g.fillRect(0, 0, 1, 1) }

  const chain = []
  for (let n = el; n; n = n.parentElement) chain.unshift(n)

  // what is under the element: the page, then every ancestor's fill (the element's own only when asked)
  const under = (includeSelf) => {
    g.clearRect(0, 0, 1, 1)
    paint('#ffffff')
    for (const n of includeSelf ? chain : chain.slice(0, -1)) {
      const bg = getComputedStyle(n).backgroundColor
      if (!none(bg)) paint(bg)
    }
  }

  const s = getComputedStyle(el, pseudo)
  let colour
  let includeSelf = true

  if (what === 'text') colour = s.color
  else if (what === 'border') colour = s.borderTopColor
  else if (what === 'outline') { colour = s.outlineColor; includeSelf = false }
  else if (what === 'fill') { colour = s.backgroundColor; includeSelf = false }
  else colour = s.getPropertyValue(what)

  under(includeSelf)
  const back = read()
  paint(colour)
  const front = read()

  const lin = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4 }
  const lum = ([r, g2, b]) => 0.2126 * lin(r) + 0.7152 * lin(g2) + 0.0722 * lin(b)
  const [x, y] = [lum(front), lum(back)]

  return { ratio: (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05), front, back, colour }
}

/** The ratio of a locator's colour (see contrastOf). */
export const ratio = async (locator, what = 'text') => (await locator.evaluate(contrastOf, { what })).ratio

/** Computed style props of a locator, plus its box. */
export const style = (locator, ...props) => locator.evaluate((el, props) => {
  const s = getComputedStyle(el)
  const out = Object.fromEntries(props.map((p) => [p, s.getPropertyValue(p)]))
  const box = el.getBoundingClientRect()
  out.height = box.height
  out.width = box.width
  return out
}, props)
