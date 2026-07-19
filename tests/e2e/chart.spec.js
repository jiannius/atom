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
