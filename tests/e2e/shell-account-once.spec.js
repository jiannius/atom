import { test, expect } from '@playwright/test'

// layouts.sidebar prints its `dropdown` slot once (issue #267): a Livewire component in it has one root, so it is live from the brand row (or
// the foot) at lg and up, and from the avatar in the top bar below lg. Both triggers open the one menu, anchored to the one that was pressed.
// On the consumer Tailwind build (`node tests/consumer/build.mjs` first).

const PHONE = { width: 390, height: 800 }
const DESKTOP = { width: 1280, height: 800 }

async function open (page, size, query = '') {
  const css = await page.request.get('/atom/e2e/consumer-css/latest')
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.setViewportSize(size)
  await page.goto(`/atom/e2e/shell-account?tw=latest&shell=sidebar&lw=1${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined && window.Livewire?.all().length === 1)
}

const phoneTrigger = (page) => page.locator('[data-atom-header] button[aria-label="Account"]')
const brandTrigger = (page) => page.locator('[data-atom-shell-brand] [data-atom-account-trigger]')
const footTrigger = (page) => page.locator('[data-atom-sidebar-scroll] button[data-atom-dropdown-remote]')
const menu = (page) => page.locator('#atom-account-menu')
const count = (page) => page.locator('[data-testid="account-count"]')
const box = async (locator) => locator.evaluate((el) => { const r = el.getBoundingClientRect(); return { x: r.x, y: r.y, right: r.right, bottom: r.bottom } })
const expanded = (page) => page.evaluate(() => [...document.querySelectorAll('[data-atom-dropdown-remote]')].map((el) => el.getAttribute('aria-expanded')))

test.describe('the account menu is printed once', () => {
  test('one root for the component, one menu, and every trigger names it', async ({ page }) => {
    await open(page, DESKTOP)

    await expect(page.locator('[data-testid="account-menu-root"]')).toHaveCount(1)
    await expect(page.locator('[data-atom-menu][popover]')).toHaveCount(1)
    await expect(menu(page)).toHaveAttribute('popover', '')

    const controls = await page.evaluate(() => [...document.querySelectorAll('[data-atom-dropdown-remote]')].map((el) => el.getAttribute('aria-controls')))
    expect(controls).toEqual(['atom-account-menu', 'atom-account-menu'])
    // nothing is open yet
    expect(await expanded(page)).toEqual(['false', 'false'])
  })

  test('below lg the avatar opens it, a wire:click in the Livewire component works, and the menu hangs under the avatar', async ({ page }) => {
    await open(page, PHONE)

    const trigger = phoneTrigger(page)
    await trigger.click()
    await expect(menu(page)).toBeVisible()
    expect(await expanded(page)).toContain('true')
    await expect(trigger).toHaveAttribute('aria-expanded', 'true')

    const t = await box(trigger)
    const m = await box(menu(page))
    expect(m.y).toBeGreaterThanOrEqual(t.bottom - 1)
    expect(m.y).toBeLessThan(t.bottom + 12)
    // on screen, and at the end of the bar (the avatar is at the right edge)
    expect(m.x).toBeGreaterThanOrEqual(0)
    expect(m.right).toBeLessThanOrEqual(PHONE.width)
    expect(Math.abs(m.right - t.right)).toBeLessThan(16)

    await page.getByTestId('account-bump').click()
    await expect(count(page)).toHaveText('1')
    await expect(menu(page)).toBeHidden()
    await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  })

  test('from lg up the brand row opens it, the component is live, and the menu hangs under the row', async ({ page }) => {
    await open(page, DESKTOP)

    const trigger = brandTrigger(page)
    await trigger.click()
    await expect(menu(page)).toBeVisible()

    const t = await box(trigger)
    const m = await box(menu(page))
    expect(m.y).toBeGreaterThanOrEqual(t.bottom - 1)
    expect(m.y).toBeLessThan(t.bottom + 12)
    expect(Math.abs(m.x - t.x)).toBeLessThan(16)

    await page.getByTestId('account-bump').click()
    await expect(count(page)).toHaveText('1')

    // and again: the same component, still live
    await trigger.click()
    await page.getByTestId('account-bump').click()
    await expect(count(page)).toHaveText('2')
  })

  test('with account="end" the foot row opens it', async ({ page }) => {
    await open(page, DESKTOP, '&account=end')

    const trigger = footTrigger(page)
    await expect(trigger).toBeVisible()
    await trigger.click()
    await expect(menu(page)).toBeVisible()

    // the foot is at the bottom of the sidebar: the menu flips above it, beside the row
    const t = await box(trigger)
    const m = await box(menu(page))
    expect(m.bottom).toBeLessThanOrEqual(t.y + 12)
    expect(Math.abs(m.x - t.x)).toBeLessThan(16)

    await page.getByTestId('account-bump').click()
    await expect(count(page)).toHaveText('1')
  })

  for (const [name, size, trigger, query] of [
    ['the avatar below lg', PHONE, phoneTrigger, ''],
    ['the brand row at lg', DESKTOP, brandTrigger, ''],
    ['the foot row at lg', DESKTOP, footTrigger, '&account=end'],
  ]) {
    test(`keyboard: ${name} opens it on the first item, arrows walk it, Escape closes it and the focus returns to it`, async ({ page }) => {
      await open(page, size, query)

      const button = trigger(page)
      await button.focus()
      await page.keyboard.press('Enter')

      await expect(menu(page)).toBeVisible()
      await expect(page.getByTestId('account-bump')).toBeFocused()
      await expect(button).toHaveAttribute('aria-expanded', 'true')

      await page.keyboard.press('ArrowDown')
      await expect(page.getByRole('menuitem', { name: 'Settings' })).toBeFocused()

      await page.keyboard.press('Escape')
      await expect(menu(page)).toBeHidden()
      await expect(button).toBeFocused()
      await expect(button).toHaveAttribute('aria-expanded', 'false')

      // ArrowDown on the trigger opens it on the first item, ArrowUp on the last
      await page.keyboard.press('ArrowUp')
      await expect(menu(page)).toBeVisible()
      await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeFocused()
      await page.keyboard.press('Escape')
      await expect(button).toBeFocused()

      // Enter on the item runs the Livewire action, then the menu closes
      await page.keyboard.press('Enter')
      await expect(page.getByTestId('account-bump')).toBeFocused()
      await page.keyboard.press('Enter')
      await expect(count(page)).toHaveText('1')
    })
  }

  test('a second press on the trigger closes it', async ({ page }) => {
    await open(page, DESKTOP)

    await brandTrigger(page).click()
    await expect(menu(page)).toBeVisible()
    await brandTrigger(page).click()
    await expect(menu(page)).toBeHidden()
    await expect(brandTrigger(page)).toHaveAttribute('aria-expanded', 'false')
  })

  test('in the stashed drawer the brand row opens it, Escape closes the menu first and the drawer on the next press', async ({ page }) => {
    await open(page, PHONE)

    await page.locator('[data-atom-sidebar-toggle]').click()
    await expect(brandTrigger(page)).toBeVisible()
    await expect.poll(() => page.evaluate(() => document.querySelector('[data-atom-sidebar]').getBoundingClientRect().right)).toBeGreaterThan(200)

    await brandTrigger(page).click()
    await expect(menu(page)).toBeVisible()

    // anchored to the drawer's row, not to the avatar at the far end of the bar
    const t = await box(brandTrigger(page))
    const m = await box(menu(page))
    expect(Math.abs(m.x - t.x)).toBeLessThan(16)
    expect(m.y).toBeGreaterThanOrEqual(t.bottom - 1)
    // the avatar is the one that is not expanded
    await expect(phoneTrigger(page)).toHaveAttribute('aria-expanded', 'false')
    await expect(brandTrigger(page)).toHaveAttribute('aria-expanded', 'true')

    await page.getByTestId('account-bump').click()
    await expect(count(page)).toHaveText('1')

    await brandTrigger(page).click()
    await expect(menu(page)).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(menu(page)).toBeHidden()
    expect(await page.evaluate(() => document.body.hasAttribute('data-show-stashed-sidebar'))).toBe(true)

    await page.keyboard.press('Escape')
    expect(await page.evaluate(() => document.body.hasAttribute('data-show-stashed-sidebar'))).toBe(false)
  })

  test('pressing the other trigger while it is open opens it again from there', async ({ page }) => {
    await open(page, PHONE)

    await page.locator('[data-atom-sidebar-toggle]').click()
    await brandTrigger(page).click()
    await expect(menu(page)).toBeVisible()
    await expect(brandTrigger(page)).toHaveAttribute('aria-expanded', 'true')

    await phoneTrigger(page).click()
    await expect(phoneTrigger(page)).toHaveAttribute('aria-expanded', 'true')
    await expect(brandTrigger(page)).toHaveAttribute('aria-expanded', 'false')
    await expect(menu(page)).toBeVisible()

    const t = await box(phoneTrigger(page))
    const m = await box(menu(page))
    expect(Math.abs(m.right - t.right)).toBeLessThan(16)

    await page.getByTestId('account-bump').click()
    await expect(count(page)).toHaveText('1')
  })

  test('a Livewire morph of the component under the open menu keeps it open, anchored, and the aria in place', async ({ page }) => {
    await open(page, PHONE)

    await phoneTrigger(page).click()
    await expect(menu(page)).toBeVisible()
    const before = await box(menu(page))

    await page.evaluate(() => window.Livewire.all()[0].$wire.bump())
    await expect(count(page)).toHaveText('1')

    await expect(menu(page)).toBeVisible()
    await expect(phoneTrigger(page)).toHaveAttribute('aria-expanded', 'true')
    await expect(phoneTrigger(page)).toHaveAttribute('aria-controls', 'atom-account-menu')
    expect(await box(menu(page))).toEqual(before)
  })
})
