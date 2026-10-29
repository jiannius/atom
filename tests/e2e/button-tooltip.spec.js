import { test, expect } from '@playwright/test'

// Drives /atom/e2e/button-tooltip (resources/views/e2e/button-tooltip.blade.php): the `tooltip` prop of <atom:button>, which wraps
// the button in <atom:tooltip>, and the three changes to tooltip.js that came with it (the description is added to the trigger's,
// not written over it; a trigger whose name is the tooltip's text is not also described by it; no tooltip over an open popup).

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const contentOf = (page, name) => probe(page, name).locator('[data-atom-tooltip-content]')

test.beforeEach(async ({ page }) => {
  await page.goto('/atom/e2e/button-tooltip')
})

test('shows on hover and hides on mouse out', async ({ page }) => {
  const content = contentOf(page, 'icon')

  await expect(content).toBeHidden()
  await page.getByTestId('icon').hover()
  await expect(content).toBeVisible()
  await expect(content).toHaveText('Add a contact')

  await page.mouse.move(0, 0)
  await expect(content).toBeHidden()
})

test('shows on keyboard focus and hides on blur', async ({ page }) => {
  const content = contentOf(page, 'icon')

  await page.keyboard.press('Tab')
  await expect(page.getByTestId('icon')).toBeFocused()
  await expect(content).toBeVisible()

  await page.getByTestId('icon').blur()
  await expect(content).toBeHidden()
})

test('an icon-only button has one accessible name and no duplicate description', async ({ page }) => {
  const button = page.getByTestId('icon')

  await expect(button).toHaveAttribute('aria-label', 'Add a contact')
  await expect(button).toHaveAccessibleName('Add a contact')
  await expect(button).not.toHaveAttribute('aria-describedby')
  await expect(button).toHaveAccessibleDescription('')
  await expect(page.getByRole('button', { name: 'Add a contact' })).toHaveCount(1)
})

test('a hand-wrapped button named with the tooltip\'s words is announced once', async ({ page }) => {
  const button = page.getByTestId('wrapped')

  await expect(button).toHaveAccessibleName('Show archived')
  await expect(button).not.toHaveAttribute('aria-describedby')
  await expect(button).toHaveAccessibleDescription('')
  await button.hover()
  await expect(contentOf(page, 'wrapped')).toBeVisible()
})

test('a labelled button keeps its own description and gains the tooltip as another', async ({ page }) => {
  const button = page.getByTestId('described')
  const id = await contentOf(page, 'described').getAttribute('id')

  expect(id).toBeTruthy()
  await expect(button).toHaveAttribute('aria-describedby', `described-hint ${id}`)
  await expect(button).not.toHaveAttribute('aria-label')
  await expect(button).toHaveAccessibleName('Export')
  await expect(button).toHaveAccessibleDescription('Opens the report Export as CSV')
})

test('in a dropdown trigger the tooltip hides when the menu opens and does not come back over it', async ({ page }) => {
  const trigger = page.getByTestId('menu')
  const content = contentOf(page, 'menu')
  const menu = probe(page, 'menu').locator('[data-atom-menu]')

  await trigger.hover()
  await expect(content).toBeVisible()

  await trigger.click()
  await expect(menu).toBeVisible()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  await expect(content).toBeHidden()

  // the pointer leaves the wrapper and comes back while the menu is open
  await page.mouse.move(0, 0)
  await trigger.hover()
  await expect(content).toBeHidden()
})

test('the tooltip does not pop up when Escape returns the focus to the trigger', async ({ page }) => {
  const trigger = page.getByTestId('menu')
  const content = contentOf(page, 'menu')
  const menu = probe(page, 'menu').locator('[data-atom-menu]')

  // by keyboard, so the pointer is nowhere near
  await page.mouse.move(0, 0)
  await trigger.focus()
  await expect(content).toBeVisible()
  await page.keyboard.press('Enter')
  await expect(menu).toBeVisible()
  await expect(content).toBeHidden()

  await page.keyboard.press('Escape')
  await expect(menu).toBeHidden()
  await expect(trigger).toBeFocused()
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  await page.waitForTimeout(150)
  await expect(content).toBeHidden()
})

test('with the menu closed the tooltip shows again on hover', async ({ page }) => {
  const trigger = page.getByTestId('menu')
  const content = contentOf(page, 'menu')

  await trigger.click()
  await page.keyboard.press('Escape')
  await page.mouse.move(0, 0)
  await trigger.hover()
  await expect(content).toBeVisible()
})

test('an outer x-data `show` toggled from a tooltipped button changes the outer value', async ({ page }) => {
  const value = page.getByTestId('scope-value')

  await expect(value).toHaveText('false')
  await page.getByTestId('scope').click()
  await expect(value).toHaveText('true')
  await page.getByTestId('scope').click()
  await expect(value).toHaveText('false')
})

test('a long block label truncates inside the wrapper on the consumer build', async ({ page }) => {
  await page.goto('/atom/e2e/button-tooltip?tw=latest')
  await page.evaluate(() => document.fonts.ready)

  const button = page.getByTestId('long')
  const column = await page.getByTestId('column').boundingBox()
  const box = await button.boundingBox()
  const label = button.locator('[data-atom-button-label]')

  expect(box.x + box.width).toBeLessThanOrEqual(column.x + column.width + 0.5)
  expect(await label.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true)
  expect(await label.evaluate((el) => getComputedStyle(el).textOverflow)).toBe('ellipsis')
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => window.innerWidth))

  await button.hover()
  await expect(contentOf(page, 'long')).toBeVisible()
})
