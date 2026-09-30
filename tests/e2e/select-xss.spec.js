import { test, expect } from '@playwright/test'

// Drives /atom/e2e/select-xss (resources/views/e2e/select-xss.blade.php).
//
// Regression cover for option labels that hold markup. The listbox renders an
// option's html with x-html, so a label or caption a user typed must reach the
// DOM as text — through the remote path (GetOptions builds the html), the static
// path (select.js builds it client-side) and the native select (server-rendered).
// A host's own `html` key is trusted and stays markup.

const select = (page, name) => page.locator(`[data-select="${name}"]`)
const trigger = (page, name) => select(page, name).locator('[data-atom-dropdown] > button')
const options = (page, name) => select(page, name).locator('[data-atom-option]')

// nothing may have run, and no element may have been injected anywhere
async function expectNothingInjected (page) {
  await expect(page.locator('img[onerror], img[src="x"], [data-injected]')).toHaveCount(0)
  expect(await page.evaluate(() => window.__xss)).toBeUndefined()
}

test('a remote option label and caption render as text', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await trigger(page, 'remote').click()
  await expect(options(page, 'remote')).toHaveCount(2)

  const first = options(page, 'remote').first()
  await expect(first).toContainText('<img src=x onerror=')
  await expect(first).toContainText('<script>')
  await expect(first.locator('img, script')).toHaveCount(0)

  // an ampersand and a real-looking tag are text too
  await expect(options(page, 'remote').nth(1)).toContainText('Alice & Bob <b>bold</b>')
  await expect(options(page, 'remote').nth(1).locator('b')).toHaveCount(0)

  await expectNothingInjected(page)
})

test('a picked remote option renders as text in the trigger', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await trigger(page, 'remote').click()
  await options(page, 'remote').first().click()

  await expect(trigger(page, 'remote')).toContainText('<img src=x onerror=')
  await expect(trigger(page, 'remote').locator('img')).toHaveCount(0)
  await expectNothingInjected(page)
})

test('a static option label and colour render as text through the client-side builder', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await trigger(page, 'static').click()
  await expect(options(page, 'static')).toHaveCount(4)
  await expect(options(page, 'static').first()).toContainText('<img src=x onerror=')
  await expect(options(page, 'static').first().locator('img')).toHaveCount(0)

  await options(page, 'static').first().click()
  await expect(trigger(page, 'static').locator('img')).toHaveCount(0)
  await expectNothingInjected(page)
})

test('a native select label cannot break out of the select', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  const option = select(page, 'native').locator('option:not(.placeholder)')
  await expect(option).toHaveCount(1)
  await expect(option).toHaveText(/<\/select><img src=x onerror=/)
  await expectNothingInjected(page)
})

// The native select reads a STRING options prop server-side through
// atom()->action('get-options'), which is a different branch of the blade from the
// array one above.
test('a native select fed remote options prints their labels as text', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  const remote = select(page, 'native-remote').locator('option:not(.placeholder)')
  await expect(remote).toHaveCount(2)
  await expect(remote.first()).toHaveText(/<img src=x onerror=.*Mallory/)
  await expect(remote.nth(1)).toHaveText('Alice & Bob <b>bold</b>')
  await expectNothingInjected(page)
})

test('a native select fed remote grouped options prints their labels as text', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  const item = select(page, 'native-remote-groups').locator('optgroup option')
  await expect(item).toHaveCount(1)
  await expect(item).toHaveText(/<\/select><img src=x onerror=.*Eve/)
  await expectNothingInjected(page)
})

test('a host-supplied html key is still rendered as markup', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await trigger(page, 'trusted').click()
  await expect(options(page, 'trusted').first().locator('strong[data-trusted]')).toHaveCount(1)
})

// A colour is not text, it is a CSS value. Escaping keeps it inside the style
// attribute but `red; position: fixed` still adds declarations, so only real
// colours may reach it. Every swatch on the page must stay put; the valid one must
// still be painted.
const swatches = (page, name) => select(page, name).locator('[style*="background-color"]')

async function expectSwatchesContained (page, name) {
  const swatchStyles = await swatches(page, name).evaluateAll(els => els.map(el => {
    const style = getComputedStyle(el)
    return { position: style.position, background: style.backgroundColor }
  }))

  expect(swatchStyles.filter(s => s.position === 'fixed')).toEqual([])
}

// open the listbox for each pick if the previous pick left it closed
async function pickEach (page, name, labels) {
  for (const label of labels) {
    if (!(await options(page, name).first().isVisible())) {
      // the no-Tailwind rig stretches the trigger over its chips, so a centred click
      // lands on the chip row instead of the button — click its corner
      await trigger(page, name).click({ position: { x: 3, y: 3 } })
      await expect(options(page, name).first()).toBeVisible()
    }

    await options(page, name).filter({ hasText: label }).click()
  }
}

test('a static option colour cannot add style declarations', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await trigger(page, 'static').click()
  await expect(options(page, 'static')).toHaveCount(4)

  await expectSwatchesContained(page, 'static')
  const painted = await swatches(page, 'static').evaluateAll(els => els.map(el => getComputedStyle(el).backgroundColor))
  expect(painted).toContain('rgb(34, 197, 94)')
})

test('multiple chips render a hostile label and colour as text and a contained swatch', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await pickEach(page, 'multiple', ['<img src=x onerror=', 'Declaration', 'Green'])

  // the picked options sit in the trigger as chips, built by the x-for template
  await expect(trigger(page, 'multiple')).toContainText('<img src=x onerror=')
  await expect(trigger(page, 'multiple').locator('img')).toHaveCount(0)
  await expectSwatchesContained(page, 'multiple')

  // the valid colour is still painted; the hostile one draws no swatch at all
  const painted = await trigger(page, 'multiple').locator('[style*="background-color"]')
    .evaluateAll(els => els.map(el => getComputedStyle(el).backgroundColor))
  expect(painted).toEqual(['rgb(34, 197, 94)'])
  await expectNothingInjected(page)
})

test('multiple remote chips keep a hostile colour contained', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await pickEach(page, 'remote-multiple', ['<img src=x onerror=', 'Alice'])

  await expect(trigger(page, 'remote-multiple')).toContainText('<img src=x onerror=')
  await expectSwatchesContained(page, 'remote-multiple')
  const painted = await trigger(page, 'remote-multiple').locator('[style*="background-color"]')
    .evaluateAll(els => els.map(el => getComputedStyle(el).backgroundColor))
  expect(painted).toEqual(['rgb(34, 197, 94)'])
  await expectNothingInjected(page)
})

test('multiple="list" renders the picked hostile option as text', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await pickEach(page, 'list', ['<img src=x onerror=', 'Declaration'])

  // the list rows above the trigger are rendered with x-html from the selected option
  await expect(select(page, 'list')).toContainText('<img src=x onerror=')
  await expectSwatchesContained(page, 'list')
  await expectNothingInjected(page)
})

test('the filter variant renders hostile options as text', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await select(page, 'filter').locator('[data-atom-dropdown] > button').click()
  await expect(options(page, 'filter')).toHaveCount(4)
  await expect(options(page, 'filter').first()).toContainText('<img src=x onerror=')
  await expect(options(page, 'filter').first().locator('img')).toHaveCount(0)
  await expectSwatchesContained(page, 'filter')

  await options(page, 'filter').first().click()
  await expect(select(page, 'filter')).toContainText('<img src=x onerror=')
  await expect(select(page, 'filter').locator('img')).toHaveCount(0)
  await expectNothingInjected(page)
})

test('the filter variant in multiple mode renders hostile options as text', async ({ page }) => {
  await page.goto('/atom/e2e/select-xss')

  await select(page, 'filter-multiple').locator('[data-atom-dropdown] > button').click()
  await options(page, 'filter-multiple').first().click()

  await expect(select(page, 'filter-multiple')).toContainText('<img src=x onerror=')
  await expect(select(page, 'filter-multiple').locator('img')).toHaveCount(0)
  await expectNothingInjected(page)
})
