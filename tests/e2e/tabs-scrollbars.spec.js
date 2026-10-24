import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// Playwright's Chromium runs with --hide-scrollbars, so no other spec can see a scrollbar: a strip that "scrolls" looks the same
// to them with and without a 15px bar. This one launches without the flag (a bar then takes space, as on Windows and on a Mac with a
// mouse) and asserts the record's tab strip draws none: no vertical bar on the strip or any box around it up to its page section,
// and no sideways bar under the tabs (the edge fade says it scrolls). Light and dark, a phone and a desktop.

test.use({ launchOptions: { ignoreDefaultArgs: ['--hide-scrollbars'] } })

/** The space a scrollbar takes in each strip and in the boxes around it, up to its section (the bar, not a border) */
const gutters = (page, { coarse = false } = {}) => page.evaluate((coarse) => [...document.querySelectorAll('[data-atom-page-tabs]')].flatMap((nav, i) => {
  const found = []
  const stop = nav.closest('[data-testid^="where-"], [data-atom-page], main') ?? document.body

  for (let el = nav.querySelector('ul'); el && el !== document.body; el = el.parentElement) {
    const s = getComputedStyle(el)
    const vertical = el.offsetWidth - el.clientWidth - parseFloat(s.borderLeftWidth) - parseFloat(s.borderRightWidth)
    const sideways = el.offsetHeight - el.clientHeight - parseFloat(s.borderTopWidth) - parseFloat(s.borderBottomWidth)
    const isStrip = el.parentElement === nav
    const name = `strip ${i}: ${el.tagName.toLowerCase()}.${String(el.className).slice(0, 30)}`

    if (vertical > 0) found.push(`${name} has a ${vertical}px vertical bar`)
    // a strip that overflows has a slim bar under a mouse (8px at most) and none under a touch screen; one that fits has none
    const overflows = isStrip && el.scrollWidth > el.clientWidth

    if (isStrip && coarse && sideways > 0) found.push(`${name} has a ${sideways}px sideways bar under a coarse pointer`)
    if (isStrip && ! coarse && overflows && (sideways <= 0 || sideways > 8)) found.push(`${name} has a ${sideways}px sideways bar, expected a slim one (1 to 8px)`)
    if (isStrip && ! coarse && ! overflows && sideways > 0) found.push(`${name} fits and still has a ${sideways}px sideways bar`)
    if (isStrip && el.scrollHeight > el.clientHeight + 1) found.push(`${name} scrolls vertically (${el.scrollHeight} > ${el.clientHeight})`)
    if (isStrip && s.overflowY !== 'hidden') found.push(`${name} overflow-y is ${s.overflowY}`)
    if (el === stop) break
  }

  return found
}), coarse)

const pages = {
  'the docs page.tabs demo': { goto: (page, dark) => page.goto(`/atom/docs/page${dark ? '?dark=1' : ''}`) },
  'the tabs-overflow fixture': { goto: (page, dark, tw) => open(page, { path: 'tabs-overflow', tw, dark }) },
  'the record pattern page': { goto: (page, dark, tw) => open(page, { path: 'shell-record', tw, dark }) },
}

test('the rig can see a scrollbar (negative control)', async ({ page }) => {
  await page.setContent('<div id="box" style="width:100px;height:50px;overflow:scroll"><div style="width:300px;height:300px"></div></div>')

  expect(await page.locator('#box').evaluate((el) => [el.offsetWidth - el.clientWidth, el.offsetHeight - el.clientHeight])).toEqual([15, 15])
})

for (const [name, { goto }] of Object.entries(pages)) {
  for (const dark of [false, true]) {
    for (const width of [390, 1440]) {
      test(`${name}: no vertical bar and a slim sideways one, ${dark ? 'dark' : 'light'} at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 })
        await page.emulateMedia({ colorScheme: dark ? 'dark' : 'light' })
        await goto(page, dark, 'latest')
        await page.evaluate(() => document.fonts.ready)

        expect(await page.locator('[data-atom-page-tabs]').count()).toBeGreaterThan(0)
        expect(await gutters(page)).toEqual([])
      })
    }
  }
}

test('under a mouse the overflowing strip keeps a slim sideways bar (1 to 8px)', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 900 })
  await open(page, { path: 'tabs-overflow' })

  const bar = await page.getByTestId('page-tabs-bare').locator('ul').evaluate((el) => el.offsetHeight - el.clientHeight - parseFloat(getComputedStyle(el).borderBottomWidth))

  expect(bar).toBeGreaterThan(0)
  expect(bar).toBeLessThanOrEqual(8)
})

test.describe('under a coarse pointer (touch)', () => {
  // hasTouch makes Chromium report `(pointer: coarse)`; scrollbars stay the real, space-taking kind
  test.use({ hasTouch: true })

  for (const [name, { goto }] of Object.entries(pages)) {
    for (const width of [390, 1440]) {
      test(`${name}: no scrollbar at all at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 })
        await goto(page, false, 'latest')
        await page.evaluate(() => document.fonts.ready)

        expect(await page.evaluate(() => matchMedia('(pointer: coarse)').matches)).toBe(true)
        expect(await gutters(page, { coarse: true })).toEqual([])
      })
    }
  }

  test('the strip is scrollbar-width: none, and still scrolls', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 })
    await open(page, { path: 'tabs-overflow' })

    const ul = page.getByTestId('page-tabs-bare').locator('ul')

    expect(await ul.evaluate((el) => getComputedStyle(el).scrollbarWidth)).toBe('none')
    expect(await ul.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true)
  })
})

test.describe('cases that could still draw one', () => {
  const strip = (page) => page.getByTestId('page-tabs-bare').locator('ul')

  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 })
  })

  test('a tall count badge and a focused tab', async ({ page }) => {
    await open(page, { path: 'tabs-overflow' })
    await page.addStyleTag({ content: '[data-atom-page-tab-count]{font-size:20px;padding:6px}' })
    await page.keyboard.press('Tab')
    await page.keyboard.press('Tab')

    expect(await gutters(page)).toEqual([])
  })

  test('the underline sitting below the strip, and the fade mask', async ({ page }) => {
    await open(page, { path: 'tabs-overflow' })
    await page.addStyleTag({ content: '[data-atom-page-tab]{margin-bottom:-2px}' })

    // narrow enough that both edges fade once the strip is scrolled from the start
    await strip(page).evaluate((el) => { el.scrollLeft = 40 })
    await expect(strip(page)).toHaveAttribute('data-fade-start', '')
    await expect(strip(page)).toHaveAttribute('data-fade-end', '')

    expect(await gutters(page)).toEqual([])
  })

  test('a host build without the strip utilities (no pb-px, overflow-y visible) still draws none', async ({ page }) => {
    await open(page, { path: 'tabs-overflow', tw: 'floor' })
    await page.addStyleTag({ content: '.pb-px{padding-bottom:0}.overflow-y-hidden{overflow-y:visible}.overflow-x-auto{overflow-x:auto}' })

    expect(await gutters(page)).toEqual([])
  })

  test('a host class on the strip', async ({ page }) => {
    await open(page, { path: 'tabs-overflow' })
    await page.getByTestId('page-tabs-bare').evaluate((nav) => nav.classList.add('mt-4', 'bg-surface', 'rounded-lg', 'p-0'))

    expect(await gutters(page)).toEqual([])
  })
})
