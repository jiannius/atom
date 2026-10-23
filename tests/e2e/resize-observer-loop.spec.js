import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// A viewport resize must not make the browser report "ResizeObserver loop completed with undelivered notifications": that is an observer
// whose callback changes the size of what it observes in the same frame. The sidebar shell used to do it (x-resize.document turning
// the sidebar in and out of the flow at 1024px); the record's tab strip and the header shell observe too. Every shell page is resized
// across the breakpoints, and the page must report none, while the sidebar still follows the width.

const PAGES = ['shell-record', 'shell-list', 'shell-form', 'shell-document', 'shell-slide-over', 'shell-header', 'shell-sidebar', 'timeline']
const WIDTHS = [1100, 800, 600, 390, 700, 1000, 1440, 320, 1200, 1023, 1024]

for (const tw of ['floor', 'latest']) {
  for (const path of PAGES) {
    test(`${path} reports no ResizeObserver loop across a resize, ${tw} consumer build`, async ({ page }) => {
      const reported = []

      await page.addInitScript(() => {
        window.__loops = []
        window.addEventListener('error', (e) => window.__loops.push(e.message))
      })
      page.on('pageerror', (e) => reported.push(e.message))
      page.on('console', (m) => m.type() === 'error' && reported.push(m.text()))

      await page.setViewportSize({ width: 1440, height: 900 })
      await open(page, { path, tw })

      for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 900 })
        await page.waitForTimeout(120)
      }

      const loops = [...reported, ...(await page.evaluate(() => window.__loops))].filter((message) => /ResizeObserver/.test(message))

      expect(loops).toEqual([])
    })
  }

  test(`the sidebar still follows the width through the breakpoint, ${tw} consumer build`, async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await open(page, { path: 'shell-list', tw })

    const sidebar = page.locator('[data-atom-sidebar]')

    await expect(sidebar).not.toHaveAttribute('data-stashed', 'true')

    await page.setViewportSize({ width: 900, height: 900 })
    await expect(sidebar).toHaveAttribute('data-stashed', 'true')

    await page.setViewportSize({ width: 1200, height: 900 })
    await expect(sidebar).not.toHaveAttribute('data-stashed', 'true')
  })
}
