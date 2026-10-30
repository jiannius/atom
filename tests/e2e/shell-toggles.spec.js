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

    // by keyboard, from the foot row
    await page.locator('[data-atom-sidebar-fallback]').focus()
    await page.keyboard.press('Enter')
    await expect(collapseButton(page)).toBeFocused()

    // and back: the bar has nothing else in it, so it goes with the sidebar's return, and the focus goes to the row that is the button's twin
    await page.keyboard.press('Enter')
    await expect.poll(() => sidebarShown(page)).toBe(true)
    await expect(page.locator('[data-atom-sidebar-fallback]')).toBeFocused()

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
