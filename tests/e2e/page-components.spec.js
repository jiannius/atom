import { test, expect } from '@playwright/test'

// The V4-2b page components (page, page.header, page.tabs, status, stat, stat.group, empty) on the consumer Tailwind
// build (`node tests/consumer/build.mjs` first), once at the hosts' minimum Tailwind and once at the newest 4.x. The
// record page is the pattern page under resources/views/docs/patterns, so what is measured is what a host copies.
// Numbers are the approved sample's (sample/invoices.html, sample/webrock.html): sizes, weights, gaps and radii, never a
// text width, so a Linux font fallback (DejaVu) changes nothing here. Screenshots (SHOTS=<directory>) are for a human to
// compare by eye, not a gate.

async function open(page, url, { tw = 'latest', dark = false, rm = null } = {}) {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.emulateMedia({ colorScheme: dark ? 'dark' : 'light', ...(rm ? { reducedMotion: rm } : {}) })
  await page.goto(`${url}${url.includes('?') ? '&' : '?'}tw=${tw}${dark ? '&dark=1' : ''}`)
  await page.evaluate(() => document.fonts.ready)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const record = (page, query = '', opts = {}) => open(page, `/atom/e2e/shell-record${query ? `?${query}` : ''}`, opts)

const styles = (locator, props) => locator.evaluate((el, props) => {
  const s = getComputedStyle(el)
  return Object.fromEntries(props.map((p) => [p, s.getPropertyValue(p)]))
}, props)

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }
})

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

const header = (page) => page.locator('[data-atom-page-header]').first()
const title = (page) => page.locator('h1[data-atom-page-header-title]')

for (const tw of ['floor', 'latest']) {
  test.describe(`page header, ${tw} consumer build`, () => {
    test.describe('at 1280px', () => {
      test.use({ viewport: { width: 1280, height: 800 } })

      test('the title is 22px Geist at weight 590, tracked -2%, with the line height of the sample', async ({ page }) => {
        await record(page, '', { tw })

        const s = await styles(title(page), ['font-size', 'font-weight', 'line-height', 'letter-spacing', 'font-family', 'margin-top'])

        expect(s['font-size']).toBe('22px')
        expect(s['font-weight']).toBe('590')
        expect(parseFloat(s['line-height'])).toBeCloseTo(22 * 1.2, 1)
        expect(parseFloat(s['letter-spacing'])).toBeCloseTo(-0.44, 2)
        expect(s['font-family']).toContain('Geist')
        expect(s['margin-top']).toBe('0px')
      })

      test('the status pill is 22px high, 12px, fully round, with a 6px dot, beside the title (not inside it)', async ({ page }) => {
        await record(page, '', { tw })

        const pill = page.locator('[data-atom-page-header] [data-atom-status]')
        const s = await styles(pill, ['font-size', 'font-weight', 'border-top-left-radius', 'column-gap', 'padding-left'])
        const dot = await box(pill.locator('[data-atom-status-dot]'))
        const pillBox = await box(pill)
        const titleBox = await box(title(page))

        expect(pillBox.height).toBe(22)
        expect(s['font-size']).toBe('12px')
        expect(s['font-weight']).toBe('510')
        expect(parseFloat(s['border-top-left-radius'])).toBeGreaterThanOrEqual(11)
        expect(s['column-gap']).toBe('6px')
        expect(s['padding-left']).toBe('8px')
        expect([dot.width, dot.height]).toEqual([6, 6])
        // beside the title on one line, 10px from it, and centred on it
        expect(pillBox.x - titleBox.right).toBeCloseTo(10, 0)
        expect(await title(page).locator('[data-atom-status]').count()).toBe(0)
        expect(Math.abs(pillBox.y + pillBox.height / 2 - (titleBox.y + titleBox.height / 2))).toBeLessThan(2)
      })

      test('the facts row sits 6px under the title, 13px, with 16px between facts and medium values', async ({ page }) => {
        await record(page, '', { tw })

        const meta = page.locator('[data-atom-page-meta]')
        const s = await styles(meta, ['font-size', 'column-gap', 'row-gap'])
        const titleRow = await box(title(page).locator('..'))
        const metaBox = await box(meta)

        expect(s['font-size']).toBe('13px')
        expect(s['column-gap']).toBe('16px')
        expect(s['row-gap']).toBe('4px')
        expect(metaBox.y - titleRow.bottom).toBe(6)
        expect((await styles(meta.locator('b').first(), ['font-weight']))['font-weight']).toBe('510')
        // an identifier is monospace, 12px
        const mono = await styles(meta.locator('b.font-id'), ['font-family', 'font-size'])
        expect(mono['font-family']).toContain('Geist Mono')
        expect(mono['font-size']).toBe('12px')
      })

      test('the crumbs sit 8px above the title; the current one is marked and the earlier ones are links', async ({ page }) => {
        await record(page, '', { tw })

        const crumbs = page.locator('[data-atom-page-crumbs]')
        const crumbBox = await box(crumbs)
        const titleRow = await box(title(page).locator('..'))

        expect(titleRow.y - crumbBox.bottom).toBe(8)
        await expect(crumbs.locator('[aria-current="page"]')).toHaveText('WO-1042')
        await expect(crumbs.locator('a')).toHaveCount(1)
        expect(await crumbs.locator('a').getAttribute('href')).toBe('?page=jobs')
      })

      test('the actions are 6px apart on the right, and the primary one is the last', async ({ page }) => {
        await record(page, '', { tw })

        const actions = page.locator('[data-atom-page-actions]')
        const s = await styles(actions, ['column-gap', 'row-gap'])
        const primary = await box(page.getByTestId('record-primary'))
        const more = await box(page.getByRole('button', { name: 'More actions' }))
        const content = await box(page.locator('[data-atom-page]'))

        expect([s['column-gap'], s['row-gap']]).toEqual(['6px', '6px'])
        expect(more.right).toBeLessThan(primary.x)
        // the primary action ends at the page's right edge (less its gutter)
        expect(Math.abs(primary.right - (content.right - 28))).toBeLessThan(1)
      })

      test('the overflow menu opens from the ellipsis and closes on Escape', async ({ page }) => {
        await record(page, '', { tw })

        const trigger = page.getByRole('button', { name: 'More actions' })
        await trigger.click()
        await expect(page.getByRole('menuitem', { name: 'Duplicate' })).toBeVisible()
        await page.keyboard.press('Escape')
        await expect(page.getByRole('menuitem', { name: 'Duplicate' })).toBeHidden()
      })

      test('the page pads itself 26px above, 28px from the sides, and keeps a 22px gap between its blocks', async ({ page }) => {
        await record(page, '', { tw })

        const p = await styles(page.locator('[data-atom-page]'), ['padding-top', 'padding-left', 'padding-right', 'padding-bottom', 'row-gap', 'max-width'])
        const head = await box(header(page))
        const stats = await box(page.locator('[data-atom-stat-group]'))
        const tabs = await box(page.locator('[data-atom-page-tabs]'))
        const main = await styles(page.locator('[data-atom-main]'), ['padding-top', 'padding-left'])
        const mainBox = await box(page.locator('[data-atom-main]'))

        expect([p['padding-top'], p['padding-left'], p['padding-right'], p['padding-bottom']]).toEqual(['26px', '28px', '28px', '120px'])
        expect(p['row-gap']).toBe('22px')
        expect(p['max-width']).toBe('1240px')
        expect(stats.y - head.bottom).toBe(22)
        // the tabs are pulled 6px up to the stats' 22px gap (the sample's -6px): 16px
        expect(tabs.y - stats.bottom).toBe(16)
        // main itself stays unpadded: the page does it
        expect(main).toEqual({ 'padding-top': '0px', 'padding-left': '0px' })
        expect(head.y - mainBox.y).toBe(26)
      })

      test('one h1: the page header replaces the layout fallback, which is out of the tree', async ({ page }) => {
        await record(page, '', { tw })

        await expect(page.locator('h1:visible')).toHaveCount(1)
        await expect(page.locator('[data-atom-page-title]')).toHaveCSS('display', 'none')
      })

      test('a page without a page header keeps the layout fallback h1', async ({ page }) => {
        await open(page, '/atom/e2e/shell-editor', { tw })

        const fallback = page.locator('[data-atom-page-title]')
        await expect(fallback).toHaveCount(1)
        expect(await fallback.evaluate((el) => getComputedStyle(el).display)).not.toBe('none')
      })

      test('the primary action follows the status, and so does the status word', async ({ page }) => {
        for (const [status, word, action] of [
          ['draft', 'Draft', 'Schedule job'],
          ['in-progress', 'In progress', 'Mark as done'],
          ['blocked', 'Waiting for parts', 'Order parts'],
          ['done', 'Done', 'Create invoice'],
        ]) {
          await record(page, `status=${status}`, { tw })

          await expect(page.locator('[data-atom-page-header] [data-atom-status]')).toHaveText(word)
          await expect(page.getByTestId('record-primary')).toContainText(action)
          // one primary action in the header, whatever the status
          await expect(page.locator('[data-atom-page-header] [data-atom-button].bg-primary')).toHaveCount(1)
        }
      })

      test('in the top-nav shell the page lines up with the bar (24px gutter)', async ({ page }) => {
        await record(page, 'shell=header', { tw })

        const p = await styles(page.locator('[data-atom-page]'), ['padding-left', 'padding-right'])
        const head = await box(header(page))
        const brand = await box(page.locator('[data-atom-header-bar] a').first())

        expect([p['padding-left'], p['padding-right']]).toEqual(['24px', '24px'])
        expect(Math.abs(head.x - brand.x)).toBeLessThan(1)
      })

      test('the page is capped at 1240px on a wide screen, `narrow` is 980px and `full` has no cap', async ({ page }) => {
        await page.setViewportSize({ width: 2000, height: 900 })

        for (const [query, width] of [['', 1240], ['&width=narrow', 980], ['&width=full', 2000], ['&width=huge', 1240]]) {
          await open(page, `/atom/e2e/page-width?x=1${query}`, { tw })

          expect((await box(page.getByTestId('page'))).width, query || 'default').toBe(width)
        }
      })
    })

    test.describe('at 390px', () => {
      test.use({ viewport: { width: 390, height: 844 } })

      test('nothing overflows sideways; the title wraps and the actions drop under it, all in reach', async ({ page }) => {
        await record(page, '', { tw })

        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390)

        const head = await box(header(page))
        const titleBox = await box(title(page))
        const primary = await box(page.getByTestId('record-primary'))
        const edit = await box(page.locator('[data-atom-page-actions] button', { hasText: 'Edit' }))

        expect(head.x).toBe(16)
        expect(head.right).toBe(374)
        expect(titleBox.right).toBeLessThanOrEqual(374)
        // the actions wrapped under the title block, and the whole row fits the viewport
        expect(edit.y).toBeGreaterThan(titleBox.bottom)
        expect(primary.right).toBeLessThanOrEqual(374)
        await expect(page.getByTestId('record-primary')).toBeVisible()
      })

      test('the totals strip is two to a row, the tabs scroll sideways inside their own row', async ({ page }) => {
        await record(page, '', { tw })

        const stats = page.locator('[data-atom-stat]')
        const [a, b, c] = [await box(stats.nth(0)), await box(stats.nth(1)), await box(stats.nth(2))]

        expect(Math.abs(a.y - b.y)).toBeLessThan(1)
        expect(c.y).toBeGreaterThan(a.y + 1)
        expect(Math.abs(a.x - c.x)).toBeLessThan(1)
        expect((await styles(page.locator('[data-atom-page-tabs] ul'), ['overflow-x']))['overflow-x']).toBe('auto')
      })

      test('a long title wraps instead of widening the page', async ({ page }) => {
        await record(page, '', { tw })
        await title(page).evaluate((el) => { el.textContent = 'Replace the main hydraulic pump seal kit on the east loading dock crane and test it under load' })

        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390)
        expect((await box(title(page))).height).toBeGreaterThan(40)
      })
    })

    test.describe('stats', () => {
      test.use({ viewport: { width: 1280, height: 900 } })

      test('a stat is label, a 20px tabular value at 590, a 12px prefix, and sub-lines of 12px', async ({ page }) => {
        await open(page, '/atom/e2e/page-parts', { tw })

        const money = page.getByTestId('stat-money')
        const value = await styles(money.locator('[data-atom-stat-value]'), ['font-size', 'font-weight', 'letter-spacing', 'font-variant-numeric', 'line-height'])
        const prefix = await styles(money.locator('small'), ['font-size', 'font-weight', 'margin-right', 'letter-spacing'])
        const sub = await styles(money.locator('[data-atom-stat-sub]'), ['font-size'])
        const label = await styles(money.locator('span').first(), ['font-size', 'font-weight'])

        expect(value['font-size']).toBe('20px')
        expect(value['font-weight']).toBe('590')
        expect(parseFloat(value['letter-spacing'])).toBeCloseTo(-0.3, 2)
        expect(value['font-variant-numeric']).toContain('tabular-nums')
        expect(parseFloat(value['line-height'])).toBeCloseTo(26, 1)
        expect(prefix['font-size']).toBe('12px')
        expect(prefix['font-weight']).toBe('510')
        expect(prefix['margin-right']).toBe('3px')
        expect(sub['font-size']).toBe('12px')
        expect(label['font-size']).toBe('13px')
      })

      test('a stat pads 14px by 18px, and the group is one 10px-round outline with 1px hairlines between', async ({ page }) => {
        await open(page, '/atom/e2e/page-parts', { tw })

        const stat = await styles(page.getByTestId('stat-money'), ['padding-top', 'padding-left', 'border-top-width', 'border-top-left-radius'])
        const group = await styles(page.getByTestId('stats'), ['border-top-width', 'border-top-left-radius', 'column-gap', 'overflow-x'])
        const [a, b] = [await box(page.getByTestId('stat-link')), await box(page.getByTestId('stat-money'))]

        expect([stat['padding-top'], stat['padding-left']]).toEqual(['14px', '18px'])
        // inside a group a stat has no outline of its own
        expect([stat['border-top-width'], stat['border-top-left-radius']]).toEqual(['0px', '0px'])
        expect([group['border-top-width'], group['border-top-left-radius'], group['column-gap'], group['overflow-x']]).toEqual(['1px', '10px', '1px', 'hidden'])
        expect(b.x - a.right).toBe(1)
      })

      test('stats are sized by their content: no height is set, and a row grows to fill the strip', async ({ page }) => {
        await open(page, '/atom/e2e/page-parts', { tw })

        const heights = await page.locator('[data-atom-stat]').evaluateAll((els) => els.map((el) => el.style.height))
        const group = await box(page.getByTestId('stats'))
        const last = await box(page.getByTestId('stat-inverse'))

        expect(heights.every((h) => h === '')).toBe(true)
        // four stats share one row and the last ends at the strip's inner edge
        expect(Math.abs(last.right - (group.right - 1))).toBeLessThan(1)
      })

      test('a linked stat is an anchor with a hover surface; the others are not focusable', async ({ page }) => {
        await open(page, '/atom/e2e/page-parts', { tw })

        const link = page.getByTestId('stat-link')
        expect(await link.evaluate((el) => el.tagName)).toBe('A')
        expect(await page.getByTestId('stat-money').evaluate((el) => el.tagName)).toBe('DIV')

        const before = (await styles(link, ['background-color']))['background-color']
        await link.hover()
        await expect.poll(async () => (await styles(link, ['background-color']))['background-color']).not.toBe(before)

        await link.focus()
        const ring = await styles(link, ['outline-width', 'outline-style'])
        expect(ring['outline-style']).toBe('solid')
        expect(ring['outline-width']).toBe('2px')
      })

      test('a delta says its direction in a word and an arrow, and inverse flips which is good', async ({ page }) => {
        await open(page, '/atom/e2e/page-parts', { tw })

        const up = page.getByTestId('stat-money').locator('[data-atom-stat-delta]')
        const down = page.getByTestId('stat-inverse').locator('[data-atom-stat-delta]')
        const colour = (loc) => loc.evaluate((el) => getComputedStyle(el).color)

        await expect(up).toContainText('Up')
        await expect(down).toContainText('Down')
        expect(await up.locator('svg').count()).toBe(1)
        expect(await down.locator('svg').count()).toBe(1)
        // +6% on a cost is bad (inverse is not set, so up is good: green); -2 on rework with inverse is good: green too
        expect(await colour(up)).toBe(await colour(down))
        // the danger tone is the red of the token
        const danger = await page.getByTestId('stat-danger').locator('[data-atom-stat-value]').evaluate((el) => getComputedStyle(el).color)
        expect(danger).not.toBe(await colour(up))
      })
    })

    test.describe('record tabs', () => {
      test.use({ viewport: { width: 1280, height: 800 } })

      test('a tab is 10px by 10px with a 2px current underline, a 12px count, and the current one is marked', async ({ page }) => {
        await record(page, '', { tw })

        const current = page.locator('[data-atom-page-tabs] [aria-current="page"]')
        const other = page.locator('[data-atom-page-tab]').nth(1)
        const s = await styles(current, ['padding-top', 'padding-left', 'border-bottom-width', 'font-size', 'font-weight', 'column-gap'])
        const o = await styles(other, ['border-bottom-width', 'border-bottom-color'])
        const count = await styles(page.locator('[data-atom-page-tab-count]').first(), ['font-size', 'font-variant-numeric'])

        await expect(current).toHaveText('Overview')
        await expect(page.locator('[data-atom-page-tabs] [aria-current]')).toHaveCount(1)
        expect([s['padding-top'], s['padding-left'], s['border-bottom-width']]).toEqual(['10px', '10px', '2px'])
        expect([s['font-size'], s['font-weight']]).toEqual(['13px', '510'])
        expect(s['column-gap']).toBe('6px')
        expect(o['border-bottom-width']).toBe('2px')
        expect(o['border-bottom-color']).toBe('rgba(0, 0, 0, 0)')
        expect(count['font-size']).toBe('12px')
        expect(count['font-variant-numeric']).toContain('tabular-nums')
        // a row about as tall as the sample's: 10 + 19.5 + 10 + 2
        expect((await box(current)).height).toBeGreaterThanOrEqual(40)
        expect((await box(current)).height).toBeLessThanOrEqual(43)
      })

      test('the current underline is drawn 2px tall: the scrolling row does not clip it to 1px', async ({ page }) => {
        await record(page, '', { tw })

        const current = page.locator('[data-atom-page-tabs] [aria-current="page"]')
        const tab = await box(current)
        const ul = await page.locator('[data-atom-page-tabs] ul').evaluate((el) => {
          const r = el.getBoundingClientRect()
          return { bottom: r.bottom, border: parseFloat(getComputedStyle(el).borderBottomWidth) }
        })

        // the tab ends inside the row's padding box, which the overflow clip keeps, not on the border it cuts
        expect(tab.bottom).toBeLessThanOrEqual(ul.bottom - ul.border)

        // and the pixels agree: a column through the tab's bottom edge holds two dark rows, then the light hairline
        const x = Math.round(tab.x + tab.width / 2)
        const png = await page.screenshot({ clip: { x, y: Math.floor(tab.bottom) - 6, width: 1, height: 12 } })
        const dark = await page.evaluate(async (b64) => {
          const img = new Image()
          img.src = `data:image/png;base64,${b64}`
          await img.decode()
          const cv = document.createElement('canvas')
          cv.width = img.width
          cv.height = img.height
          const ctx = cv.getContext('2d')
          ctx.drawImage(img, 0, 0)
          const rows = []
          for (let y = 0; y < img.height; y++) {
            const [r, g, b] = ctx.getImageData(0, y, 1, 1).data
            rows.push(0.299 * r + 0.587 * g + 0.114 * b < 100)
          }
          return rows
        }, png.toString('base64'))

        expect(dark.filter(Boolean).length).toBe(2)
        // the two dark rows are the lowest of the tab, one row above the hairline's
        expect(dark.slice(4, 6)).toEqual([true, true])
      })

      test('a current tab that is not a link keeps its underline and aria-current', async ({ page }) => {
        await record(page, '', { tw })
        await page.locator('[data-atom-page-tab][aria-current="page"]').evaluate((el) => {
          const span = document.createElement('span')
          span.className = el.className
          span.setAttribute('aria-current', 'page')
          span.setAttribute('data-atom-page-tab', '')
          span.textContent = 'Overview'
          el.replaceWith(span)
        })

        await expect(page.locator('[data-atom-page-tabs] span[aria-current="page"]')).toHaveCSS('border-bottom-width', '2px')
      })

      test('a click moves the mark, a keyboard Tab reaches each link with a visible ring', async ({ page }) => {
        await record(page, '', { tw })

        await page.locator('[data-atom-page-tab]', { hasText: 'Tasks' }).click()
        await page.waitForURL(/tab=tasks/)
        await expect(page.locator('[data-atom-page-tabs] [aria-current="page"]')).toHaveText(/Tasks/)

        await page.locator('[data-atom-page-tab]').first().focus()
        await page.keyboard.press('Tab')
        const focused = page.locator('[data-atom-page-tab]:focus-visible')
        await expect(focused).toHaveCount(1)
        expect((await styles(focused, ['outline-width']))['outline-width']).toBe('2px')
      })
    })
  })
}

test.describe('status', () => {
  test.use({ viewport: { width: 1000, height: 900 } })

  test('a running status pulses its dot under no-preference and keeps it still under reduce', async ({ page }) => {
    await open(page, '/atom/e2e/page-parts', { rm: 'no-preference' })

    const dot = (testid) => page.getByTestId(testid).locator('[data-atom-status-dot]')
    const animations = (testid) => dot(testid).evaluate((el) => el.getAnimations().map((a) => a.animationName))

    expect(await animations('status-running')).toEqual(['atom-status-pulse'])
    expect(await animations('status-info')).toEqual([])

    await open(page, '/atom/e2e/page-parts', { rm: 'reduce' })

    expect(await animations('status-running')).toEqual([])
    // still, and fully visible: the word carries the meaning
    expect(await dot('status-running').evaluate((el) => getComputedStyle(el).opacity)).toBe('1')
    await expect(page.getByTestId('status-running')).toHaveText('Running')
  })

  test('every tone is a pill with its word, and the word reads on its fill in both themes', async ({ page }) => {
    for (const dark of [false, true]) {
      await open(page, '/atom/e2e/page-parts', { dark })

      for (const tone of ['neutral', 'info', 'success', 'warning', 'danger', 'accent', 'running']) {
        const pill = page.getByTestId(`status-${tone}`)
        await expect(pill).toHaveText(tone === 'running' ? 'Running' : tone[0].toUpperCase() + tone.slice(1))

        const s = await styles(pill, ['color', 'background-color'])
        expect(await contrastBetween(page, s.color, s['background-color']), `${tone}, ${dark ? 'dark' : 'light'}`).toBeGreaterThanOrEqual(4.5)
      }
    }
  })

  test('the accent tone reads on its soft fill for each pilot accent, in both themes', async ({ page }) => {
    for (const accent of ['#f5c518', '#9f5afd', '#4c55c9']) {
      for (const dark of [false, true]) {
        await open(page, `/atom/e2e/page-parts?accent=${encodeURIComponent(accent)}`, { dark })

        const s = await styles(page.getByTestId('status-accent'), ['color', 'background-color'])
        expect(await contrastBetween(page, s.color, s['background-color']), `${accent}, ${dark ? 'dark' : 'light'}`).toBeGreaterThanOrEqual(4.5)
      }
    }
  })
})

test.describe('empty', () => {
  test.use({ viewport: { width: 1000, height: 1400 } })

  const strokes = (page, testid) => page.getByTestId(testid).locator('[data-atom-empty-icon] path, [data-atom-empty-icon] circle, [data-atom-empty-icon] line')

  test('the large icon draws in once, about a second, under no-preference motion', async ({ page }) => {
    await open(page, '/atom/e2e/page-parts', { rm: 'no-preference' })
    // the animation starts when the page does: read it from the stroke itself
    const stroke = strokes(page, 'empty-first-use').first()

    const run = await stroke.evaluate((el) => {
      const [a] = el.getAnimations()
      return a && { name: a.animationName, duration: a.effect.getComputedTiming().duration, iterations: a.effect.getComputedTiming().iterations }
    })

    // the page may already have run it on a slow machine: then there is nothing to read, and the end state below is the proof
    if (run) {
      expect(run.name).toBe('atom-draw')
      expect(run.duration).toBe(1000)
      expect(run.iterations).toBe(1)
    }

    // it finishes and the dash is dropped, so the icon is a plain whole stroke
    await expect.poll(() => stroke.evaluate((el) => el.getAnimations().length), { timeout: 4000 }).toBe(0)
    const after = await styles(stroke, ['stroke-dasharray', 'stroke-dashoffset'])
    expect(after['stroke-dasharray']).toBe('none')
    expect(after['stroke-dashoffset']).toBe('0px')
  })

  test('the icon starts undrawn: at the first frame the stroke is dashed to nothing', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' })
    await open(page, '/atom/e2e/page-parts', { rm: 'no-preference' })

    // replay it on a fresh element, paused at the start, so the check does not race the page's own run
    const start = await page.getByTestId('empty-first-use').locator('[data-atom-empty-icon] path').first().evaluate((el) => {
      const clone = el.cloneNode(true)
      el.parentNode.appendChild(clone)
      const a = clone.getAnimations()[0]
      a.pause()
      a.currentTime = 0
      const s = getComputedStyle(clone)
      const out = { dasharray: s.strokeDasharray, offset: s.strokeDashoffset, pathLength: clone.getAttribute('pathLength') }
      a.currentTime = 500
      out.mid = parseFloat(getComputedStyle(clone).strokeDashoffset)
      clone.remove()
      return out
    })

    expect(start.pathLength).toBe('1')
    expect(start.dasharray).toBe('1px')
    expect(start.offset).toBe('1px')
    // halfway through, with an ease-out, most of it is drawn already
    expect(start.mid).toBeGreaterThan(0)
    expect(start.mid).toBeLessThan(0.5)
  })

  test('no animation at all under prefers-reduced-motion: reduce', async ({ page }) => {
    await open(page, '/atom/e2e/page-parts', { rm: 'reduce' })

    for (const testid of ['empty-first-use', 'empty-no-results', 'empty-error']) {
      const count = await strokes(page, testid).evaluateAll((els) => els.reduce((n, el) => n + el.getAnimations().length, 0))
      expect(count, testid).toBe(0)
    }

    expect((await styles(strokes(page, 'empty-first-use').first(), ['stroke-dasharray']))['stroke-dasharray']).toBe('none')
  })

  test('the sm state never animates, even under no-preference', async ({ page }) => {
    await open(page, '/atom/e2e/page-parts', { rm: 'no-preference' })

    const sm = page.getByTestId('empty-sm')
    await expect(sm.locator('[data-atom-empty-icon]')).toHaveCount(0)
    expect(await sm.locator('path').evaluateAll((els) => els.reduce((n, el) => n + el.getAnimations().length, 0))).toBe(0)
  })

  test('colour is currentColor: the accent ink for first use, the muted foreground for the others', async ({ page }) => {
    await open(page, '/atom/e2e/page-parts?accent=%234c55c9', { rm: 'reduce' })

    const colour = (testid) => page.getByTestId(testid).locator('[data-atom-empty-icon]').evaluate((el) => getComputedStyle(el).color)
    const probe = (value) => page.evaluate((v) => {
      const el = document.createElement('i')
      el.style.color = v
      document.body.appendChild(el)
      const c = getComputedStyle(el).color
      el.remove()
      return c
    }, value)

    expect(await colour('empty-first-use')).toBe(await probe('var(--atom-accent-ink)'))
    expect(await colour('empty-no-results')).toBe(await probe('var(--atom-fg-subtle)'))
    expect(await colour('empty-error')).toBe(await probe('var(--atom-fg-subtle)'))

    // the stroke follows the colour
    expect((await styles(strokes(page, 'empty-first-use').first(), ['stroke']))['stroke']).toBe(await probe('var(--atom-accent-ink)'))
  })

  test('each kind prints its next step, and the heading is 16px at 590', async ({ page }) => {
    await open(page, '/atom/e2e/page-parts', { rm: 'reduce' })

    for (const [testid, action] of [['empty-first-use', 'New job'], ['empty-no-results', 'Clear filters'], ['empty-error', 'Try again']]) {
      await expect(page.getByTestId(testid).locator('[data-atom-empty-action] [data-atom-button]')).toContainText(action)
    }

    const heading = await styles(page.getByTestId('empty-first-use').getByText('No jobs yet'), ['font-size', 'font-weight'])
    expect(heading).toEqual({ 'font-size': '16px', 'font-weight': '590' })
  })
})

test.describe('page.header in a slide-over, through a Livewire morph', () => {
  test.use({ viewport: { width: 1000, height: 800 } })

  test('the header is a level-2 form header, and a morph patches it without replacing its nodes', async ({ page }) => {
    await open(page, '/atom/e2e/page-header-slide', { rm: 'no-preference' })
    await page.waitForFunction(() => window.Livewire !== undefined)

    await page.getByTestId('open-slide').click()
    const dialog = page.locator('dialog[data-open]')
    await expect(dialog).toBeVisible()

    const head = dialog.locator('[data-atom-page-header]')
    await expect(head.locator('h2[data-atom-page-header-title]')).toHaveText('New job')
    await expect(dialog.locator('h1')).toHaveCount(0)
    expect((await styles(head.locator('h2'), ['font-size']))['font-size']).toBe('16px')
    // the slide-over's own header does not hide the page's fallback title: nothing to hide here
    await expect(head.locator('[data-atom-status]')).toHaveText('In progress')
    expect(await head.locator('[data-atom-status-dot]').evaluate((el) => el.getAnimations().map((a) => a.animationName))).toEqual(['atom-status-pulse'])

    // mark the very nodes, then re-render the component twice
    await page.evaluate(() => {
      const d = document.querySelector('dialog[data-open]')
      d.__keep = {
        header: d.querySelector('[data-atom-page-header]'),
        title: d.querySelector('[data-atom-page-header-title]'),
        status: d.querySelector('[data-atom-status]'),
        dot: d.querySelector('[data-atom-status-dot]'),
      }
    })

    await dialog.getByTestId('bump').click()
    await expect(head.locator('[data-atom-page-meta]')).toContainText('1')
    await dialog.getByTestId('bump').click()
    await expect(head.locator('[data-atom-page-meta]')).toContainText('2')

    await expect(dialog).toBeVisible()
    const same = await page.evaluate(() => {
      const d = document.querySelector('dialog[data-open]')
      const k = d.__keep

      return {
        header: d.querySelector('[data-atom-page-header]') === k.header,
        title: d.querySelector('[data-atom-page-header-title]') === k.title,
        status: d.querySelector('[data-atom-status]') === k.status,
        dot: d.querySelector('[data-atom-status-dot]') === k.dot,
      }
    })
    expect(same).toEqual({ header: true, title: true, status: true, dot: true })

    // a server-driven change to the status is patched in: the word changes and the pulse stops
    await dialog.getByTestId('finish').click()
    await expect(head.locator('[data-atom-status]')).toHaveText('Done')
    await expect(head.locator('[data-atom-status]')).not.toHaveAttribute('data-running', /.*/)
    expect(await head.locator('[data-atom-status-dot]').evaluate((el) => el.getAnimations().length)).toBe(0)
    await expect(dialog).toBeVisible()
  })

  test('the overflow menu opens in the slide-over, works, and survives a morph while it is open', async ({ page }) => {
    await open(page, '/atom/e2e/page-header-slide')
    await page.waitForFunction(() => window.Livewire !== undefined)

    await page.getByTestId('open-slide').click()
    const dialog = page.locator('dialog[data-open]')
    const meta = dialog.locator('[data-atom-page-meta]')
    const menu = dialog.locator('[data-atom-menu]')
    const trigger = dialog.getByRole('button', { name: 'More actions' })

    // opens, and an item runs its Livewire action
    await trigger.click()
    await expect(menu).toBeVisible()
    await dialog.getByTestId('menu-bump').click()
    await expect(meta).toContainText('1')
    await expect(menu).toBeHidden()

    // open again, then a morph lands while it is open: the menu stays open on the same node
    await trigger.click()
    await expect(menu).toBeVisible()
    await menu.evaluate((el) => { el.__keep = true })
    await page.evaluate(() => window.Livewire.all()[0].$wire.bump())
    await expect(meta).toContainText('2')

    await expect(menu).toBeVisible()
    expect(await menu.evaluate((el) => el.__keep === true && el.matches(':popover-open'))).toBe(true)
    await expect(dialog).toBeVisible()

    await page.keyboard.press('Escape')
    await expect(menu).toBeHidden()
  })

  test('the primary action in the slide-over header still works after a morph', async ({ page }) => {
    await open(page, '/atom/e2e/page-header-slide')
    await page.waitForFunction(() => window.Livewire !== undefined)

    await page.getByTestId('open-slide').click()
    const dialog = page.locator('dialog[data-open]')

    await dialog.getByTestId('bump').click()
    await expect(dialog.locator('[data-atom-page-meta]')).toContainText('1')
    await dialog.getByTestId('bump').click()
    await expect(dialog.locator('[data-atom-page-meta]')).toContainText('2')
  })
})

test.describe('dark', () => {
  test.use({ viewport: { width: 1280, height: 800 } })

  test('the record page in dark: text reads on the canvas, the stats and tabs keep their hairlines', async ({ page }) => {
    await record(page, 'tab=parts', { dark: true })

    const canvas = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
    for (const loc of [title(page), page.locator('[data-atom-page-meta]'), page.locator('[data-atom-stat-value]').first(), page.locator('[data-atom-page-tab]').nth(1), page.getByText('No parts on this job')]) {
      const c = (await styles(loc, ['color'])).color
      expect(await contrastBetween(page, c, canvas)).toBeGreaterThanOrEqual(4.5)
    }

    // the strip's outline is the dark hairline, not the light one
    const border = (await styles(page.locator('[data-atom-stat-group]'), ['border-top-color']))['border-top-color']
    expect(border).toMatch(/rgba\(255, 245, 225/)
  })
})

// The screenshots a human compares with the approved sample by eye. Written only when asked: SHOTS=<directory>.
if (process.env.SHOTS) {
  const shot = (page, name, full = false) => page.screenshot({ path: `${process.env.SHOTS}/${name}.png`, fullPage: full })
  const settle = (page) => page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))))

  for (const width of [1280, 390]) {
    test(`screenshot: record page header, light, ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 800 })
      await record(page, '')
      await settle(page)
      await shot(page, `record-header-${width}`)
    })
  }

  test('screenshot: record page, dark, 1280px', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await record(page, '', { dark: true })
    await shot(page, 'record-dark-1280')
  })

  for (const tab of ['parts', 'activity', 'files']) {
    test(`screenshot: record page empty state (${tab}), 1280px`, async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 })
      await record(page, `tab=${tab}`, { rm: 'reduce' })
      await shot(page, `record-empty-${tab}-1280`)
    })
  }

  test('screenshot: record page in the top-nav shell, 1280px', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await record(page, 'shell=header')
    await settle(page)
    await shot(page, 'record-top-nav-1280')
  })

  test('screenshot: statuses, stats and empty states', async ({ page }) => {
    await page.setViewportSize({ width: 1000, height: 900 })
    await open(page, '/atom/e2e/page-parts', { rm: 'reduce' })
    await shot(page, 'page-parts-light', true)
    await open(page, '/atom/e2e/page-parts', { rm: 'reduce', dark: true })
    await shot(page, 'page-parts-dark', true)
  })
}
