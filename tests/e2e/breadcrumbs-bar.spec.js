import { test, expect } from '@playwright/test'

// The shell's top bar when the persisted trail prints nothing (one crumb above a page.header, which prints the title itself).
// The server cannot know that: the trail's markup is always in the `crumbs` slot. breadcrumbs.js marks the bar
// `data-atom-header-empty` (layouts.sidebar, from lg up) or the crumbs row (layouts.header) and atom.css does not draw it.
// Fixture: /atom/e2e/breadcrumbs-header/{old|new|plain|deep|late}; old and new have one crumb, plain and deep two; new and deep
// have a page.header, late gets one from a Livewire action. ?shell=header, ?actions=1. On the consumer Tailwind
// (`node tests/consumer/build.mjs` first).

const url = (slug, query = '') => `/atom/e2e/breadcrumbs-header/${slug}${query}`
const header = (page) => page.locator('[data-atom-main] h1[data-atom-page-header-title]')
const trailBuilt = (page, count) => page.waitForFunction((n) => {
  const root = document.querySelector('[data-atom-breadcrumbs]')
  return root && window.Alpine?.$data(root).built && window.Alpine.$data(root).breadcrumbs.length === n
}, count)

for (const shell of ['sidebar', 'header']) {
  test.describe(`${shell} shell`, () => {
    const query = shell === 'header' ? '?shell=header' : ''
    // what is drawn above the page: the sidebar's 48px bar, or the header shell's crumbs row under its (always drawn) bar
    const bar = (page) => page.locator(shell === 'header' ? '[data-atom-header-crumbs]' : '[data-atom-header]')
    const barHeight = (page) => bar(page).evaluate((el) => el.getBoundingClientRect().height)
    const crumbs = (page) => page.locator('[data-atom-breadcrumbs] ol li')

    test.beforeEach(async ({ page }) => {
      const css = await page.request.get('/atom/e2e/consumer-css/latest')
      expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)
      await page.setViewportSize({ width: 1440, height: 900 })
    })

    test('one crumb above a page.header: nothing is drawn above the page', async ({ page }) => {
      await page.goto(url('new', query))
      await expect(header(page)).toHaveText('New page')
      await trailBuilt(page, 1)

      await expect.poll(() => barHeight(page)).toBe(0)
      await expect(bar(page)).toHaveAttribute('data-atom-header-empty', '')
      // the page header is the first thing on the page: it starts at the top, under no empty row
      expect((await header(page).boundingBox()).y).toBeLessThan(shell === 'header' ? 80 : 40)
    })

    test('wire:navigate to a two-crumb page brings it back, and a one-crumb page with a page.header takes it away again', async ({ page }) => {
      await page.goto(url('new', query))
      await trailBuilt(page, 1)
      await expect.poll(() => barHeight(page)).toBe(0)

      await page.locator('[data-go="plain"]').click()
      await expect(page).toHaveURL(/breadcrumbs-header\/plain/)
      await expect(crumbs(page)).toHaveCount(2)
      await expect(bar(page)).not.toHaveAttribute('data-atom-header-empty', '')
      if (shell === 'sidebar') expect(await barHeight(page)).toBe(48)
      await expect(crumbs(page).first()).toBeVisible()

      await page.locator('[data-go="new"]').click()
      await expect(page).toHaveURL(/breadcrumbs-header\/new/)
      await expect(header(page)).toHaveText('New page')
      await expect.poll(() => barHeight(page)).toBe(0)

      // two crumbs and a page.header: the trail has something to show
      await page.locator('[data-go="deep"]').click()
      await expect(page).toHaveURL(/breadcrumbs-header\/deep/)
      await expect(crumbs(page)).toHaveCount(2)
      await expect(bar(page)).not.toHaveAttribute('data-atom-header-empty', '')
      await expect(crumbs(page).first()).toBeVisible()
    })

    test('one crumb and no page.header: the bar shows the heading', async ({ page }) => {
      await page.goto(url('old', query))
      await trailBuilt(page, 1)

      await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveText('Old page')
      await expect(bar(page)).not.toHaveAttribute('data-atom-header-empty', '')
      expect(await barHeight(page)).toBeGreaterThan(20)

      // and a page.header that a Livewire update renders later takes it away again
      await page.goto(url('late', query))
      await trailBuilt(page, 1)
      await expect(page.locator('[data-atom-breadcrumbs] [data-atom-heading]')).toHaveText('Late page')
      expect(await barHeight(page)).toBeGreaterThan(20)

      await page.locator('[data-load]').click()
      await expect(header(page)).toHaveText('Late page')
      await expect.poll(() => barHeight(page)).toBe(0)
    })

    test('a slot that holds more than the trail is the host\'s: it is never marked empty', async ({ page }) => {
      await page.goto(url('new', query))
      await trailBuilt(page, 1)
      await expect.poll(() => barHeight(page)).toBe(0)

      await page.evaluate(() => {
        const slot = document.querySelector('[data-atom-shell-crumbs]')
        const extra = document.createElement('span')
        extra.textContent = 'Extra'
        slot.append(extra)
        window.Alpine.$data(document.querySelector('[data-atom-breadcrumbs]')).syncHeader()
      })

      await expect(bar(page)).not.toHaveAttribute('data-atom-header-empty', '')
      expect(await barHeight(page)).toBeGreaterThan(0)
    })
  })
}

test.describe('sidebar shell, what keeps the bar', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
  })

  test('actions in the bar keep it, with the trail empty', async ({ page }) => {
    await page.goto(url('new', '?actions=1'))
    await expect(header(page)).toHaveText('New page')
    await trailBuilt(page, 1)

    await expect(page.locator('[data-atom-header]')).not.toHaveAttribute('data-atom-header-crumbs-only', '')
    await expect(page.locator('[data-atom-header]')).not.toHaveAttribute('data-atom-header-empty', '')
    expect(await page.locator('[data-atom-header]').evaluate((el) => el.getBoundingClientRect().height)).toBe(48)
    await expect(page.getByTestId('bar-action')).toBeVisible()
  })

  test('a phone keeps the bar: its menu button and the back link are there', async ({ page }) => {
    await page.setViewportSize({ width: 500, height: 900 })
    await page.goto(url('new'))
    await expect(header(page)).toHaveText('New page')
    await trailBuilt(page, 1)

    // below lg the bar holds the sidebar toggle, so it is drawn even though the trail prints nothing
    expect(await page.locator('[data-atom-header]').evaluate((el) => el.getBoundingClientRect().height)).toBe(48)
    await expect(page.locator('[data-atom-sidebar-toggle]')).toBeVisible()

    await page.locator('[data-go="plain"]').click()
    await expect(page.locator('[data-atom-crumb-back]')).toBeVisible()
  })
})
