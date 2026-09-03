import { test, expect } from '@playwright/test'

// atom.css now carries [x-cloak]{display:none!important}. The whole point of the
// change is that the rule REACHES the browser — asserting it exists in the source
// file (XCloakTest) can't catch a build or manifest problem, which is exactly how
// it stayed inert for so long.

test('the served stylesheet actually hides an x-cloak element', async ({ page }) => {
  await page.goto('/atom/docs/uploader')
  await page.waitForLoadState('networkidle')

  // an element with no x-data above it is never touched by Alpine, so whatever
  // display it ends up with came from the stylesheet and nothing else
  const display = await page.evaluate(() => {
    const el = document.createElement('div')
    el.setAttribute('x-cloak', '')
    el.className = 'flex'                    // a utility of equal specificity
    document.body.appendChild(el)

    const value = getComputedStyle(el).display
    el.remove()

    return value
  })

  expect(display).toBe('none')
})

test('components that use x-cloak still render once Alpine boots', async ({ page }) => {
  // The flip side, and the risk the rule introduces: an x-cloak Alpine never
  // strips is now hidden forever rather than harmlessly ignored. The uploader
  // carries it on the same element as its x-data.
  await page.goto('/atom/docs/uploader')
  await page.waitForLoadState('networkidle')

  await expect(page.getByText('Upload attachment').first()).toBeVisible()

  // nothing anywhere on the page is left cloaked
  await expect
    .poll(() => page.locator('[x-cloak]').count())
    .toBe(0)
})

test('no page in the docs leaves an element permanently cloaked', async ({ page }) => {
  // every component that uses x-cloak, swept in one pass
  for (const slug of ['uploader', 'lightbox', 'tiptap', 'date-picker', 'table', 'select', 'input']) {
    await page.goto(`/atom/docs/${slug}`)
    await page.waitForLoadState('networkidle')

    await expect
      .poll(() => page.locator('[x-cloak]').count(), { message: `/atom/docs/${slug}` })
      .toBe(0)
  }
})
