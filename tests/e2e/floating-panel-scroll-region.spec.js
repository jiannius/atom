import { test, expect } from '@playwright/test'

// Drives /atom/e2e/floating-panel-height (resources/views/e2e/floating-panel-height.blade.php).
//
// v3.34.1 caps a panel taller than its room (max-height + overflow-y:auto) so it can be scrolled.
// The select listbox and filter panels already hold a scroll container of their own, the option
// list, so on a short window they scrolled twice: the panel, then the list inside it, and the
// search row could scroll out of sight. A direct child marked data-atom-scroll-region is now the
// ONLY thing that scrolls in a capped panel; the panel pins everything else (atom.css).
//
// What is asserted is what a user sees: with the region scrolled to its end, the last row is the
// thing under its own centre, the search box still is too, and the panel itself does not scroll.

// The rig compiles no Tailwind. These are the utilities the four panels use, written as
// Tailwind emits them, so the panels have a consumer's real shape (a search row, padded rows,
// a 24px swatch grid) and the cap bites the way it does in an app.
const CONSUMER_CSS = `
  *, ::before, ::after { box-sizing: border-box; }
  body { margin: 0; }
  .flex { display: flex; }
  .inline-flex { display: inline-flex; }
  .flex-col { flex-direction: column; }
  .items-center { align-items: center; }
  .justify-center { justify-content: center; }
  .shrink-0 { flex-shrink: 0; }
  .grow { flex-grow: 1; }
  .gap-1 { gap: 4px; }
  .gap-2 { gap: 8px; }
  .px-3 { padding-left: 12px; padding-right: 12px; }
  .pt-2 { padding-top: 8px; }
  .pb-3 { padding-bottom: 12px; }
  .py-2 { padding-top: 8px; padding-bottom: 8px; }
  .p-2 { padding: 8px; }
  .w-full { width: 100%; }
  .size-4 { width: 16px; height: 16px; }
  .size-5 { width: 20px; height: 20px; }
  .size-6 { width: 24px; height: 24px; }
  .w-6 { width: 24px; }
  .h-6 { height: 24px; }
  [data-atom-icon] > * { width: 100%; height: 100%; }
  .relative { position: relative; }
  .absolute { position: absolute; }
  .fixed { position: fixed; }
  .invisible { visibility: hidden; }
  .grid { display: grid; }
  .grid-cols-11 { grid-template-columns: repeat(11, minmax(0, 1fr)); }
  .overflow-auto { overflow: auto; }
  .max-h-\\[300px\\] { max-height: 300px; }
  .max-h-\\[400px\\] { max-height: 400px; }
  .min-w-72 { min-width: 288px; }
  [data-atom-menu] { padding: 5px; min-width: 240px; }
`

const SHORT = { width: 1366, height: 520 }

// Only the probe under test stays on the page, at a y that leaves less room than the panel needs.
async function goto (page, probe, top) {
  await page.setViewportSize(SHORT)
  await page.goto('/atom/e2e/floating-panel-height?top=' + top)
  await page.addStyleTag({ content: CONSUMER_CSS })
  await page.waitForFunction(() => window.Alpine)
  await page.evaluate((keep) => {
    for (const el of document.querySelectorAll('[data-probe]')) {
      if (el.dataset.probe !== keep) el.style.display = 'none'
    }
  }, probe)

  return page.locator(`[data-probe="${probe}"]`)
}

const SEARCH = '[data-atom-select-search]'

const cases = [
  {
    name: 'select listbox',
    probe: 'select-searchable',
    top: 100,
    open: async (root) => root.locator('[data-atom-dropdown] > button').click(),
    panel: (root) => root.locator('[popover]'),
    lastRow: '[data-atom-option]',
    pinned: SEARCH,
  },
  {
    name: 'select filter',
    probe: 'filter-searchable',
    top: 100,
    open: async (root) => root.locator('[data-atom-dropdown] > button').click(),
    panel: (root) => root.locator('[popover]'),
    lastRow: '[data-atom-option]',
    pinned: SEARCH,
  },
  {
    name: 'color input',
    probe: 'color',
    top: 100,
    open: async (root) => root.locator('[data-atom-dropdown-trigger]').click(),
    panel: (root) => root.locator('[popover]'),
    lastRow: ':scope > div',
    pinned: null,
  },
  {
    name: 'tiptap toolbar text colour',
    probe: 'text-color',
    top: 230,
    open: async (root) => root.locator('button[aria-label="Text Color"]').click(),
    panel: (root) => root.locator('[data-atom-menu]'),
    lastRow: ':scope > div',
    pinned: null,
  },
  {
    // not a popover: the mention list is positioned straight off a virtual anchor, the way the
    // editor's suggestion plugin drives it.
    name: 'tiptap mention',
    probe: 'mention',
    top: 230,
    open: async (root, page) => {
      await root.evaluate((el) => {
        const anchor = el.querySelector('[data-mention-anchor]')
        el.querySelector('.tiptap-mention').start({ query: '', clientRect: () => anchor.getBoundingClientRect() })
      })
    },
    panel: (root) => root.locator('.tiptap-mention > [x-ref="dropdown"]'),
    lastRow: ':scope > li',
    pinned: null,
  },
]

for (const c of cases) {
  test(`${c.name}: a capped panel scrolls its list, and nothing else`, async ({ page }) => {
    const root = await goto(page, c.probe, c.top)
    await c.open(root, page)

    const panel = c.panel(root)
    await expect(panel).toBeVisible()
    await expect(panel).toHaveAttribute('data-atom-capped', '')

    const result = await panel.evaluate((pop, { lastRow, pinned }) => {
      const region = pop.querySelector(':scope > [data-atom-scroll-region]')
      const hitsItself = (el) => {
        const r = el.getBoundingClientRect()
        const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
        return !!hit && el.contains(hit)
      }

      region.scrollTop = region.scrollHeight

      const rows = region.querySelectorAll(lastRow)
      const last = rows[rows.length - 1]
      const p = pop.getBoundingClientRect()

      return {
        hasRegion: !!region,
        regionScrolls: region.scrollHeight > region.clientHeight,
        regionHeight: region.clientHeight,
        rowCount: rows.length,
        lastRowReachable: hitsItself(last),
        pinnedReachable: pinned ? hitsItself(pop.querySelector(pinned)) : null,
        panelScrollTop: pop.scrollTop,
        panelScrolls: pop.scrollHeight > pop.clientHeight + 1,
        panelTop: p.top,
        panelBottom: p.bottom,
        display: getComputedStyle(pop).display,
        scrollY: window.scrollY,
      }
    }, { lastRow: c.lastRow, pinned: c.pinned })

    expect(result.hasRegion).toBe(true)
    expect(result.rowCount).toBeGreaterThan(40)
    expect(result.regionScrolls).toBe(true)

    expect(result.lastRowReachable, 'last row under its own centre').toBe(true)
    if (c.pinned) expect(result.pinnedReachable, 'search box under its own centre').toBe(true)

    // the panel is a column that fits its cap: it does not scroll as a whole
    expect(result.display).toBe('flex')
    expect(result.panelScrollTop).toBe(0)
    expect(result.panelScrolls).toBe(false)
    expect(result.panelTop).toBeGreaterThanOrEqual(0)
    expect(result.panelBottom).toBeLessThanOrEqual(SHORT.height)
    expect(result.scrollY).toBe(0)

    // the cap really bit: the list was squeezed under the 300/400px it has when nothing caps it
    expect(result.regionHeight).toBeLessThan(['mention', 'text-color'].includes(c.probe) ? 300 : 400)
  })
}

test('a capped list keeps its own scroll position through a reposition', async ({ page }) => {
  const root = await goto(page, 'select-searchable', 100)
  await root.locator('[data-atom-dropdown] > button').click()

  const panel = root.locator('[popover]')
  const region = panel.locator(':scope > [data-atom-scroll-region]')
  await expect(panel).toHaveAttribute('data-atom-capped', '')

  // Scroll to within 12px of the end. Taking the cap off for a pass measures the list at its
  // natural 400px, whose scroll limit is lower, so the browser clamps the position; and 12px
  // from the end is short of the limit once a 10px page scroll gives the capped list 10px more.
  const before = await region.evaluate((el) => {
    el.scrollTop = el.scrollHeight - el.clientHeight - 12
    return { scrollTop: el.scrollTop, naturalLimit: el.scrollHeight - 400 }
  })
  expect(before.scrollTop).toBeGreaterThan(before.naturalLimit)

  const panelTopBefore = (await panel.boundingBox()).y

  // a small page scroll makes autoUpdate reposition the open panel
  await page.evaluate(() => window.scrollTo(0, 10))
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(10)
  await expect.poll(async () => (await panel.boundingBox()).y).toBeLessThan(panelTopBefore - 5)
  await page.waitForTimeout(300)

  await expect(panel).toHaveAttribute('data-atom-capped', '')
  expect(await region.evaluate((el) => Math.round(el.scrollTop))).toBe(Math.round(before.scrollTop))
})

test('a closed capped panel stays closed: the column layout does not reopen it', async ({ page }) => {
  const root = await goto(page, 'select-searchable', 100)
  await root.locator('[data-atom-dropdown] > button').click()

  const panel = root.locator('[popover]')
  await expect(panel).toHaveAttribute('data-atom-capped', '')

  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()

  // The marker is normally gone by now (the last autoUpdate pass measures a hidden panel and
  // resets it), but one that lingers for a moment must not let display:flex, an author rule,
  // beat the UA's display:none for a closed [popover].
  expect(await panel.evaluate((el) => {
    el.setAttribute('data-atom-capped', '')
    return getComputedStyle(el).display
  })).toBe('none')
})

test('x-show on the pinned rows and the list still wins inside the column', async ({ page }) => {
  const root = await goto(page, 'select-searchable', 100)
  await root.locator('[data-atom-dropdown] > button').click()

  const panel = root.locator('[popover]')
  const region = panel.locator(':scope > [data-atom-scroll-region]')
  await expect(panel).toHaveAttribute('data-atom-capped', '')
  await expect(region).toBeVisible()

  // no option matches: Alpine hides the list with an inline display:none
  await panel.locator(SEARCH).fill('zzzz')
  await expect(region).toBeHidden()
  expect(await region.evaluate((el) => el.style.display)).toBe('none')
  // and the search row is still there to correct it
  await expect(panel.locator(SEARCH)).toBeVisible()
})

test('a capped panel with no scroll region keeps scrolling as a whole panel', async ({ page }) => {
  const root = await goto(page, 'dropdown', 100)
  await root.getByRole('button', { name: 'Many items' }).click()

  const menu = root.locator('[data-atom-menu]')
  await expect(menu).toBeVisible()
  await expect(menu).toHaveAttribute('data-atom-capped', '')

  const result = await menu.evaluate((el) => {
    el.scrollTop = el.scrollHeight
    return {
      display: getComputedStyle(el).display,
      overflowY: getComputedStyle(el).overflowY,
      scrolls: el.scrollHeight > el.clientHeight,
      scrollTop: el.scrollTop,
      childShrink: getComputedStyle(el.querySelector('[role="menuitem"]')).flexShrink,
    }
  })

  // the column layout is for panels that have a marked region only
  expect(result.display).not.toBe('flex')
  expect(result.childShrink).toBe('1')
  expect(result.overflowY).toBe('auto')
  expect(result.scrolls).toBe(true)
  expect(result.scrollTop).toBeGreaterThan(0)
})

test('a panel that fits is left untouched: no marker, no cap, no layout change', async ({ page }) => {
  await page.setViewportSize({ width: 1366, height: 900 })
  await page.goto('/atom/e2e/floating-panel-height?top=0')
  await page.addStyleTag({ content: CONSUMER_CSS })
  await page.waitForFunction(() => window.Alpine)

  const root = page.locator('[data-probe="small"]')
  await root.getByRole('button', { name: 'Few items' }).click()
  const menu = root.locator('[data-atom-menu]')
  await expect(menu).toBeVisible()

  expect(await menu.evaluate((el) => [el.hasAttribute('data-atom-capped'), el.style.maxHeight, el.style.overflowY, getComputedStyle(el).display])).toEqual([false, '', '', 'block'])
})

test('a list that fits its room is not capped, and the marker is taken off when room returns', async ({ page }) => {
  const root = await goto(page, 'color', 100)
  await root.locator('[data-atom-dropdown-trigger]').click()

  const panel = root.locator('[popover]')
  await expect(panel).toHaveAttribute('data-atom-capped', '')

  // a taller window: autoUpdate re-measures the natural height, which now fits
  await page.setViewportSize({ width: 1366, height: 900 })

  await expect.poll(() => panel.evaluate((el) => [el.hasAttribute('data-atom-capped'), el.style.maxHeight, el.style.overflowY, getComputedStyle(el).display])).toEqual([false, '', '', 'block'])
})

test('a window too short for the list falls back to scrolling the panel, with the list still usable', async ({ page }) => {
  // 160px: the room under the field cannot hold the search row plus even a few options
  await page.setViewportSize({ width: 1366, height: 160 })
  await page.goto('/atom/e2e/floating-panel-height?top=10')
  await page.addStyleTag({ content: CONSUMER_CSS })
  await page.waitForFunction(() => window.Alpine)
  await page.evaluate(() => {
    for (const el of document.querySelectorAll('[data-probe]')) {
      if (el.dataset.probe !== 'select-searchable') el.style.display = 'none'
    }
  })

  const root = page.locator('[data-probe="select-searchable"]')
  await root.locator('[data-atom-dropdown] > button').click()

  const panel = root.locator('[popover]')
  await expect(panel).toHaveAttribute('data-atom-capped', '')

  const result = await panel.evaluate((pop) => {
    const region = pop.querySelector(':scope > [data-atom-scroll-region]')
    const search = pop.querySelector('[data-atom-select-search]')
    const hitsItself = (el) => {
      const r = el.getBoundingClientRect()
      const p = pop.getBoundingClientRect()
      const cy = r.top + r.height / 2
      // the point has to be inside the panel's own box to count as reachable at all
      if (cy < p.top || cy > p.bottom) return false
      const hit = document.elementFromPoint(r.left + r.width / 2, cy)
      return !!hit && el.contains(hit)
    }

    // the panel scrolls (never the page), then the list does
    pop.scrollTop = pop.scrollHeight
    region.scrollTop = region.scrollHeight
    const rows = region.querySelectorAll('[data-atom-option]')
    const lastRowReachable = hitsItself(rows[rows.length - 1])
    const regionHeight = region.clientHeight

    pop.scrollTop = 0
    return {
      lastRowReachable,
      searchReachable: hitsItself(search),
      regionHeight,
      floor: 6 * parseFloat(getComputedStyle(document.documentElement).fontSize),
      panelScrolls: pop.scrollHeight > pop.clientHeight + 1,
      scrollY: window.scrollY,
    }
  })

  // the list keeps a floor of 6rem instead of collapsing behind the search row
  expect(result.regionHeight).toBeGreaterThanOrEqual(result.floor)
  // so what no longer fits is scrolled by the panel
  expect(result.panelScrolls).toBe(true)
  expect(result.lastRowReachable, 'last option reachable').toBe(true)
  expect(result.searchReachable, 'search box reachable').toBe(true)
  expect(result.scrollY).toBe(0)
})
