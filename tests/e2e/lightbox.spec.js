import { test, expect } from '@playwright/test'

// Drives the live lightbox demo on /atom/docs/lightbox. The lightbox is a native
// <dialog> opened with showModal(), so visibility + the data-open attribute are
// driven by the dialog element and Alpine — not Tailwind — and are valid in the
// package-served docs env (which ships only atom's base CSS).
//
// Slide navigation is read from the Alpine component's `pointer` rather than the
// rendered <img>, because the demo points at external picsum URLs that need not
// load for the test to be meaningful.

const lightbox = (page) => page.locator('dialog[data-atom-lightbox]')
const pointer = (page) =>
  page.evaluate(() => window.Alpine.$data(document.querySelector('[data-atom-lightbox]')).pointer)

const open = async (page) => {
  await page.goto('/atom/docs/lightbox')
  await page.locator('[data-lightbox] [data-lightbox-url]').first().click()
  await expect(lightbox(page)).toBeVisible()
}

test('opens from a thumbnail with a labelled control set', async ({ page }) => {
  await open(page)

  const dialog = lightbox(page)
  await expect(dialog).toHaveAttribute('data-open', '')
  await expect(dialog.locator('button[aria-label="Close"]')).toBeVisible()
  await expect(dialog.locator('button[aria-label="Previous"]')).toBeVisible()
  await expect(dialog.locator('button[aria-label="Next"]')).toBeVisible()
  expect(await pointer(page)).toBe(0)
})

test('navigates with the next button and the arrow keys', async ({ page }) => {
  await open(page)

  await lightbox(page).locator('button[aria-label="Next"]').click()
  await expect.poll(() => pointer(page)).toBe(1)

  await page.keyboard.press('ArrowRight')
  await expect.poll(() => pointer(page)).toBe(2)

  await page.keyboard.press('ArrowLeft')
  await expect.poll(() => pointer(page)).toBe(1)
})

test('closes on escape and clears the data-open hook', async ({ page }) => {
  await open(page)

  // Regression: native <dialog> escape bypasses close(), so data-open used to
  // linger; the dialog now listens for the close event to clear it.
  await page.keyboard.press('Escape')
  await expect(lightbox(page)).toBeHidden()
  await expect(lightbox(page)).not.toHaveAttribute('data-open')
})

test('closes via the close button', async ({ page }) => {
  await open(page)

  await lightbox(page).locator('button[aria-label="Close"]').click()
  await expect(lightbox(page)).toBeHidden()
})
