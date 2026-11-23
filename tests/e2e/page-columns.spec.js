import { test, expect } from '@playwright/test'

// <atom:page.columns>: a main column and a 320px rail that sit side by side while the main column keeps at least 60% of the width and the
// rail still fits beside it (22px between them), and stack otherwise. No viewport breakpoint: it is the room the columns have, so a
// collapsed sidebar brings the rail beside the main column at a window width where it would be under it otherwise. Measured in both shells
// on the consumer's Tailwind (the rig has none), at the hosts' minimum (floor) and the newest 4.x (latest).

const GAP = 22
const RAIL = 320
// the narrowest container the rail sits beside: 60% for the main column, the gap, the rail
const SWITCH = (RAIL + GAP) / 0.4

const open = async (page, { shell, width, height = 900, query = '', collapsed = false, tw = 'latest' }) => {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.setViewportSize({ width, height })
  await page.addInitScript((on) => {
    try {
      if (on) window.localStorage.setItem('atom-sidebar-collapsed', '1')
      else window.localStorage.removeItem('atom-sidebar-collapsed')
    } catch {}
  }, collapsed)
  await page.goto(`/atom/e2e/page-columns?shell=${shell}&tw=${tw}${query}`)
  await page.evaluate(() => document.fonts.ready)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }
})

const layout = async (page) => ({
  columns: await box(page.locator('[data-atom-page-columns]')),
  main: await box(page.locator('[data-atom-page-columns-main]')),
  // none with ?rail=0
  rail: (await page.locator('[data-atom-page-columns-rail]').count()) ? await box(page.locator('[data-atom-page-columns-rail]')) : null,
})

// nudge the window until the row of columns is the width asked for (the row is the window less the shell and the gutters)
const setRowWidth = async (page, target, height = 700) => {
  let width = (await page.viewportSize()).width

  for (let n = 0; n < 4; n++) {
    const row = (await layout(page)).columns.width

    width += target - row
    await page.setViewportSize({ width: Math.round(width), height })
    await page.waitForTimeout(50)
  }

  const row = (await layout(page)).columns.width

  expect(Math.abs(row - target), `row ${row}`).toBeLessThanOrEqual(1.5)
}

const noSideScroll = (page) => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)

// beside: the rail starts after the main column ends, on the same top line; below: it starts under it
// (a sticky rail beside the main column bleeds 6px into the gap and above the row, with a negative margin: BLEED)
const BLEED = 6
const placement = ({ main, rail }) => (rail.x >= main.right - 1 && Math.abs(rail.y - main.y) <= BLEED + 1 ? 'beside' : rail.y >= main.bottom - 1 ? 'below' : 'overlap')

const expectPlacement = (l, expected, label) => {
  expect(placement(l), `${label}: ${JSON.stringify(l)}`).toBe(expected)

  if (expected === 'beside') {
    // the rail is its 320px (the main column's flex-grow takes all but a hundredth of a pixel of the free room), the gap between the columns is 22px, and the two fill the row
    expect(Math.abs(l.rail.width - RAIL), `${label}: rail width ${l.rail.width}`).toBeLessThanOrEqual(0.1)
    expect(Math.abs(l.rail.x - l.main.right - GAP), `${label}: gap`).toBeLessThanOrEqual(1)
    expect(Math.abs(l.rail.right - l.columns.right), `${label}: rail ends at the row's end`).toBeLessThanOrEqual(1)
    expect(l.main.width, `${label}: main keeps 60%`).toBeGreaterThanOrEqual(l.columns.width * 0.6 - 1)
  } else {
    // wrapped: the rail takes the whole row, 22px under the main column
    expect(Math.abs(l.rail.width - l.columns.width), `${label}: wrapped rail fills the row`).toBeLessThanOrEqual(1)
    expect(Math.abs(l.rail.y - l.main.bottom - GAP), `${label}: gap`).toBeLessThanOrEqual(1)
    expect(Math.abs(l.main.width - l.columns.width), `${label}: main fills the row`).toBeLessThanOrEqual(1)
  }
}

// [shell, window width, collapsed, expected placement]. The sidebar shell's main column is 228px narrower (its sidebar) and its page
// gutters 28px a side from lg; the top-nav shell's are 24px. A collapsed sidebar gives the room back.
const cases = [
  ['sidebar', 1440, false, 'beside'],
  ['sidebar', 1280, false, 'beside'],
  ['sidebar', 1100, false, 'below'],
  ['sidebar', 1100, true, 'beside'],
  ['sidebar', 1024, false, 'below'],
  ['sidebar', 1024, true, 'beside'],
  ['sidebar', 800, true, 'below'],
  ['sidebar', 390, false, 'below'],
  ['header', 1440, false, 'beside'],
  ['header', 1100, false, 'beside'],
  ['header', 1000, false, 'beside'],
  ['header', 880, false, 'below'],
  ['header', 640, false, 'below'],
  ['header', 390, false, 'below'],
]

for (const tw of ['floor', 'latest']) {
  test.describe(`page.columns, ${tw} consumer build`, () => {
    for (const [shell, width, collapsed, expected] of cases) {
      if (tw === 'floor' && width !== 1100 && width !== 390) continue

      test(`${shell} shell${collapsed ? ' (sidebar collapsed)' : ''} at ${width}px: the rail is ${expected} the main column`, async ({ page }) => {
        await open(page, { shell, width, collapsed, tw })

        const l = await layout(page)

        // the placement follows the room, not the window: it is beside exactly when the container is at least the switch width
        expect(placement(l) === 'beside', `container ${l.columns.width}px, switch ${SWITCH}px`).toBe(l.columns.width >= SWITCH - 1)

        expectPlacement(l, expected, `${shell} ${width}`)
        expect(await noSideScroll(page)).toBe(true)

        // the rail's cards are 14px apart
        const [a, b] = [await box(page.locator('[data-testid="rail-card-1"]')), await box(page.locator('[data-testid="rail-card-2"]'))]

        expect(Math.abs(b.y - a.bottom - 14)).toBeLessThanOrEqual(1)
        expect(Math.abs(a.width - l.rail.width)).toBeLessThanOrEqual(1)
      })
    }

    test(`the rail is a labelled aside, and the page's 22px gap sits between the main column's blocks`, async ({ page }) => {
      await open(page, { shell: 'sidebar', width: 1440, query: '&wide=1', tw })

      await expect(page.locator('aside[data-atom-page-columns-rail]')).toHaveAttribute('aria-label', 'Work order details')

      const a = await box(page.locator('[data-testid="main-block"]'))
      const b = await box(page.locator('[data-testid="wide-panel"]'))

      expect(Math.abs(b.y - a.bottom - GAP)).toBeLessThanOrEqual(1)
    })
  })
}

test.describe('page.columns, the main column and a wide table', () => {
  // wide=1 is a frameless table in a panel the page draws; wide=2 is a framed table that is a direct child of the main column
  for (const [wide, shell, width, collapsed] of [[1, 'sidebar', 1440, false], [1, 'sidebar', 1100, false], [1, 'sidebar', 1100, true], [1, 'header', 1100, false], [1, 'header', 640, false], [1, 'sidebar', 390, false], [2, 'sidebar', 1440, false], [2, 'sidebar', 1100, false], [2, 'header', 1100, false], [2, 'header', 640, false]]) {
    test(`${wide === 1 ? 'a wide frameless table' : 'a wide framed table'}, ${shell} shell${collapsed ? ' (collapsed)' : ''} at ${width}px: it scrolls inside the main column, the page never sideways`, async ({ page }) => {
      await open(page, { shell, width, collapsed, query: `&wide=${wide}` })

      const l = await layout(page)

      expect(await noSideScroll(page)).toBe(true)
      // the main column is not widened by its content: it is the same width as without the table
      expect(l.main.right, 'main stays inside the columns').toBeLessThanOrEqual(l.columns.right + 1)
      expect(l.main.width).toBeLessThanOrEqual(l.columns.width + 1)

      // the table's own scroll box holds the overflow
      const scroller = await page.locator('[data-testid="wide-panel"]').evaluate((panel) => {
        const box = [panel, ...panel.querySelectorAll('*')].find((el) => ['auto', 'scroll'].includes(getComputedStyle(el).overflowX) && el.scrollWidth > el.clientWidth + 1)

        return box ? { scrollWidth: box.scrollWidth, clientWidth: box.clientWidth, panelWidth: panel.getBoundingClientRect().width } : null
      })

      expect(scroller, 'a scroll box with overflow inside the panel').not.toBeNull()
      expect(scroller.clientWidth).toBeLessThanOrEqual(scroller.panelWidth + 1)
      expect(scroller.scrollWidth).toBeGreaterThan(scroller.clientWidth)

      // and the rail keeps its size beside or below
      const rail = await box(page.locator('[data-atom-page-columns-rail]'))

      expect(rail.right).toBeLessThanOrEqual(l.columns.right + 1)
    })
  }
})

test.describe('page.columns, content that cannot shrink', () => {
  test('the main column is a track of the room it has: a block of unbreakable content is as wide as the column, not as its content', async ({ page }) => {
    await open(page, { shell: 'sidebar', width: 1440, query: '&long=1' })

    const l = await layout(page)
    const block = await box(page.locator('[data-testid="long-block"]'))

    expect(placement(l)).toBe('beside')
    expect(Math.abs(block.width - l.main.width), `block ${block.width}, main ${l.main.width}`).toBeLessThanOrEqual(1)
  })
})

test.describe('page.columns, the sticky rail', () => {
  // what the bar leaves: the shell's variable, measured through a probe element
  const barHeight = (page) => page.evaluate(() => {
    const probe = document.createElement('div')

    probe.style.cssText = 'position:absolute;height:var(--atom-shell-bar-h,0px);width:1px'
    document.body.append(probe)

    const h = probe.getBoundingClientRect().height

    probe.remove()

    return h
  })

  // the sidebar shell's bar is not drawn while it holds nothing (the variable is 0 then), so it is run empty and with a crumb in it
  for (const [shell, crumbs] of [['sidebar', false], ['sidebar', true], ['header', false]]) {
    const name = `${shell} shell${crumbs ? ' with a bar' : ''}`

    test(`${name}: a sticky rail beside the main column stays 1rem under the bar while the page scrolls`, async ({ page }) => {
      await open(page, { shell, width: 1440, query: `&sticky=1&tall=1${crumbs ? '&crumbs=1' : ''}` })

      const bar = await barHeight(page)

      expect(bar > 0, 'the bar is drawn, and the shell says how tall').toBe(shell === 'header' || crumbs)
      expect(placement(await layout(page))).toBe('beside')

      const before = await box(page.locator('[data-atom-page-columns-rail]'))

      for (const y of [400, 900, 1500]) {
        await page.evaluate((to) => window.scrollTo(0, to), y)
        await page.waitForTimeout(50)

        const card = await box(page.locator('[data-testid="rail-card-1"]'))

        expect(Math.abs(card.y - (bar + 16)), `scrolled ${y}: first card top ${card.y}, bar ${bar}`).toBeLessThanOrEqual(1)
      }

      // it never leaves the columns: scrolled to the end, it ends with the main column
      await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))
      await page.waitForTimeout(50)

      const l = await layout(page)

      expect(l.rail.bottom - BLEED).toBeLessThanOrEqual(l.columns.bottom + 1)

      // and it never sits under the bar
      if (bar > 0) {
        const bars = await box(page.locator(shell === 'header' ? '[data-atom-header-bar]' : '[data-atom-header]'))

        expect(l.rail.y + BLEED).toBeGreaterThanOrEqual(bars.bottom)
      }

      expect(before.y).toBeGreaterThan(bar + 16 - BLEED)
    })

  }

  for (const shell of ['sidebar', 'header']) {
    test(`${shell} shell: a rail with no sticky scrolls away with the page`, async ({ page }) => {
      await open(page, { shell, width: 1440, query: '&tall=1' })

      const before = await box(page.locator('[data-atom-page-columns-rail]'))

      await page.evaluate(() => window.scrollTo(0, 900))
      await page.waitForTimeout(50)

      const after = await box(page.locator('[data-atom-page-columns-rail]'))

      expect(Math.round(before.y - after.y)).toBe(900)
    })

    test(`${shell} shell: a sticky rail that has wrapped under the main column does not leave its place`, async ({ page }) => {
      await open(page, { shell, width: 640, query: '&sticky=1&tall=1' })

      expect(placement(await layout(page))).toBe('below')

      for (const y of [0, 1200, 99999]) {
        await page.evaluate((to) => window.scrollTo(0, to), y)
        await page.waitForTimeout(50)

        const l = await layout(page)

        expect(Math.abs(l.rail.y - l.main.bottom - GAP), `scrolled ${y}`).toBeLessThanOrEqual(1)
      }
    })
  }
})

test.describe('page.columns, no rail', () => {
  test('the main column fills the row and no aside is drawn', async ({ page }) => {
    await open(page, { shell: 'sidebar', width: 1440, query: '&rail=0' })

    await expect(page.locator('aside[data-atom-page-columns-rail]')).toHaveCount(0)

    const columns = await box(page.locator('[data-atom-page-columns]'))
    const main = await box(page.locator('[data-atom-page-columns-main]'))

    expect(Math.abs(main.width - columns.width)).toBeLessThanOrEqual(1)
  })
})

test.describe('page.columns, a sticky rail taller than the window', () => {
  const query = '&sticky=1&tall=1&railtall=1'

  for (const [shell, crumbs] of [['sidebar', true], ['header', false]]) {
    test(`${shell} shell: the rail scrolls inside itself, and its last card's control is reached by scrolling it and by Tab`, async ({ page }) => {
      await open(page, { shell, width: 1440, height: 520, query: query + (crumbs ? '&crumbs=1' : '') })

      const rail = page.locator('[data-atom-page-columns-rail]')

      expect(placement(await layout(page))).toBe('beside')

      // stuck under the bar, taller than the window leaves: a scroll box, with the room the window leaves under the bar
      await page.evaluate(() => window.scrollTo(0, 300))
      await page.waitForTimeout(50)

      const state = await rail.evaluate((el) => {
        const cs = getComputedStyle(el)
        const probe = document.createElement('div')

        probe.style.cssText = 'position:absolute;height:var(--atom-shell-bar-h,0px);width:1px'
        document.body.append(probe)

        const bar = probe.getBoundingClientRect().height

        probe.remove()

        return { overflowY: cs.overflowY, overscroll: cs.overscrollBehaviorY, position: cs.position, height: el.getBoundingClientRect().height, scrollHeight: el.scrollHeight, clientHeight: el.clientHeight, bar, innerHeight: window.innerHeight }
      })

      expect(state.position).toBe('sticky')
      expect(state.overflowY).toBe('auto')
      expect(state.overscroll).toBe('contain')
      expect(state.scrollHeight, 'the rail has more than it shows').toBeGreaterThan(state.clientHeight + 100)
      // what the window leaves under the bar, 1rem above and below (the 6px bleed is part of the box)
      expect(state.height).toBeLessThanOrEqual(state.innerHeight - state.bar - 32 + 12 + 1)

      // the last card's button is below the fold of the rail until the rail is scrolled
      const last = page.locator('[data-testid="rail-btn-12"]')
      const visible = () => last.evaluate((el) => {
        const r = el.getBoundingClientRect()
        const rail = el.closest('[data-atom-page-columns-rail]').getBoundingClientRect()

        return r.top >= rail.top && r.bottom <= rail.bottom && r.bottom <= window.innerHeight
      })

      expect(await visible()).toBe(false)

      await rail.evaluate((el) => { el.scrollTop = el.scrollHeight })

      expect(await visible()).toBe(true)

      // by Tab: from the first control to the last, the focused one is scrolled into view each time
      await rail.evaluate((el) => { el.scrollTop = 0 })
      await page.locator('[data-testid="rail-btn-1"]').focus()

      for (let n = 2; n <= 12; n++) {
        await page.keyboard.press('Tab')
        await expect(page.locator(`[data-testid="rail-btn-${n}"]`)).toBeFocused()
        expect(await page.locator(`[data-testid="rail-btn-${n}"]`).evaluate((el) => {
          const r = el.getBoundingClientRect()
          const rail = el.closest('[data-atom-page-columns-rail]').getBoundingClientRect()

          // (Chrome scrolls a focused control to within a pixel of the edge)
          return r.top >= rail.top - 2 && r.bottom <= rail.bottom + 2
        }), `Tab ${n}`).toBe(true)
      }

      // the scroll stayed in the rail: the page did not move while it was reached (it is at the 300px it was put at)
      expect(await page.evaluate(() => window.scrollY)).toBe(300)
    })
  }

  test('the 6px of padding and the negative margin keep the content on the 320px and the row\'s edges', async ({ page }) => {
    await open(page, { shell: 'sidebar', width: 1440, height: 520, query })

    const l = await layout(page)
    const card = await box(page.locator('[data-testid="rail-card-1"]'))
    const style = await page.locator('[data-atom-page-columns-rail]').evaluate((el) => { const cs = getComputedStyle(el); return { padding: cs.paddingLeft, margin: cs.marginLeft } })

    expect(style).toEqual({ padding: '6px', margin: '-6px' })
    expect(Math.abs(card.width - RAIL)).toBeLessThanOrEqual(0.1)
    expect(Math.abs(card.right - l.columns.right)).toBeLessThanOrEqual(1)
    expect(Math.abs(card.x - l.rail.x - BLEED)).toBeLessThanOrEqual(0.1)
    // the main column has what it had without the bleed
    expect(Math.abs(l.rail.x + BLEED - l.main.right - GAP)).toBeLessThanOrEqual(1)
  })

  test('wrapped under the main column the rail is plain content: it does not scroll inside itself or bleed', async ({ page }) => {
    await open(page, { shell: 'header', width: 640, height: 520, query })

    expect(placement(await layout(page))).toBe('below')

    const style = await page.locator('[data-atom-page-columns-rail]').evaluate((el) => { const cs = getComputedStyle(el); return { position: cs.position, overflowY: cs.overflowY, maxHeight: cs.maxHeight, padding: cs.paddingTop, margin: cs.marginTop } })

    // it is sticky, and has nothing below it to stick against (see 'a sticky rail that has wrapped ... does not leave its place')
    expect(style).toEqual({ position: 'sticky', overflowY: 'visible', maxHeight: 'none', padding: '0px', margin: '0px' })
  })

  test('the inner scroll and the flex switch change at the same row width (855px, the default gap)', async ({ page }) => {
    await open(page, { shell: 'header', width: 900, height: 700, query })

    for (const [target, expected] of [[870, 'beside'], [860, 'beside'], [850, 'below'], [840, 'below']]) {
      await setRowWidth(page, target)

      const l = await layout(page)

      expect(placement(l), `${l.columns.width}px`).toBe(expected)

      const style = await page.locator('[data-atom-page-columns-rail]').evaluate((el) => { const cs = getComputedStyle(el); return { position: cs.position, overflowY: cs.overflowY } })

      expect(style, `${l.columns.width}px`).toEqual({ position: 'sticky', overflowY: expected === 'beside' ? 'auto' : 'visible' })
    }
  })

  test('a host gap moves the real switch: gap-8 wrapped at 870px keeps the rail inside the row, gap-4 beside at 845px still sticks', async ({ page }) => {
    // gap-8: beside needs (320 + 32) / 0.4 = 880px, so 870px is wrapped although the container query (855px) is on
    await open(page, { shell: 'header', width: 900, height: 700, query: `${query}&gap=8` })
    await setRowWidth(page, 870)

    let l = await layout(page)

    expect(placement(l)).toBe('below')
    expect(l.rail.width, `rail ${l.rail.width}, row ${l.columns.width}`).toBeLessThanOrEqual(l.columns.width + 0.5)
    expect(await noSideScroll(page)).toBe(true)

    // gap-4: beside needs (320 + 16) / 0.4 = 840px, so 845px is beside although the container query is off: no inner scroll, but it sticks
    await open(page, { shell: 'header', width: 900, height: 700, query: `${query}&gap=4` })
    await setRowWidth(page, 845)

    l = await layout(page)

    expect(placement(l)).toBe('beside')
    expect(await page.locator('[data-atom-page-columns-rail]').evaluate((el) => getComputedStyle(el).overflowY)).toBe('visible')

    const bar = await page.evaluate(() => {
      const probe = document.createElement('div')

      probe.style.cssText = 'position:absolute;height:var(--atom-shell-bar-h,0px);width:1px'
      document.body.append(probe)

      const h = probe.getBoundingClientRect().height

      probe.remove()

      return h
    })

    await page.evaluate(() => window.scrollTo(0, 700))
    await page.waitForTimeout(50)

    const card = await box(page.locator('[data-testid="rail-card-1"]'))

    expect(Math.abs(card.y - (bar + 16)), `first card top ${card.y}, bar ${bar}`).toBeLessThanOrEqual(1)
  })

  test('a wide card is clipped inside the rail, not given a scroll bar of its own', async ({ page }) => {
    await open(page, { shell: 'sidebar', width: 1440, height: 700, query: '&sticky=1&tall=1&widecard=1' })

    const style = await page.locator('[data-atom-page-columns-rail]').evaluate((el) => getComputedStyle(el).overflowX)
    const block = await box(page.locator('[data-testid="rail-wide-block"]'))
    const rail = await box(page.locator('[data-atom-page-columns-rail]'))

    // `overflow-x: clip` beside `overflow-y: auto` computes to hidden: no scroll bar, the block is cut at the rail's edge
    expect(['hidden', 'clip']).toContain(style)
    expect(block.width).toBeGreaterThan(rail.width)
    expect(await noSideScroll(page)).toBe(true)
  })
})

test.describe('page.columns, a host utility beats the base', () => {
  test('gap-8 on the tag and static on the rail win over the @layer base rules', async ({ page }) => {
    await open(page, { shell: 'sidebar', width: 1440, query: '&sticky=1&host=1' })

    const row = await page.locator('[data-atom-page-columns]').evaluate((el) => { const cs = getComputedStyle(el); return { row: cs.rowGap, column: cs.columnGap } })
    const rail = await page.locator('[data-atom-page-columns-rail]').evaluate((el) => getComputedStyle(el).position)

    expect(row).toEqual({ row: '32px', column: '32px' })
    expect(rail).toBe('static')

    // and without them the base is what applies
    await open(page, { shell: 'sidebar', width: 1440, query: '&sticky=1' })

    expect(await page.locator('[data-atom-page-columns]').evaluate((el) => getComputedStyle(el).rowGap)).toBe('22px')
    expect(await page.locator('[data-atom-page-columns-rail]').evaluate((el) => getComputedStyle(el).position)).toBe('sticky')
  })
})

// The `nav` slot (a settings screen's column of navigation). It follows the room like the rail: a 200px column beside the main column from a row 768px
// wide (22px between them), a row of its own above it below that. Beside, it is sticky under the bar like a sticky rail (6px of bleed inside, the content
// on the 200px), and `--atom-page-nav-offset` on the main column is 222px; stacked or without a nav, 0. With a rail too, the rail is beside the main column
// from 1410px (200 + 22 + 320 + 22, over 0.4) instead of 855px.
test.describe('page.columns, the nav slot', () => {
  const NAV = 200
  const NAV_SWITCH = 768
  const WITH_RAIL = (NAV + GAP + RAIL + GAP) / 0.4

  const navBox = (page) => box(page.locator('[data-atom-page-columns-nav] [data-atom-navlist]'))
  const offset = (page) => page.locator('[data-atom-page-columns-main]').evaluate((el) => getComputedStyle(el).getPropertyValue('--atom-page-nav-offset').trim())

  test('beside the main column from a row 768px wide: a 200px column, 22px between them, the offset 222px', async ({ page }) => {
    await open(page, { shell: 'header', width: 1000, query: '&nav=1&rail=0' })

    for (const target of [900, 780]) {
      await setRowWidth(page, target)

      const nav = await navBox(page)
      const l = await layout(page)

      expect(Math.abs(nav.width - NAV), `row ${l.columns.width}: nav ${nav.width}`).toBeLessThanOrEqual(0.5)
      expect(Math.abs(nav.x - l.columns.x), 'the nav starts at the row').toBeLessThanOrEqual(1)
      expect(Math.abs(l.main.x - nav.right - GAP), 'the gap between them').toBeLessThanOrEqual(1)
      expect(Math.abs(l.main.right - l.columns.right), 'the main column takes the rest').toBeLessThanOrEqual(1)
      expect(Math.abs(nav.y - l.main.y), 'on one top line').toBeLessThanOrEqual(1)
      expect(await offset(page)).toBe('222px')
    }

    expect(await page.locator('[data-atom-page-columns]').getAttribute('data-has-nav')).not.toBeNull()
    expect(await noSideScroll(page)).toBe(true)
  })

  test('a row of its own above the main column below 768px: full width, 22px over it, no offset', async ({ page }) => {
    await open(page, { shell: 'header', width: 1000, query: '&nav=1&rail=0' })

    for (const target of [760, 600, 358]) {
      await setRowWidth(page, target)

      const nav = await box(page.locator('[data-atom-page-columns-nav]'))
      const l = await layout(page)

      expect(Math.abs(nav.width - l.columns.width), `row ${l.columns.width}`).toBeLessThanOrEqual(1)
      expect(Math.abs(l.main.y - nav.bottom - GAP), 'the gap under it').toBeLessThanOrEqual(1)
      expect(Math.abs(l.main.width - l.columns.width)).toBeLessThanOrEqual(1)
      expect(await offset(page)).toBe('0px')
      expect(await noSideScroll(page)).toBe(true)
    }
  })

  test('the switch is the 768px container query: beside at 770, stacked at 766 (the row, not the window)', async ({ page }) => {
    await open(page, { shell: 'header', width: 900, query: '&nav=1&rail=0' })

    for (const [target, expected] of [[770, 'beside'], [766, 'stacked']]) {
      await setRowWidth(page, target)

      const nav = await box(page.locator('[data-atom-page-columns-nav]'))
      const l = await layout(page)

      expect(nav.bottom <= l.main.y + 1 ? 'stacked' : 'beside', `row ${l.columns.width}`).toBe(expected)
    }
  })

  test('a page.columns without a nav has no offset and no data-has-nav', async ({ page }) => {
    await open(page, { shell: 'sidebar', width: 1440, query: '&rail=0' })

    expect(await page.locator('[data-atom-page-columns]').getAttribute('data-has-nav')).toBeNull()
    expect(await offset(page)).toBe('0px')
    await expect(page.locator('[data-atom-page-columns-nav]')).toHaveCount(0)
  })

  test('nav, main and rail: all three side by side from 1410px, the rail under both below it', async ({ page }) => {
    await open(page, { shell: 'sidebar', width: 1700, query: '&nav=1&sticky=1&tall=1' })

    await setRowWidth(page, 1420, 800)

    let l = await layout(page)
    let nav = await navBox(page)

    expect(placement(l), `row ${l.columns.width}`).toBe('beside')
    expect(Math.abs(nav.width - NAV)).toBeLessThanOrEqual(0.5)
    expect(Math.abs(l.main.x - nav.right - GAP)).toBeLessThanOrEqual(1)
    expect(Math.abs(l.rail.width - (RAIL + 2 * BLEED)), 'the rail with its bleed').toBeLessThanOrEqual(0.5)
    expect(Math.abs(l.rail.right - BLEED - l.columns.right)).toBeLessThanOrEqual(1)
    expect(l.main.width, 'main keeps 60%').toBeGreaterThanOrEqual(l.columns.width * 0.6 - 1)
    expect(await page.locator('[data-atom-page-columns-rail]').evaluate((el) => getComputedStyle(el).overflowY)).toBe('auto')

    await setRowWidth(page, 1400, 800)

    l = await layout(page)
    nav = await navBox(page)

    // the nav stays beside the main column; the rail wraps under both, full width, plain content (no inner scroll or bleed)
    expect(placement(l), `row ${l.columns.width}`).toBe('below')
    expect(Math.abs(l.main.x - nav.right - GAP)).toBeLessThanOrEqual(1)
    expect(Math.abs(l.rail.width - l.columns.width)).toBeLessThanOrEqual(1)
    expect(Math.abs(l.rail.y - l.main.bottom - GAP)).toBeLessThanOrEqual(1)
    expect(await page.locator('[data-atom-page-columns-rail]').evaluate((el) => { const cs = getComputedStyle(el); return { overflowY: cs.overflowY, padding: cs.paddingTop, margin: cs.marginTop } })).toEqual({ overflowY: 'visible', padding: '0px', margin: '0px' })
    expect(WITH_RAIL).toBe(1410)
    expect(await noSideScroll(page)).toBe(true)
  })

  test('a row that is narrow wraps nav, main and rail as three rows', async ({ page }) => {
    await open(page, { shell: 'header', width: 600, query: '&nav=1&sticky=1' })

    const nav = await box(page.locator('[data-atom-page-columns-nav]'))
    const l = await layout(page)

    expect(nav.bottom).toBeLessThanOrEqual(l.main.y + 1)
    expect(l.main.bottom).toBeLessThanOrEqual(l.rail.y + 1)
  })

  for (const [shell, crumbs] of [['sidebar', true], ['header', false]]) {
    test(`${shell} shell: the nav beside the main column stays 1rem under the bar while the page scrolls, and never leaves the columns`, async ({ page }) => {
      await open(page, { shell, width: 1440, query: `&nav=1&tall=1&rail=0${crumbs ? '&crumbs=1' : ''}` })

      const bar = await page.evaluate(() => {
        const probe = document.createElement('div')

        probe.style.cssText = 'position:absolute;height:var(--atom-shell-bar-h,0px);width:1px'
        document.body.append(probe)

        const h = probe.getBoundingClientRect().height

        probe.remove()

        return h
      })

      expect(bar > 0).toBe(true)

      for (const y of [400, 900, 1500]) {
        await page.evaluate((to) => window.scrollTo(0, to), y)
        await page.waitForTimeout(50)

        const item = await box(page.locator('[data-atom-page-columns-nav] [data-atom-navlist-item]').first())

        expect(Math.abs(item.y - (bar + 16)), `scrolled ${y}: first item top ${item.y}, bar ${bar}`).toBeLessThanOrEqual(1)
      }

      await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))
      await page.waitForTimeout(50)

      const l = await layout(page)
      const nav = await box(page.locator('[data-atom-page-columns-nav]'))

      expect(nav.bottom - BLEED).toBeLessThanOrEqual(l.columns.bottom + 1)
    })
  }

  test('the 6px of padding and the negative margin keep the navlist on the 200px and the row\'s edge', async ({ page }) => {
    await open(page, { shell: 'sidebar', width: 1440, query: '&nav=1&rail=0' })

    const style = await page.locator('[data-atom-page-columns-nav]').evaluate((el) => { const cs = getComputedStyle(el); return { padding: cs.paddingLeft, margin: cs.marginLeft, position: cs.position, overflowY: cs.overflowY } })

    expect(style).toEqual({ padding: '6px', margin: '-6px', position: 'sticky', overflowY: 'auto' })
  })

  test('a nav taller than the window scrolls inside itself, and its last item is reached by Tab', async ({ page }) => {
    await open(page, { shell: 'header', width: 1440, height: 400, query: '&nav=1&rail=0&tall=1&navitems=30' })

    const nav = page.locator('[data-atom-page-columns-nav]')
    const state = await nav.evaluate((el) => ({ scrollHeight: el.scrollHeight, clientHeight: el.clientHeight, innerHeight: window.innerHeight }))

    expect(state.scrollHeight).toBeGreaterThan(state.clientHeight + 100)
    expect(state.clientHeight).toBeLessThan(state.innerHeight)

    const last = page.locator('[data-atom-page-columns-nav] [data-atom-navlist-item]').last()

    await page.evaluate(() => window.scrollTo(0, 300))
    await last.focus()
    expect(await last.evaluate((el) => { const r = el.getBoundingClientRect(); const n = el.closest('[data-atom-page-columns-nav]').getBoundingClientRect(); return r.top >= n.top - 2 && r.bottom <= n.bottom + 2 })).toBe(true)
    expect(await page.evaluate(() => window.scrollY), 'the page stayed where it was').toBe(300)
  })

  test('a host utility on the nav slot wins over the base', async ({ page }) => {
    await open(page, { shell: 'header', width: 1440, query: '&nav=1&rail=0&navhost=1' })

    expect(await page.locator('[data-atom-page-columns-nav]').evaluate((el) => getComputedStyle(el).position)).toBe('static')
  })
})
