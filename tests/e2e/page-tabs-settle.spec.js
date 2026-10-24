import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// <atom:page.tabs> keeps the current tab in view after the tabs change width: a web font that arrives after the strip started, a strip
// that was hidden when it started, a tab that gains a count. It stops once the reader has scrolled the strip themselves (a sideways wheel, a swipe, a click, a key, focus).

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

/** Widen the tabs and wait until the strip has grown, so the assertion that follows is about the resize having been handled */
const widenAndWait = async (page) => {
  const before = await strip(page).evaluate((el) => el.scrollWidth)

  await widen(page)
  await expect.poll(() => strip(page).evaluate((el) => el.scrollWidth)).toBeGreaterThan(before)
  // the observer reports on the next frame
  await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))))
}

test('a strip the reader has scrolled is not pulled back by a later resize', async ({ page }) => {
  await open(page, { path: 'tabs-overflow', extra: '&current=Settings' })

  await expect.poll(async () => (await inView(page)).scrollLeft).toBeGreaterThan(0)

  await strip(page).hover()
  await page.mouse.wheel(-2000, 0)
  await expect.poll(async () => (await inView(page)).scrollLeft).toBe(0)

  await widenAndWait(page)

  expect((await inView(page)).scrollLeft).toBe(0)
})

test('a tab the reader focused is not carried off when the tabs get wider', async ({ page }) => {
  await open(page, { path: 'tabs-overflow', extra: '&current=Settings' })

  await expect.poll(async () => (await inView(page)).scrollLeft).toBeGreaterThan(0)

  // focus moves into the strip with no key, pointer or wheel event of its own (a screen reader, a script, the browser's own
  // Tab handling before the next key): only `focusin` can tell the strip the reader is in it
  await page.getByTestId('page-tabs-bare').locator('a', { hasText: 'Activity' }).focus()
  await expect(page.getByTestId('page-tabs-bare').locator('a', { hasText: 'Activity' })).toBeFocused()

  const before = (await inView(page)).scrollLeft

  await widenAndWait(page)

  // the reveal would move the strip on to the current tab at the far end (the strip is longer now, so further than before)
  expect((await inView(page)).scrollLeft).toBe(before)
  await expect(page.getByTestId('page-tabs-bare').locator('a', { hasText: 'Activity' })).toBeFocused()
})

test('a vertical wheel over the strip is the page scrolling, and does not stop the correction', async ({ page }) => {
  await open(page, { path: 'tabs-overflow', extra: '&current=Settings' })

  await strip(page).hover()
  await page.mouse.wheel(0, 120)

  await widen(page)

  await expect.poll(async () => (await inView(page)).inside).toBe(true)
})

test('a wheel with shift held is a sideways gesture, and stops it', async ({ page }) => {
  await open(page, { path: 'tabs-overflow', extra: '&current=Settings' })

  await strip(page).hover()
  await page.keyboard.down('Shift')
  await page.mouse.wheel(0, 40)
  await page.keyboard.up('Shift')
  // put the strip back at the start by hand: only the event's own flag can have said the reader scrolled it
  await strip(page).evaluate((el) => { el.scrollLeft = 0 })

  await widenAndWait(page)

  expect((await inView(page)).scrollLeft).toBe(0)
})
