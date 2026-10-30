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
        light: await row.getByRole('button', { name: 'Light' }).getAttribute('aria-pressed'),
        dark: await row.getByRole('button', { name: 'Dark' }).getAttribute('aria-pressed'),
        system: await row.getByRole('button', { name: 'System' }).getAttribute('aria-pressed'),
      })

      expect(await pressed()).toEqual({ light: 'false', dark: 'false', system: 'true' })

      await row.getByRole('button', { name: 'Dark' }).click()

      expect(await isDark(page)).toBe(true)
      await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeVisible()
      await expect(row).toBeVisible()
      expect(await pressed()).toEqual({ light: 'false', dark: 'true', system: 'false' })

      await row.getByRole('button', { name: 'Light' }).click()
      expect(await isDark(page)).toBe(false)
      await expect(row).toBeVisible()
      expect(await pressed()).toEqual({ light: 'true', dark: 'false', system: 'false' })
    })

    test('the choice survives a reload, and the row shows it', async ({ page }) => {
      await open(page, { shell, query: '&dark=1' })

      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      await page.locator('[data-atom-theme-row]:visible').getByRole('button', { name: 'Dark' }).click()
      expect(await isDark(page)).toBe(true)

      await page.reload()
      await page.waitForFunction(() => window.Alpine !== undefined)

      expect(await isDark(page)).toBe(true)
      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      await expect(page.locator('[data-atom-theme-row]:visible').getByRole('button', { name: 'Dark' })).toHaveAttribute('aria-pressed', 'true')
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
      seen: ['system', 'dark', 'dark', 'light', 'light', 'system', 'system'],
    })
  })

  test('the theme row starts from the stored choice', async ({ page }) => {
    await page.addInitScript(() => { try { window.localStorage.setItem('darkmode', 'light') } catch {} })
    await page.setViewportSize({ width: 1280, height: 800 })
    await open(page, { query: '&dark=1' })

    await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
    await expect(page.locator('[data-atom-theme-row]:visible').getByRole('button', { name: 'Light' })).toHaveAttribute('aria-pressed', 'true')
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
    await expect(button).toHaveAttribute('aria-label', 'Hide sidebar')
    await expect(button).toHaveAttribute('aria-controls', 'atom-sidebar')
    await expect(page.locator('#atom-sidebar')).toHaveCount(1)
    await expect.poll(() => sidebarShown(page)).toBe(true)

    const mainBefore = await page.locator('[data-atom-main]').boundingBox()
    expect(mainBefore.x).toBeGreaterThan(200)

    await button.click()

    await expect(button).toHaveAttribute('aria-expanded', 'false')
    await expect(button).toHaveAttribute('aria-label', 'Show sidebar')
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
    await expect(button).toHaveAttribute('aria-label', 'Hide sidebar')
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

  test('a bar with nothing in it is not drawn while the sidebar is shown, and the button is at the sidebar\'s foot', async ({ page }) => {
    await goCollapse(page)

    expect(await barHeight(page)).toBe(0)
    await expect(collapseButton(page)).toBeHidden()

    const fallback = page.locator('[data-atom-sidebar-fallback]')
    await expect(fallback).toBeVisible()
    await expect(fallback).toHaveText('Hide sidebar')

    await fallback.click()
    await expect.poll(() => sidebarShown(page)).toBe(false)

    // collapsed: the bar is always drawn, with the button that brings the sidebar back
    expect(await barHeight(page)).toBe(48)
    await expect(collapseButton(page)).toBeVisible()
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'false')
    // and the page starts under it
    expect((await page.locator('[data-atom-main]').boundingBox()).y).toBeGreaterThanOrEqual(48)

    await collapseButton(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)
    expect(await barHeight(page)).toBe(0)
    await expect(fallback).toBeVisible()
  })

  test('a bar that has crumbs or actions is drawn either way, and the foot has no second button', async ({ page }) => {
    await goCollapse(page, { query: '&crumbs=1' })

    expect(await barHeight(page)).toBe(48)
    await expect(page.locator('[data-atom-sidebar-fallback]')).toBeHidden()
    await expect(collapseButton(page)).toBeVisible()

    await collapseButton(page).click()
    expect(await barHeight(page)).toBe(48)
    await expect(page.locator('[data-crumb]')).toBeVisible()
  })

  test('is remembered across a reload, with the first paint already collapsed', async ({ page }) => {
    await goCollapse(page)
    await page.locator('[data-atom-sidebar-fallback]').click()
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
    await expect(collapseButton(page)).toHaveAttribute('aria-label', 'Show sidebar')
    await expect.poll(() => sidebarShown(page)).toBe(false)

    await collapseButton(page).click()
    await expect.poll(() => sidebarShown(page)).toBe(true)

    await page.locator('[data-go]').click()
    await expect(page.locator('[data-fixture-page="one"]')).toBeVisible()
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await expect(collapseButton(page)).toHaveAttribute('aria-expanded', 'true')
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
