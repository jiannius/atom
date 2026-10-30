import { test, expect } from '@playwright/test'

// The theme switcher in the shells' account menu, and the sidebar's collapse button. On the consumer Tailwind build
// (`node tests/consumer/build.mjs` first).

async function open (page, { shell = 'sidebar', tw = 'latest', query = '' } = {}) {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/shell-account?tw=${tw}&shell=${shell}${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const isDark = (page) => page.evaluate(() => document.documentElement.classList.contains('dark'))

for (const shell of ['sidebar', 'header']) {
  test.describe(`${shell} shell: the theme row`, () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 })
      // a clean slate once per tab: a reload keeps what the page stored
      await page.addInitScript(() => {
        try {
          if (!window.sessionStorage.getItem('st-clean')) {
            window.sessionStorage.setItem('st-clean', '1')
            window.localStorage.removeItem('darkmode')
          }
        } catch {}
      })
    })

    test('picking a theme applies it and keeps the account menu open', async ({ page }) => {
      await open(page, { shell, query: '&dark=1' })

      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()

      const row = page.locator('[data-atom-theme-row]:visible')
      await expect(row).toBeVisible()
      await expect(row.getByRole('group', { name: 'Theme' })).toBeVisible()

      const pressed = async () => ({
        light: await row.getByRole('menuitemradio', { name: 'Light' }).getAttribute('aria-checked'),
        dark: await row.getByRole('menuitemradio', { name: 'Dark' }).getAttribute('aria-checked'),
        system: await row.getByRole('menuitemradio', { name: 'System' }).getAttribute('aria-checked'),
      })

      expect(await pressed()).toEqual({ light: 'false', dark: 'false', system: 'true' })

      await row.getByRole('menuitemradio', { name: 'Dark' }).click()

      expect(await isDark(page)).toBe(true)
      await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeVisible()
      await expect(row).toBeVisible()
      expect(await pressed()).toEqual({ light: 'false', dark: 'true', system: 'false' })

      await row.getByRole('menuitemradio', { name: 'Light' }).click()
      expect(await isDark(page)).toBe(false)
      await expect(row).toBeVisible()
      expect(await pressed()).toEqual({ light: 'true', dark: 'false', system: 'false' })
    })

    test('the choice survives a reload, and the row shows it', async ({ page }) => {
      await open(page, { shell, query: '&dark=1' })

      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      await page.locator('[data-atom-theme-row]:visible').getByRole('menuitemradio', { name: 'Dark' }).click()
      expect(await isDark(page)).toBe(true)

      await page.reload()
      await page.waitForFunction(() => window.Alpine !== undefined)

      expect(await isDark(page)).toBe(true)
      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      await expect(page.locator('[data-atom-theme-row]:visible').getByRole('menuitemradio', { name: 'Dark' })).toHaveAttribute('aria-checked', 'true')
    })

    test('Tab reaches the options, which are menu radios, and Space or Enter picks one with the menu still open', async ({ page }) => {
      await open(page, { shell, query: '&dark=1' })

      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      const row = page.locator('[data-atom-theme-row]:visible')
      await expect(row.getByRole('menuitemradio')).toHaveCount(3)

      const light = row.getByRole('menuitemradio', { name: 'Light' })
      let reached = false
      for (let i = 0; i < 12 && !reached; i++) {
        await page.keyboard.press('Tab')
        reached = await light.evaluate((el) => el === document.activeElement)
      }
      expect(reached, 'Tab got to the first option').toBe(true)

      await page.keyboard.press('Enter')
      expect(await isDark(page)).toBe(false)
      await expect(light).toHaveAttribute('aria-checked', 'true')

      await page.keyboard.press('Tab')
      await page.keyboard.press('Space')
      expect(await isDark(page)).toBe(true)
      await expect(row.getByRole('menuitemradio', { name: 'Dark' })).toHaveAttribute('aria-checked', 'true')
      await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeVisible()
    })

    test('there is no row, and no toggle, with dark mode off', async ({ page }) => {
      await open(page, { shell })

      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeVisible()
      await expect(page.locator('[data-atom-theme-row]')).toHaveCount(0)
      await expect(page.locator('[data-atom-darkmode-toggle]')).toHaveCount(0)
    })
  })
}

test.describe('theme-switcher="false"', () => {
  for (const shell of ['sidebar', 'header']) {
    test(`${shell} shell: atom draws no switcher, and the host's own control drives window.darkmode()`, async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 })
      await page.addInitScript(() => { try { window.localStorage.removeItem('darkmode') } catch {} })
      await open(page, { shell, query: '&dark=1&own=1' })

      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeVisible()
      await expect(page.locator('[data-atom-theme-row]')).toHaveCount(0)
      await expect(page.locator('[data-atom-darkmode-toggle]')).toHaveCount(0)
      await page.keyboard.press('Escape')

      const own = page.locator('[data-own-switcher]')
      await expect(own.getByRole('button', { name: 'Own system' })).toHaveAttribute('aria-pressed', 'true')

      await own.getByRole('button', { name: 'Own dark' }).click()
      expect(await isDark(page)).toBe(true)
      await expect(own.getByRole('button', { name: 'Own dark' })).toHaveAttribute('aria-pressed', 'true')
      await expect(own.getByRole('button', { name: 'Own system' })).toHaveAttribute('aria-pressed', 'false')
    })
  }
})

test.describe('window.darkmode()', () => {
  test('returns the choice, and the event carries the choice, never the resolved scheme', async ({ page }) => {
    // the visitor's OS is dark: a "system" choice resolves to dark, and must still read as "system"
    await page.emulateMedia({ colorScheme: 'dark' })
    await open(page, { query: '&dark=1' })

    const run = () => page.evaluate(() => {
      const seen = []
      document.addEventListener('darkmode-changed', (e) => seen.push(e.detail))

      return {
        initial: window.darkmode(),
        dark: window.darkmode('dark'),
        afterDark: window.darkmode(),
        light: window.darkmode('light'),
        afterLight: window.darkmode(),
        system: window.darkmode('system'),
        afterSystem: window.darkmode(),
        resolvedDark: document.documentElement.classList.contains('dark'),
        stored: window.localStorage.getItem('darkmode'),
        seen,
      }
    })

    const result = await run()

    expect(result).toEqual({
      initial: 'system',
      dark: 'dark',
      afterDark: 'dark',
      light: 'light',
      afterLight: 'light',
      system: 'system',
      afterSystem: 'system',
      resolvedDark: true,
      stored: null,
      // only the three explicit calls tell the page; reading is silent
      seen: ['dark', 'light', 'system'],
    })
  })

  test('with no argument it only reads: no event, no write, no change to the page, and anything stored but light or dark reads as system', async ({ page }) => {
    await open(page, { query: '&dark=1' })

    const result = await page.evaluate(() => {
      const seen = []
      document.addEventListener('darkmode-changed', (e) => seen.push(e.detail))
      const writes = []
      const setItem = Storage.prototype.setItem
      const removeItem = Storage.prototype.removeItem
      Storage.prototype.setItem = function (...args) { writes.push(['set', ...args]); return setItem.apply(this, args) }
      Storage.prototype.removeItem = function (...args) { writes.push(['remove', ...args]); return removeItem.apply(this, args) }

      const out = { reads: [] }
      for (const stored of [null, 'light', 'dark', 'garbage', 'DARK']) {
        // set up with the original methods, so only what darkmode() itself does is recorded
        if (stored === null) removeItem.call(window.localStorage, 'darkmode')
        else setItem.call(window.localStorage, 'darkmode', stored)
        out.reads.push(window.darkmode())
      }

      // the class on <html> stays whatever it was
      document.documentElement.classList.remove('dark')
      window.darkmode()
      out.darkClass = document.documentElement.classList.contains('dark')

      Storage.prototype.setItem = setItem
      Storage.prototype.removeItem = removeItem
      return { ...out, seen, writes }
    })

    expect(result.reads).toEqual(['system', 'light', 'dark', 'system', 'system'])
    expect(result.seen).toEqual([])
    expect(result.writes).toEqual([])
    expect(result.darkClass).toBe(false)
  })

  test('the theme row starts from the stored choice', async ({ page }) => {
    await page.addInitScript(() => { try { window.localStorage.setItem('darkmode', 'light') } catch {} })
    await page.setViewportSize({ width: 1280, height: 800 })
    await open(page, { query: '&dark=1' })

    await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
    await expect(page.locator('[data-atom-theme-row]:visible').getByRole('menuitemradio', { name: 'Light' })).toHaveAttribute('aria-checked', 'true')
  })
})

// The sidebar's collapse button (layouts.sidebar, from lg up).

const goCollapse = async (page, { page: slug = 'one', query = '' } = {}) => {
  const css = await page.request.get('/atom/e2e/consumer-css/latest')
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/shell-collapse/${slug}?tw=latest${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const sidebarShown = (page) => page.evaluate(() => {
  const el = document.querySelector('[data-atom-sidebar]')
  const r = el.getBoundingClientRect()
  return getComputedStyle(el).visibility === 'visible' && r.right > 0
})
const collapseButton = (page) => page.locator('[data-atom-sidebar-collapse]')
const circle = (page) => page.locator('[data-atom-sidebar-circle]')
const barHeight = (page) => page.locator('[data-atom-header]').evaluate((el) => el.getBoundingClientRect().height)
const stash = (page) => page.evaluate(() => window.localStorage.getItem('atom-sidebar-collapsed'))

test.describe('the sidebar collapse button', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    // a clean slate once per tab: a reload keeps what the page stored
    await page.addInitScript(() => {
      try {
        if (!window.sessionStorage.getItem('st-clean')) {
          window.sessionStorage.setItem('st-clean', '1')
          window.localStorage.removeItem('atom-sidebar-collapsed')
        }
      } catch {}
    })
  })

  test('hides and shows the sidebar, and the page takes the room', async ({ page }) => {
    await goCollapse(page, { query: '&crumbs=1' })

    const button = collapseButton(page)
    await expect(button).toBeVisible()
    await expect(button).toHaveAttribute('aria-expanded', 'true')
    await expect(button).toHaveAttribute('aria-label', 'Sidebar')
    await expect(button).toHaveAttribute('title', /Toggle sidebar/)
    await expect(button).toHaveAttribute('aria-controls', 'atom-sidebar')
    await expect(page.locator('#atom-sidebar')).toHaveCount(1)
    await expect.poll(() => sidebarShown(page)).toBe(true)

    const mainBefore = await page.locator('[data-atom-main]').boundingBox()
    expect(mainBefore.x).toBeGreaterThan(200)

    await button.click()

    await expect(button).toHaveAttribute('aria-expanded', 'false')
    // the name does not flip, the state is aria-expanded
    await expect(button).toHaveAttribute('aria-label', 'Sidebar')
    await expect.poll(() => sidebarShown(page)).toBe(false)
    expect(await stash(page)).toBe('1')

    const mainAfter = await page.locator('[data-atom-main]').boundingBox()
    expect(mainAfter.x).toBe(0)
    expect(mainAfter.width).toBeGreaterThan(mainBefore.width + 200)
    // the header spans the page too
    expect((await page.locator('[data-atom-header]').boundingBox()).x).toBe(0)

    // a hidden sidebar is out of the tab order
    await expect(page.getByRole('link', { name: 'Page two' })).toHaveCount(0)

    await button.click()
    await expect(button).toHaveAttribute('aria-expanded', 'true')
    await expect(button).toHaveAttribute('aria-label', 'Sidebar')
    await expect.poll(() => sidebarShown(page)).toBe(true)
    expect(await stash(page)).toBeNull()
    expect((await page.locator('[data-atom-main]').boundingBox()).x).toBeGreaterThan(200)
  })

  test('Ctrl and Cmd + B toggle it, except from a field or an editor', async ({ page }) => {
    await goCollapse(page, { query: '&crumbs=1' })
    const button = collapseButton(page)

    await page.keyboard.press('Control+b')
    await expect(button).toHaveAttribute('aria-expanded', 'false')
    await page.keyboard.press('Meta+b')
    await expect(button).toHaveAttribute('aria-expanded', 'true')

    await page.locator('[data-field]').focus()
    await page.keyboard.press('Control+b')
    await expect(button).toHaveAttribute('aria-expanded', 'true')

    await page.locator('[data-editor]').focus()
    await page.keyboard.press('Control+b')
    await expect(button).toHaveAttribute('aria-expanded', 'true')

    // other chords are not ours
    await page.locator('body').click({ position: { x: 700, y: 400 } })
    await page.keyboard.press('Control+Shift+b')
    await page.keyboard.press('Control+Alt+b')
    await page.keyboard.press('b')
    await expect(button).toHaveAttribute('aria-expanded', 'true')
  })

  test('is only there from lg up', async ({ page }) => {
    await page.setViewportSize({ width: 800, height: 800 })
    await goCollapse(page, { query: '&crumbs=1' })

    await expect(collapseButton(page)).toBeHidden()
    await expect(page.locator('[data-atom-sidebar-toggle]')).toBeVisible()

    await page.keyboard.press('Control+b')
    expect(await page.evaluate(() => document.documentElement.hasAttribute('data-atom-sidebar-collapsed'))).toBe(false)
    expect(await stash(page)).toBeNull()
  })

  test('a bar with nothing in it is not drawn while the sidebar is shown: the round button on the sidebar\'s edge is the toggle', async ({ page }) => {
    await goCollapse(page)

    expect(await barHeight(page)).toBe(0)
    await expect(collapseButton(page)).toBeHidden()
    await expect(circle(page)).toBeVisible()
    await expect(circle(page)).toHaveAttribute('aria-expanded', 'true')
    await expect(circle(page)).toHaveAttribute('aria-label', 'Sidebar')
    await expect(circle(page)).toHaveAttribute('aria-controls', 'atom-sidebar')
    await expect(circle(page)).toHaveAttribute('title', /Toggle sidebar/)

    await circle(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(false)

    // collapsed: the bar is always drawn, with the plain button that brings the sidebar back; the circle is gone (not focusable)
    expect(await barHeight(page)).toBe(48)
    await expect(collapseButton(page)).toBeVisible()
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'false')
    await expect(circle(page)).toBeHidden()
    expect(await circle(page).evaluate((el) => getComputedStyle(el).display)).toBe('none')
    const plain = await collapseButton(page).evaluate((el) => ({ border: getComputedStyle(el).borderTopWidth, shadow: getComputedStyle(el).boxShadow, radius: parseFloat(getComputedStyle(el).borderTopLeftRadius) }))
    expect(plain.border).toBe('0px')
    expect(plain.shadow).toBe('none')
    // and the page starts under it
    expect((await page.locator('[data-atom-main]').boundingBox()).y).toBeGreaterThanOrEqual(48)

    await collapseButton(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)
    expect(await barHeight(page)).toBe(0)
    await expect(circle(page)).toBeVisible()
  })

  test('a bar that has crumbs or actions is drawn either way, with the plain button in both states and no circle', async ({ page }) => {
    await goCollapse(page, { query: '&crumbs=1' })

    expect(await barHeight(page)).toBe(48)
    await expect(circle(page)).toBeHidden()
    await expect(collapseButton(page)).toBeVisible()
    const plain = () => collapseButton(page).evaluate((el) => ({ border: getComputedStyle(el).borderTopWidth, shadow: getComputedStyle(el).boxShadow }))
    expect(await plain()).toEqual({ border: '0px', shadow: 'none' })

    await collapseButton(page).click()
    expect(await barHeight(page)).toBe(48)
    await expect(page.locator('[data-crumb]')).toBeVisible()
    await expect(circle(page)).toBeHidden()
    expect(await plain()).toEqual({ border: '0px', shadow: 'none' })

    await goCollapse(page, { query: '&actions=1' })
    await expect(circle(page)).toBeHidden()
    await expect(collapseButton(page)).toBeVisible()
  })

  // the circle: 28px, on the sidebar's border (half in, half out), on the line of the caret (or of the brand row when there is none)
  for (const [name, query, target] of [
    ['the default brand row: the caret', '', '[data-atom-shell-brand] [data-atom-account-trigger] [data-atom-icon]'],
    ['a host brand slot: the caret button beside it', '&brand=1', '[data-atom-shell-brand] [data-atom-account-trigger]'],
    ['account="end": the logo tile of the brand row', '&account=end', 'a[href="/app"] > div'],
  ]) {
    test(`the circle sits on the sidebar\'s edge, on the line of ${name}`, async ({ page }) => {
      await goCollapse(page, { query })

      const geometry = () => page.evaluate((selector) => {
        const rect = (el) => el.getBoundingClientRect()
        const c = rect(document.querySelector('[data-atom-sidebar-circle]'))
        const sidebar = rect(document.querySelector('[data-atom-sidebar]'))
        const mark = rect(document.querySelector(selector))
        return {
          size: [Math.round(c.width), Math.round(c.height)],
          dy: (c.top + c.height / 2) - (mark.top + mark.height / 2),
          dx: (c.left + c.width / 2) - (sidebar.right - 0.5),
          straddles: c.left < sidebar.right - 1 && c.right > sidebar.right + 1,
          style: getComputedStyle(document.querySelector('[data-atom-sidebar-circle]')).borderTopWidth,
        }
      }, target)

      const normal = await geometry()
      expect(normal.size).toEqual([28, 28])
      expect(Math.abs(normal.dy)).toBeLessThanOrEqual(2)
      expect(Math.abs(normal.dx)).toBeLessThanOrEqual(1.5)
      expect(normal.straddles).toBe(true)
      expect(normal.style).toBe('1px')

      // a host-widened sidebar: the edge moves, the circle follows
      await page.addStyleTag({ content: '[data-atom-sidebar] { width: 300px }' })
      const wide = await geometry()
      expect(Math.abs(wide.dy)).toBeLessThanOrEqual(2)
      expect(Math.abs(wide.dx)).toBeLessThanOrEqual(1.5)
      expect(wide.straddles).toBe(true)
    })
  }

  test('the circle is above the page and the bar, in the dark theme too, and below an open account menu', async ({ page }) => {
    await page.addInitScript(() => { try { window.localStorage.setItem('darkmode', 'dark') } catch {} })
    await goCollapse(page, { query: '&dark=1' })
    expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(true)

    // the canvas colour is the circle's fill, and it is not the sidebar's
    const fills = await page.evaluate(() => {
      const probe = document.createElement('div')
      probe.className = 'bg-canvas border-border'
      document.body.appendChild(probe)
      const style = getComputedStyle(probe)
      const out = { canvas: style.backgroundColor, border: style.borderTopColor }
      probe.remove()
      const c = getComputedStyle(document.querySelector('[data-atom-sidebar-circle]'))
      return { ...out, fill: c.backgroundColor, edge: c.borderTopColor, shadow: c.boxShadow, sidebar: getComputedStyle(document.querySelector('[data-atom-sidebar]')).backgroundColor }
    })
    expect(fills.fill).toBe(fills.canvas)
    expect(fills.fill).not.toBe(fills.sidebar)
    expect(fills.shadow).not.toBe('none')

    // the half outside the sidebar is over the page: it is what is hit there
    const hit = await page.evaluate(() => {
      const r = document.querySelector('[data-atom-sidebar-circle]').getBoundingClientRect()
      const sidebar = document.querySelector('[data-atom-sidebar]').getBoundingClientRect()
      const x = sidebar.right + 6
      const y = r.top + r.height / 2
      return document.elementFromPoint(x, y)?.closest('[data-atom-sidebar-circle]') !== null
    })
    expect(hit).toBe(true)

    // an open account menu is in the top layer, so it covers the circle where they meet
    await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
    await expect(page.locator('[data-atom-menu]:popover-open').first()).toBeVisible()
    const covered = await page.evaluate(() => {
      const menu = document.querySelector('[data-atom-menu]:popover-open')
      const r = document.querySelector('[data-atom-sidebar-circle]').getBoundingClientRect()
      menu.style.left = (r.left - 20) + 'px'
      menu.style.top = (r.top - 20) + 'px'
      menu.style.right = 'auto'
      menu.style.bottom = 'auto'
      const top = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
      return !!top?.closest('[data-atom-menu]')
    })
    expect(covered).toBe(true)
  })

  test('a trail that prints nothing, known only in the browser (a page.header a Livewire update renders later), swaps the bar button for the circle', async ({ page }) => {
    const css = await page.request.get('/atom/e2e/consumer-css/latest')
    expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)
    await page.setViewportSize({ width: 1280, height: 800 })

    await page.goto('/atom/e2e/breadcrumbs-header/late')
    await page.waitForFunction(() => window.Alpine !== undefined && window.Alpine.$data(document.querySelector('[data-atom-breadcrumbs]'))?.built)

    // the bar is drawn (the heading is the trail's own): the plain button, no circle
    await expect(page.locator('[data-atom-header]')).not.toHaveAttribute('data-atom-header-empty', '')
    await expect(collapseButton(page)).toBeVisible()
    await expect(circle(page)).toBeHidden()

    await page.locator('[data-load]').click()
    await expect(page.locator('[data-atom-header]')).toHaveAttribute('data-atom-header-empty', '')
    await expect(circle(page)).toBeVisible()
    await expect(collapseButton(page)).toBeHidden()

    // collapsed, the same page draws the bar with the button, whatever the mark says
    await circle(page).click()
    await expect(page.locator('[data-atom-header]')).toHaveAttribute('data-atom-header-empty', '')
    await expect(collapseButton(page)).toBeVisible()
    await expect(circle(page)).toBeHidden()
  })

  test('is remembered across a reload, with the first paint already collapsed', async ({ page }) => {
    await goCollapse(page)
    await circle(page).click()
    expect(await stash(page)).toBe('1')

    // hold Livewire's script (and the Alpine inside it) back: what the page looks like on its own, as the browser first paints it
    await page.route('**/livewire*/**', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 900))
      await route.continue()
    })

    await page.reload({ waitUntil: 'commit' })
    await page.waitForFunction(() => performance.getEntriesByType('paint').length > 0)

    const painted = await page.evaluate(() => {
      const sidebar = document.querySelector('[data-atom-sidebar]')
      const header = document.querySelector('[data-atom-header]')
      return {
        alpine: window.Alpine !== undefined,
        visibility: getComputedStyle(sidebar).visibility,
        mainX: document.querySelector('[data-atom-main]').getBoundingClientRect().x,
        barHeight: header.getBoundingClientRect().height,
        glyphs: [...document.querySelectorAll('[data-atom-sidebar-collapse] [data-atom-sidebar-glyph]')].filter((el) => getComputedStyle(el).display !== 'none').map((el) => el.dataset.atomSidebarGlyph),
      }
    })

    // the guard: if Alpine got through, this proves nothing
    expect(painted.alpine).toBe(false)
    expect(painted).toMatchObject({ visibility: 'hidden', mainX: 0, barHeight: 48, glyphs: ['expand'] })

    await page.unroute('**/livewire*/**')
    await page.waitForFunction(() => window.Alpine !== undefined)
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'false')
    await expect.poll(() => sidebarShown(page)).toBe(false)
  })

  test('survives wire:navigate with no frame of the other state', async ({ page }) => {
    await goCollapse(page, { query: '&crumbs=1' })
    await collapseButton(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(false)

    await page.evaluate(() => {
      window.__frames = []
      let navigated = false
      document.addEventListener('livewire:navigated', () => { navigated = true })
      const sample = () => {
        const sidebar = document.querySelector('[data-atom-sidebar]')
        const header = document.querySelector('[data-atom-header]')
        window.__frames.push({
          visibility: getComputedStyle(sidebar).visibility,
          barHeight: Math.round(header.getBoundingClientRect().height),
          page: document.querySelector('[data-fixture-page]')?.dataset.fixturePage,
        })
        if (!(navigated && window.__frames.length > 20)) requestAnimationFrame(sample)
        else window.__done = true
      }
      requestAnimationFrame(sample)
    })

    await page.locator('[data-go]').click()
    await expect(page.locator('[data-fixture-page="two"]')).toBeVisible()
    await page.waitForFunction(() => window.__done)

    const frames = await page.evaluate(() => window.__frames)
    expect(frames.some((f) => f.page === 'two'), 'a frame of the new page was sampled').toBe(true)
    expect(frames.filter((f) => f.visibility !== 'hidden')).toEqual([])

    // it was a navigation, not a load, and the state is still the browser's
    expect(await page.evaluate(() => performance.getEntriesByType('navigation').length)).toBe(1)
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'false')
    await expect(collapseButton(page)).toHaveAttribute('aria-label', 'Sidebar')
    await expect.poll(() => sidebarShown(page)).toBe(false)

    await collapseButton(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await page.locator('[data-go]').click()
    await expect(page.locator('[data-fixture-page="one"]')).toBeVisible()
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'true')
  })

  test('focus goes to the bar button when the sidebar it was in goes away', async ({ page }) => {
    await goCollapse(page)

    // by keyboard, from the circle: the focus goes to the bar button that is there now
    await circle(page).focus()
    await page.keyboard.press('Enter')
    await expect(collapseButton(page)).toBeFocused()

    // and back: the bar has nothing else in it, so it goes with the sidebar's return, and the focus goes to the circle
    await page.keyboard.press('Enter')
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await expect(circle(page)).toBeFocused()

    // with crumbs the bar stays, and so does the focus on its button
    await page.goto('/atom/e2e/shell-collapse/one?tw=latest&crumbs=1')
    await page.waitForFunction(() => window.Alpine !== undefined)
    await collapseButton(page).focus()
    await page.keyboard.press('Enter')
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'false')
    await page.keyboard.press('Enter')
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'true')
    await expect(collapseButton(page)).toBeFocused()
    await page.goto('/atom/e2e/shell-collapse/one?tw=latest')
    await page.waitForFunction(() => window.Alpine !== undefined)

    // Ctrl+B with a nav link focused
    await page.getByRole('link', { name: 'Page two' }).focus()
    await page.keyboard.press('Control+b')
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(collapseButton(page)).toBeFocused()

    // Ctrl+B from the page itself (the focus on the body)
    await page.keyboard.press('Control+b')
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await page.evaluate(() => document.activeElement?.blur())
    await page.keyboard.press('Control+b')
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(collapseButton(page)).toBeFocused()
  })

  test('a focus that is somewhere else stays there', async ({ page }) => {
    await goCollapse(page, { query: '&crumbs=1' })

    await page.locator('[data-go]').focus()
    await page.keyboard.press('Control+b')
    await expect.poll(() => sidebarShown(page)).toBe(false)
    await expect(page.locator('[data-go]')).toBeFocused()
  })

  test('Ctrl+B held down toggles once, and not at all under an open dialog', async ({ page }) => {
    await goCollapse(page, { query: '&crumbs=1' })
    const button = collapseButton(page)

    await page.evaluate(() => {
      const fire = (repeat) => window.dispatchEvent(new KeyboardEvent('keydown', { key: 'b', code: 'KeyB', ctrlKey: true, repeat, bubbles: true, cancelable: true }))
      fire(false)
      fire(true)
    })
    await expect(button).toHaveAttribute('aria-expanded', 'false')

    await button.click()
    await expect(button).toHaveAttribute('aria-expanded', 'true')

    await page.evaluate(() => {
      const dialog = document.createElement('dialog')
      dialog.id = 'st-dialog'
      document.body.appendChild(dialog)
      dialog.showModal()
    })
    await page.keyboard.press('Control+b')
    await expect(button).toHaveAttribute('aria-expanded', 'true')
    expect(await stash(page)).toBeNull()

    await page.evaluate(() => document.getElementById('st-dialog').close())
    await page.keyboard.press('Control+b')
    await expect(button).toHaveAttribute('aria-expanded', 'false')
  })

  test('works from a layout without a Latin B (the key is not b, the code is KeyB), but not where the letter is elsewhere', async ({ page }) => {
    await goCollapse(page, { query: '&crumbs=1' })
    const button = collapseButton(page)

    const press = (key, code) => page.evaluate(([k, c]) => window.dispatchEvent(new KeyboardEvent('keydown', { key: k, code: c, ctrlKey: true, bubbles: true, cancelable: true })), [key, code])

    await press('и', 'KeyB')
    await expect(button).toHaveAttribute('aria-expanded', 'false')
    await press('и', 'KeyB')
    await expect(button).toHaveAttribute('aria-expanded', 'true')

    // a Dvorak "x" sits on the physical B: it is not ours
    await press('x', 'KeyB')
    await expect(button).toHaveAttribute('aria-expanded', 'true')
  })

  test('a keydown with no key (autofill, a synthetic Event) is ignored, with no page error', async ({ page }) => {
    const errors = []
    page.on('pageerror', (error) => errors.push(error.message))
    await goCollapse(page, { query: '&crumbs=1' })

    await page.evaluate(() => {
      window.dispatchEvent(new Event('keydown'))
      document.dispatchEvent(new Event('keydown', { bubbles: true }))
      document.body.dispatchEvent(new Event('keydown', { bubbles: true }))
    })

    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'true')
    expect(errors).toEqual([])
  })

  test('a stashed sidebar does not outlive the window growing to lg', async ({ page }) => {
    await page.setViewportSize({ width: 800, height: 800 })
    await page.addInitScript(() => { try { window.localStorage.setItem('atom-sidebar-collapsed', '1') } catch {} })
    await goCollapse(page, { query: '&crumbs=1' })

    // below lg the menu button opens the stashed sidebar over the page
    await page.locator('[data-atom-sidebar-toggle]').click()
    await expect(page.locator('body')).toHaveAttribute('data-show-stashed-sidebar', '')
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await page.setViewportSize({ width: 1280, height: 800 })
    await expect.poll(() => page.evaluate(() => document.body.hasAttribute('data-show-stashed-sidebar'))).toBe(false)
    await expect.poll(() => sidebarShown(page)).toBe(false)
    // collapsed still: the bar button brings it back
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'false')
    await collapseButton(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)
  })

  test('storage that throws leaves the sidebar working', async ({ page }) => {
    await page.addInitScript(() => {
      Object.defineProperty(window, 'localStorage', { get () { throw new Error('denied') } })
    })
    await goCollapse(page, { query: '&crumbs=1' })

    await collapseButton(page).click()
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'false')
    await expect.poll(() => sidebarShown(page)).toBe(false)
  })
})

test('in a real editor Ctrl+B bolds and does not collapse the sidebar', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 })
  const css = await page.request.get('/atom/e2e/consumer-css/latest')
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto('/atom/e2e/shell-editor?tw=latest&bare=1')
  await page.waitForFunction(() => window.Alpine !== undefined)

  const editor = page.locator('.ProseMirror').first()
  await editor.click()
  await page.keyboard.type('bold me')
  // ProseMirror's Mod is Cmd on a Mac and Ctrl elsewhere
  await page.keyboard.press('ControlOrMeta+a')
  await page.keyboard.press('ControlOrMeta+b')

  await expect(editor.locator('strong')).toHaveText('bold me')
  expect(await page.evaluate(() => document.documentElement.hasAttribute('data-atom-sidebar-collapsed'))).toBe(false)
  expect(await page.evaluate(() => window.localStorage.getItem('atom-sidebar-collapsed'))).toBeNull()
})
