import { test, expect } from '@playwright/test'

// The design tokens (resources/css/atom.tailwind.css), measured as a browser computes them. The e2e rig has no
// Tailwind, so the page opts in to the consumer build (tests/consumer/build.mjs: `node tests/consumer/build.mjs`
// first) the way a host's stylesheet does, once at the hosts' minimum Tailwind (floor) and once at the newest 4.x.
// Pest holds the static rules; this holds what the CSS adds up to. Screenshots are for a human to compare with the
// sample by eye, not a gate (font rendering differs by OS).

const ACCENTS = ['#f5c518', '#e8a011', '#1c1b18', '#e8632b', '#9f5afd', '#4c55c9', '#ffffff']
const TONES = ['neutral', 'info', 'success', 'warning', 'danger', 'accent']

async function open(page, { tw, dark = false, density = 'compact' }) {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.emulateMedia({ colorScheme: dark ? 'dark' : 'light' })
  await page.goto(`/atom/e2e/tokens?tw=${tw}&density=${density}${dark ? '&dark=1' : ''}`)
  await page.evaluate(() => document.fonts.ready)
}

const style = (page, testid, ...props) => page.getByTestId(testid).evaluate((el, props) => {
  const s = getComputedStyle(el)
  const out = Object.fromEntries(props.map((p) => [p, s.getPropertyValue(p)]))
  out.height = el.getBoundingClientRect().height
  out.width = el.getBoundingClientRect().width
  return out
}, props)

// Computed colours come back as rgb(), oklab(), oklch() or color(srgb-linear ...): a canvas turns any of them into sRGB bytes.
const contrastBetween = (page, a, b) => page.evaluate(([a, b]) => {
  const cv = document.createElement('canvas').getContext('2d', { willReadFrequently: true })
  const rgb = (value) => {
    cv.clearRect(0, 0, 1, 1)
    cv.fillStyle = '#000'
    cv.fillStyle = value
    cv.fillRect(0, 0, 1, 1)
    return [...cv.getImageData(0, 0, 1, 1).data].slice(0, 3)
  }
  const lin = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4 }
  const lum = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)
  const [x, y] = [lum(rgb(a)), lum(rgb(b))]
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)
}, [a, b])

const colorOf = (page, testid, prop) => page.getByTestId(testid).evaluate((el, prop) => getComputedStyle(el)[prop], prop)

for (const tw of ['floor', 'latest']) {
  test.describe(`tokens, ${tw} consumer build`, () => {
    test('the body is 13px Geist with the page canvas and foreground', async ({ page }) => {
      await open(page, { tw })

      const body = await page.evaluate(() => {
        const s = getComputedStyle(document.body)
        return { size: s.fontSize, family: s.fontFamily, bg: s.backgroundColor, fg: s.color, html: getComputedStyle(document.documentElement).fontSize, feature: s.fontFeatureSettings }
      })

      expect(body.size).toBe('13px')
      expect(body.family.startsWith('Geist,')).toBe(true)
      expect(body.bg).toBe('rgb(251, 250, 248)')
      expect(body.fg).toBe('rgb(27, 26, 23)')
      expect(body.html, 'the root is back at the browser default').toBe('16px')
      expect(body.feature).toContain('ss01')

      // the family is really Geist: both faces loaded from /atom/, not a fallback that merely has the name first
      const loaded = await page.evaluate(() => ({
        sans: document.fonts.check('13px Geist'),
        mono: document.fonts.check('12px "Geist Mono"'),
        faces: [...document.fonts].filter((f) => f.status === 'loaded').map((f) => f.family.replace(/"/g, '')),
      }))
      expect(loaded.faces).toEqual(expect.arrayContaining(['Geist', 'Geist Mono']))
      expect(loaded.sans && loaded.mono).toBe(true)
    })

    test('the type scale and weights', async ({ page }) => {
      await open(page, { tw })

      expect((await style(page, 'size-2xl', 'font-size', 'letter-spacing', 'font-weight', 'line-height'))).toMatchObject({ 'font-size': '22px', 'letter-spacing': '-0.44px', 'font-weight': '590' })
      expect((await style(page, 'size-xl', 'font-size'))['font-size']).toBe('18px')
      expect((await style(page, 'size-lg', 'font-size'))['font-size']).toBe('16px')
      expect((await style(page, 'size-base', 'font-size'))['font-size']).toBe('14px')
      expect((await style(page, 'size-sm', 'font-size'))['font-size']).toBe('13px')
      expect((await style(page, 'size-xs', 'font-size'))['font-size']).toBe('12px')
      expect((await style(page, 'weight-medium', 'font-weight'))['font-weight']).toBe('510')
      expect((await style(page, 'weight-semibold', 'font-weight'))['font-weight']).toBe('590')

      // an identifier is Geist Mono, 12px, -0.01em
      const id = await style(page, 'id', 'font-family', 'font-size', 'letter-spacing')
      expect(id['font-family']).toContain('Geist Mono')
      expect(id['font-size']).toBe('12px')
      expect(id['letter-spacing']).toBe('-0.12px')

      expect((await style(page, 'sans', 'font-family'))['font-family']).toContain('Geist,')
      expect((await style(page, 'mono', 'font-family'))['font-family']).toContain('Geist Mono')
    })

    test('the weights are real: 510 and 590 draw differently from 500 and 600 only because the font is variable', async ({ page }) => {
      await open(page, { tw })

      // a static font would snap 510 to 500; a variable one has an axis, so the width of a run moves between 500 and 590
      const widths = await page.evaluate(() => {
        const probe = (weight) => {
          const el = document.createElement('span')
          el.style.cssText = `position:absolute;visibility:hidden;white-space:nowrap;font:${weight} 40px Geist`
          el.textContent = 'Invoices overdue 8,888.88'
          document.body.append(el)
          const w = el.getBoundingClientRect().width
          el.remove()
          return w
        }
        return { w400: probe(400), w510: probe(510), w590: probe(590), w650: probe(650) }
      })

      expect(widths.w510).toBeGreaterThan(widths.w400)
      expect(widths.w590).toBeGreaterThan(widths.w510)
      expect(widths.w650).toBeGreaterThan(widths.w590)
    })

    test('numbers are tabular on request', async ({ page }) => {
      await open(page, { tw })

      expect((await style(page, 'digits-a', 'font-variant-numeric'))['font-variant-numeric']).toContain('tabular-nums')

      const a = (await style(page, 'digits-a', 'width')).width
      const b = (await style(page, 'digits-b', 'width')).width

      expect(a).toBeCloseTo(b, 1)

      // negative control: without tabular-nums the digits of Geist have different advance widths
      const plain = [(await style(page, 'digits-plain-a', 'width')).width, (await style(page, 'digits-plain-b', 'width')).width]
      expect(plain[0]).not.toBeCloseTo(plain[1], 1)
    })

    test('a control is 28px high at 7px radius, in a button and in an input', async ({ page }) => {
      await open(page, { tw })

      const button = await style(page, 'control-button', 'border-radius', 'font-size')
      expect(button.height).toBe(28)
      expect(button['border-radius']).toBe('7px')
      expect((await style(page, 'control-input', 'border-radius')).height).toBe(28)
      expect((await style(page, 'control-link', 'border-radius'))['border-radius']).toBe('6px')
      expect((await style(page, 'raised', 'border-radius'))['border-radius']).toBe('12px')
      expect((await style(page, 'role-canvas', 'border-radius'))['border-radius']).toBe('10px')
      expect((await style(page, 'tone-info', 'border-radius'))['border-radius']).toBe('999px')
    })

    test('a table row is at least 40px, and a two-line cell keeps its padding off the borders', async ({ page }) => {
      await open(page, { tw })

      expect((await style(page, 'row-one', 'height')).height).toBeGreaterThanOrEqual(40)

      const two = await page.getByTestId('cell-two').evaluate((cell) => {
        const range = document.createRange()
        range.selectNodeContents(cell)
        const lines = new Set([...range.getClientRects()].map((r) => Math.round(r.top)))
        const text = range.getBoundingClientRect()
        const box = cell.getBoundingClientRect()
        const row = cell.closest('tr').getBoundingClientRect()
        const s = getComputedStyle(cell)

        return { lines: lines.size, above: text.top - box.top, below: box.bottom - text.bottom, row: row.height, padTop: s.paddingTop, leading: s.lineHeight, size: s.fontSize }
      })

      expect(two.lines).toBeGreaterThanOrEqual(2)
      expect(two.row).toBeGreaterThan(40)
      // the text starts a padding below the cell's top edge and ends a padding above its bottom edge
      expect(two.above).toBeGreaterThanOrEqual(9)
      expect(two.below).toBeGreaterThanOrEqual(9)
      expect(two.padTop).toBe('9px')
      // 1.35 of 13px
      expect(parseFloat(two.leading)).toBeCloseTo(17.55, 1)
    })

    test('the focus ring is a 2px accent-ink outline, offset 1px', async ({ page }) => {
      await open(page, { tw })

      await page.getByTestId('focus-target').focus()
      await page.keyboard.press('Shift+Tab')
      await page.keyboard.press('Tab')

      const ring = await style(page, 'focus-target', 'outline-width', 'outline-style', 'outline-offset', 'outline-color')
      expect(ring['outline-width']).toBe('2px')
      expect(ring['outline-style']).toBe('solid')
      expect(ring['outline-offset']).toBe('1px')

      // the ring is the accent-ink colour, which is also what a link in accent text draws
      expect(await contrastBetween(page, ring['outline-color'], await colorOf(page, 'accent-ink', 'color'))).toBeCloseTo(1, 2)
      // and it can be seen against the canvas
      expect(await contrastBetween(page, ring['outline-color'], 'rgb(251, 250, 248)')).toBeGreaterThanOrEqual(4.5)

      // no focus ring on a mouse click
      await page.getByTestId('control-button').click()
      expect((await style(page, 'control-button', 'outline-style'))['outline-style']).toBe('none')
    })

    test('zinc and gray are the warm ramp, and muted, primary and the border default follow the roles', async ({ page }) => {
      await open(page, { tw })

      const vars = await page.evaluate(() => {
        const s = getComputedStyle(document.documentElement)
        return ['--color-zinc-50', '--color-zinc-100', '--color-zinc-950', '--color-gray-100', '--color-gray-900'].map((v) => s.getPropertyValue(v).trim())
      })
      expect(vars).toEqual(['#fbfaf8', '#f3f1ed', '#151412', '#f3f1ed', '#1c1b19'])

      // the border default (Preflight's currentColor) is the hairline, not the text colour
      const border = await page.getByTestId('role-canvas').evaluate((el) => getComputedStyle(el).borderTopColor)
      expect(border).toBe('rgba(45, 32, 8, 0.1)')
    })

    test('the dark variant follows the .dark class and the role tokens flip with it', async ({ page }) => {
      await open(page, { tw })
      expect(await colorOf(page, 'dark-variant', 'backgroundColor')).toBe('rgb(255, 255, 255)')
      expect(await page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(251, 250, 248)')

      await open(page, { tw, dark: true })
      expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(true)
      expect(await colorOf(page, 'dark-variant', 'backgroundColor')).toBe('rgb(0, 0, 0)')

      const dark = await page.evaluate(() => {
        const s = getComputedStyle(document.body)
        return { bg: s.backgroundColor, fg: s.color, scheme: getComputedStyle(document.documentElement).colorScheme }
      })
      expect(dark).toEqual({ bg: 'rgb(21, 20, 18)', fg: 'rgb(236, 235, 231)', scheme: 'dark' })
    })

    for (const dark of [false, true]) {
      test(`every tone pair is at least 4.5:1 in ${dark ? 'dark' : 'light'}`, async ({ page }) => {
        await open(page, { tw, dark })

        for (const tone of TONES) {
          const ratio = await contrastBetween(page, await colorOf(page, `tone-${tone}`, 'color'), await colorOf(page, `tone-${tone}`, 'backgroundColor'))
          expect(ratio, `${tone} pill`).toBeGreaterThanOrEqual(4.5)
        }

        for (const role of ['canvas', 'surface', 'surface-sunken', 'surface-raised']) {
          for (const selector of ['.font-medium', '.text-fg-muted', '.text-fg-subtle']) {
            const [fg, bg] = await page.getByTestId(`role-${role}`).evaluate((el, selector) => [getComputedStyle(el.querySelector(selector)).color, getComputedStyle(el).backgroundColor], selector)
            expect(await contrastBetween(page, fg, bg), `${selector} on ${role}`).toBeGreaterThanOrEqual(4.5)
          }
        }
      })

      test(`the derived accent colours hold for every pilot accent in ${dark ? 'dark' : 'light'}`, async ({ page }) => {
        await open(page, { tw, dark })

        for (const accent of ACCENTS) {
          await page.evaluate((a) => document.documentElement.style.setProperty('--color-accent', a), accent)

          const canvas = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
          const on = await contrastBetween(page, await colorOf(page, 'accent-button', 'color'), await colorOf(page, 'accent-button', 'backgroundColor'))
          const hover = await contrastBetween(page, await colorOf(page, 'accent-button', 'color'), await colorOf(page, 'accent-hover', 'backgroundColor'))
          const ink = await contrastBetween(page, await colorOf(page, 'accent-ink', 'color'), canvas)
          const soft = await contrastBetween(page, await colorOf(page, 'tone-accent', 'color'), await colorOf(page, 'tone-accent', 'backgroundColor'))

          expect(on, `foreground on ${accent}`).toBeGreaterThanOrEqual(4.5)
          expect(hover, `foreground on the hover of ${accent}`).toBeGreaterThanOrEqual(4.5)
          expect(ink, `accent ink of ${accent} on the canvas`).toBeGreaterThanOrEqual(4.5)
          expect(soft, `accent tone pair of ${accent}`).toBeGreaterThanOrEqual(4.5)
        }
      })
    }

    test('the accent is the host colour, and the host can override any derived token', async ({ page }) => {
      await open(page, { tw })
      await page.evaluate(() => document.documentElement.style.setProperty('--color-accent', '#e8632b'))

      expect(await colorOf(page, 'accent-button', 'backgroundColor')).toBe('rgb(232, 99, 43)')

      await page.evaluate(() => document.documentElement.style.setProperty('--color-accent-foreground', 'rgb(255, 0, 255)'))
      expect(await colorOf(page, 'accent-button', 'color')).toBe('rgb(255, 0, 255)')
    })

    test('the page keeps reading the same without the opt-in (negative control: no tokens)', async ({ page }) => {
      await page.goto('/atom/e2e/tokens')
      await page.evaluate(() => document.fonts.ready)

      // atom.css alone carries the font faces and no Tailwind: the body is not the 13px token size
      expect(await page.evaluate(() => getComputedStyle(document.body).fontSize)).toBe('16px')
    })
  })
}

// The screenshots a human compares with the approved sample by eye. Written only when asked: SHOTS=<directory>.
if (process.env.SHOTS) {
  for (const dark of [false, true]) {
    for (const density of ['compact', 'comfortable']) {
      test(`screenshot: token sheet, ${dark ? 'dark' : 'light'}, ${density}`, async ({ page }) => {
        await page.setViewportSize({ width: 1100, height: 900 })
        await open(page, { tw: 'latest', dark, density })
        await page.screenshot({ path: `${process.env.SHOTS}/tokens-${dark ? 'dark' : 'light'}-${density}.png`, fullPage: true })
      })
    }
  }
}
