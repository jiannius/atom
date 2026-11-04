import { test, expect } from '@playwright/test'

// Drives the chart demos on /atom/docs/chart. Proves the Alpine factories boot
// and ApexCharts renders an SVG into [data-atom-chart] — the regression guard
// for the area.js `this.chart.render()` fix (area charts never rendered before).

test('bar chart renders an apexcharts canvas', async ({ page }) => {
  await page.goto('/atom/docs/chart')
  const chart = page.locator('[data-atom-chart-type="bar"]').first()
  await expect(chart.locator('.apexcharts-canvas')).toBeVisible()
})

test('area chart renders (regression: this.chart.render fix)', async ({ page }) => {
  await page.goto('/atom/docs/chart')
  const chart = page.locator('[data-atom-chart-type="area"]').first()
  await expect(chart.locator('.apexcharts-canvas')).toBeVisible()
})

test('trend sparkline renders', async ({ page }) => {
  await page.goto('/atom/docs/chart')
  const chart = page.locator('[data-atom-chart-type="trend"]').first()
  await expect(chart.locator('.apexcharts-canvas')).toBeVisible()
})

// #151: a chart is as tall as its own box (h-16 for a trend, h-64 otherwise), not as tall as whatever holds it. ApexCharts
// reads a percentage height from the parent of the element it mounts on, so the factories mount on an inner canvas whose
// parent is the chart's own sized root. The rig has no Tailwind, so the sizes come from the consumer builds.
import { openContent, chartReady } from './content-helpers.js'

const svgHeight = (page, testid) => page.getByTestId(testid).locator('svg.apexcharts-svg').first().evaluate((el) => el.getBoundingClientRect().height)

for (const tw of ['floor', 'latest']) {
  test.describe(`chart sizing, ${tw} consumer build`, () => {
    test.beforeEach(async ({ page }) => {
      await openContent(page, { tw, section: 'chart-sizing' })
      for (const id of ['size-stat-trend', 'size-trend', 'size-bar', 'size-area', 'size-own-bar', 'size-own-trend', 'size-fill', 'size-card-chart', 'size-card-stat']) {
        await expect(page.getByTestId(id).locator('.apexcharts-canvas')).toBeVisible()
      }
      await page.waitForTimeout(400)
    })

    test('a trend with no class is 64px tall inside a 300px parent, a bar and an area 256px inside 400px', async ({ page }) => {
      expect(Math.abs(await svgHeight(page, 'size-trend') - 64)).toBeLessThanOrEqual(2)
      expect(Math.abs(await svgHeight(page, 'size-bar') - 256)).toBeLessThanOrEqual(2)
      expect(Math.abs(await svgHeight(page, 'size-area') - 256)).toBeLessThanOrEqual(2)
    })

    test('an h-* class on the tag sets the height of a bar and of a trend', async ({ page }) => {
      expect(Math.abs(await svgHeight(page, 'size-own-bar') - 160)).toBeLessThanOrEqual(2)
      expect(Math.abs(await svgHeight(page, 'size-own-trend') - 96)).toBeLessThanOrEqual(2)
    })

    test('a stat with a trend draws a 32px sparkline inside its own box, and grows by the chart and its 8px gap only (#159)', async ({ page }) => {
      const stat = page.getByTestId('size-stat-trend')
      const svg = stat.locator('svg.apexcharts-svg')

      await expect(svg).toBeVisible()
      await page.waitForTimeout(300)

      const box = await stat.boundingBox()
      const line = await svg.boundingBox()

      expect(Math.abs(line.height - 32)).toBeLessThanOrEqual(2)
      // inside the stat, above its bottom padding
      expect(line.y).toBeGreaterThanOrEqual(box.y)
      expect(line.y + line.height).toBeLessThanOrEqual(box.y + box.height - 8)

      // a group stretches its stats to one height, so compare what each one needs: its content plus its bottom padding
      const natural = (testid) => page.getByTestId(testid).evaluate((el) => {
        const last = el.lastElementChild.getBoundingClientRect()
        return last.bottom + parseFloat(getComputedStyle(el).paddingBottom) - el.getBoundingClientRect().top
      })

      expect(Math.abs((await natural('size-stat-trend')) - (await natural('size-stat-plain')) - 40)).toBeLessThanOrEqual(2)
    })

    test('a chart that passes h-full still fills its box', async ({ page }) => {
      expect(Math.abs(await svgHeight(page, 'size-fill') - 120)).toBeLessThanOrEqual(2)
    })

    test('the card chart and the stat trend keep the sizes they had', async ({ page }) => {
      expect(Math.abs(await svgHeight(page, 'size-card-chart') - 249)).toBeLessThanOrEqual(3)
      expect(Math.abs(await svgHeight(page, 'size-card-stat') - 63)).toBeLessThanOrEqual(3)
    })
  })
}

test('a Livewire re-render of the component keeps the charts ApexCharts drew', async ({ page }) => {
  await page.goto('/atom/e2e/chart-morph')

  for (const id of ['morph-bar', 'morph-trend', 'morph-area']) {
    await expect(page.getByTestId(id).locator('.apexcharts-canvas')).toBeVisible()
  }

  const canvas = await page.getByTestId('morph-bar').locator('.apexcharts-canvas').elementHandle()

  await page.locator('[data-bump]').click()
  await expect(page.locator('[data-renders]')).toHaveText('1')
  // the root still takes a server-rendered attribute
  await expect(page.getByTestId('morph-bar')).toHaveAttribute('data-state', 'render-1')

  for (const id of ['morph-bar', 'morph-trend', 'morph-area']) {
    await expect(page.getByTestId(id).locator('.apexcharts-canvas')).toHaveCount(1)
  }

  // the same node, not a redraw
  expect(await canvas.evaluate((el) => el.isConnected)).toBe(true)
})
