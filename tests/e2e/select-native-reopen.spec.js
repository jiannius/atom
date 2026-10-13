import { test, expect } from '@playwright/test'

// Drives /atom/e2e/select-native-reopen (resources/views/e2e/select-native-reopen.blade.php), issue #84.
//
// On Windows, Linux and ChromeOS Chromium draws the native list itself, and after a pick it dispatches `change`, `mouseup`
// and then a `click` on the <select> (InternalPopupMenu::SetValueAndClosePopup). That click bubbled to the wrapper, whose
// handler called showPicker() and reopened the list at once. macOS uses the OS menu and never fires it. The popup cannot
// be driven here, so showPicker() is stubbed to count its calls (and to throw as the real one does for a disabled select),
// and the post-pick click is dispatched by hand.

const wrapper = (page, name) => page.locator(`[data-probe="${name}"] [data-atom-select-native]`)
const select = (page, name) => page.locator(`[data-probe="${name}"] select`)
const pickerCalls = page => page.evaluate(() => window.__pickerCalls)

// a click that did not land on the <select>: its padding or icon area, which is the wrapper's inner box
const clickBesideSelect = (page, name) => wrapper(page, name).locator(':scope > div:not([class*="pointer-events-none"])').first()
  .evaluate(el => el.dispatchEvent(new MouseEvent('click', { bubbles: true })))

const clickSelect = (page, name) => select(page, name)
  .evaluate(el => el.dispatchEvent(new MouseEvent('click', { bubbles: true })))

// Alpine has run when the wrapper carries its data: a click before that reaches no handler and proves nothing
async function open (page) {
  await page.goto('/atom/e2e/select-native-reopen')
  await page.waitForFunction(() => [...document.querySelectorAll('[data-atom-select-native]')].every(el => el._x_dataStack))
}

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => {
    window.__pickerCalls = 0
    HTMLSelectElement.prototype.showPicker = function () {
      if (this.disabled) throw new DOMException('select is disabled', 'InvalidStateError')
      window.__pickerCalls++
    }
  })
})

test('the click Chromium fires on the select after a pick does not reopen the list', async ({ page }) => {
  await open(page)

  await select(page, 'single').selectOption('card')
  await clickSelect(page, 'single')

  await expect(select(page, 'single')).toHaveValue('card')
  expect(await pickerCalls(page)).toBe(0)
})

test('a click on an option inside the select does not call showPicker either', async ({ page }) => {
  await open(page)

  await select(page, 'single').locator('option[value="cash"]')
    .evaluate(el => el.dispatchEvent(new MouseEvent('click', { bubbles: true })))

  expect(await pickerCalls(page)).toBe(0)
})

test('a click on the wrapper outside the select still opens the list', async ({ page }) => {
  await open(page)

  await clickBesideSelect(page, 'single')
  expect(await pickerCalls(page)).toBe(1)

  // and a click on the wrapper element itself
  await wrapper(page, 'single').evaluate(el => el.dispatchEvent(new MouseEvent('click', { bubbles: true })))
  expect(await pickerCalls(page)).toBe(2)
})

test('multiple mode picks into the chip list and is not reopened by the click on the select', async ({ page }) => {
  await open(page)

  await select(page, 'multiple').selectOption('b')
  await clickSelect(page, 'multiple')

  // the pick became a chip and the select went back to its placeholder
  await expect(wrapper(page, 'multiple').locator('[x-text="item.label"]', { hasText: 'Beta' })).toBeVisible()
  await expect(select(page, 'multiple')).toHaveValue('')
  expect(await pickerCalls(page)).toBe(0)

  await clickBesideSelect(page, 'multiple')
  expect(await pickerCalls(page)).toBe(1)
})

test('a showPicker() that throws does not raise an uncaught error', async ({ page }) => {
  const errors = []
  page.on('pageerror', error => errors.push(error.message))
  await open(page)

  // the stub throws for a disabled select, as the real one does; nothing may escape the handler
  await clickBesideSelect(page, 'disabled')
  await wrapper(page, 'disabled').evaluate(el => el.dispatchEvent(new MouseEvent('click', { bubbles: true })))

  expect(errors).toEqual([])
  expect(await pickerCalls(page)).toBe(0)
})
