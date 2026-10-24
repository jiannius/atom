import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// <atom:page.tabs> keeps the current tab in view after the tabs change width: a web font that arrives after the strip started, a strip
// that was hidden when it started, a tab that gains a count. It stops once the reader has scrolled the strip themselves.

const current = (page) => page.getByTestId('page-tabs-bare').locator('[aria-current="page"]')
const strip = (page) => page.getByTestId('page-tabs-bare').locator('ul')

/** Whether the current tab sits fully inside the strip, and whether the strip fades its end */
const inView = (page) => page.evaluate(() => {
  const ul = document.querySelector('[data-testid="page-tabs-bare"] ul')
  const tab = ul.querySelector('[aria-current="page"]').getBoundingClientRect()
  const box = ul.getBoundingClientRect()

  return { inside: tab.left >= box.left - 0.5 && tab.right <= box.right + 0.5, fadeEnd: ul.hasAttribute('data-fade-end'), scrollLeft: ul.scrollLeft }
})

// a wider face, as a web font that arrives late makes
const widen = (page) => page.addStyleTag({ content: '[data-atom-page-tab]{letter-spacing:.15em}' })

test.beforeEach(async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 900 })
})

test('the current tab stays in view when the tabs get wider after the strip started', async ({ page }) => {
  await open(page, { path: 'tabs-overflow', extra: '&current=Settings' })

  expect(await inView(page)).toMatchObject({ inside: true, fadeEnd: false })

  await widen(page)

  await expect.poll(async () => (await inView(page)).inside).toBe(true)
  expect((await inView(page)).fadeEnd).toBe(false)
})

test('the current tab is in view when a strip that started hidden is shown', async ({ page }) => {
  await open(page, { path: 'tabs-overflow', extra: '&current=Settings&hidden=1' })

  await page.getByTestId('tab-strips').evaluate((el) => { el.style.display = '' })

  await expect(current(page)).toBeVisible()
  await expect.poll(async () => (await inView(page)).inside).toBe(true)
  expect((await inView(page)).fadeEnd).toBe(false)
})

test('a strip the reader has scrolled is not pulled back by a later resize', async ({ page }) => {
  await open(page, { path: 'tabs-overflow', extra: '&current=Settings' })

  await expect.poll(async () => (await inView(page)).scrollLeft).toBeGreaterThan(0)

  await strip(page).hover()
  await page.mouse.wheel(-2000, 0)
  await expect.poll(async () => (await inView(page)).scrollLeft).toBe(0)

  await widen(page)
  // give the observer its turn: the strip is wider now, and must stay where the reader left it
  await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))))
  await page.waitForTimeout(150)

  expect((await inView(page)).scrollLeft).toBe(0)
})
