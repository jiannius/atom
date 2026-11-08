import { test, expect } from '@playwright/test'

// The header shell's Escape is heard on the window, so it closes the innermost open layer whatever has the focus: Safari does not focus a
// button on click, and a click on blank space in the panel, the search popover's edge or the bar leaves the focus on <body> in every engine.
// `likeSafari()` does the same in any engine (a prevented mousedown does not focus), so these fail in Chromium without the fix too.

async function open(page, shell, width) {
  await page.setViewportSize({ width, height: 800 })
  await page.goto(`/atom/e2e/shell-${shell}?tw=latest`)
  await page.evaluate(() => document.fonts.ready)
  await page.waitForFunction(() => window.Alpine !== undefined)
}
// Safari does not focus a button on click. A prevented mousedown does the same in every engine, so this runs in Chromium too.
const likeSafari = (locator) => locator.evaluate((el) => { document.activeElement?.blur(); el.addEventListener('mousedown', (e) => e.preventDefault()) })
const bodyHasFocus = (page) => expect.poll(() => page.evaluate(() => document.activeElement === document.body)).toBe(true)

test('More opened by a click closes on Escape with the focus on the page', async ({ page }) => {
  await open(page, 'header-wide', 1100)
  const trigger = page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]')
  await likeSafari(trigger)
  await trigger.click()
  await bodyHasFocus(page)
  await page.keyboard.press('Escape')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  await expect(trigger).toBeFocused()
})

test('a navlist.group opened by a click closes on Escape with the focus on the page', async ({ page }) => {
  await open(page, 'header', 1280)
  const trigger = page.locator('[data-atom-navlist-group-trigger]', { hasText: 'Reports' })
  await likeSafari(trigger)
  await trigger.click()
  await bodyHasFocus(page)
  await page.keyboard.press('Escape')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  await expect(trigger).toBeFocused()
})

test('the menu panel closes on Escape after a click on its blank space', async ({ page }) => {
  await open(page, 'header-wide', 768)
  const button = page.locator('[data-atom-header-menu-button]')
  await button.click()
  const b = await page.locator('[data-atom-header-nav]').boundingBox()
  await page.mouse.click(b.x + b.width - 8, b.y + b.height - 8)
  await bodyHasFocus(page)
  await page.keyboard.press('Escape')
  await expect(button).toHaveAttribute('aria-expanded', 'false')
  await expect(button).toBeFocused()
})

test('the search popover closes on Escape after a click on its edge', async ({ page }) => {
  await open(page, 'header-wide', 1100)
  const button = page.locator('[data-atom-shell-search-button]')
  await button.click()
  await expect(page.locator('[data-atom-shell-search]')).toBeVisible()
  const b = await page.locator('[data-atom-shell-search]').boundingBox()
  await page.mouse.click(b.x + 2, b.y + 2)
  await bodyHasFocus(page)
  await page.keyboard.press('Escape')
  await expect(button).toHaveAttribute('aria-expanded', 'false')
  await expect(button).toBeFocused()
})

test('with the focus on the page, a dropdown in the panel has the first Escape and the panel the next', async ({ page }) => {
  await open(page, 'header-wide', 768)
  const button = page.locator('[data-atom-header-menu-button]')
  await button.click()
  const context = page.locator('[data-atom-shell-context]').getByRole('button', { name: /Acme Manufacturing/ })
  await likeSafari(context)
  await context.click()
  const item = page.getByRole('menuitem', { name: 'Acme Trading' })
  await expect(item).toBeVisible()
  await bodyHasFocus(page)
  await page.keyboard.press('Escape')
  await expect(item).toBeHidden()
  await expect(button).toHaveAttribute('aria-expanded', 'true')
  await page.keyboard.press('Escape')
  await expect(button).toHaveAttribute('aria-expanded', 'false')
})

test('an open modal has Escape first: More, open behind it, stays', async ({ page }) => {
  await open(page, 'header-wide', 1100)
  const trigger = page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]')
  await likeSafari(trigger)
  await trigger.click()
  await page.evaluate(() => {
    const d = document.createElement('dialog')
    d.id = 'zz'
    d.innerHTML = '<button>ok</button>'
    document.body.append(d)
    d.showModal()
  })
  await page.keyboard.press('Escape')
  await expect(page.locator('dialog#zz')).toBeHidden()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
})

test('an Escape the header takes does not reach the page: one press, one layer', async ({ page }) => {
  await open(page, 'header-wide', 1100)
  await page.evaluate(() => { window.__heard = 0; document.addEventListener('keydown', (e) => { if (e.key === 'Escape') window.__heard++ }) })
  const trigger = page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]')
  await likeSafari(trigger)
  await trigger.click()
  await page.keyboard.press('Escape')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
  expect(await page.evaluate(() => window.__heard)).toBe(0)
  await page.keyboard.press('Escape')
  expect(await page.evaluate(() => window.__heard)).toBe(1)
})

test('with nothing open the header leaves Escape alone: not prevented, not stopped', async ({ page }) => {
  for (const width of [768, 1280]) {
    await open(page, 'header-wide', width)
    await page.evaluate(() => {
      window.__seen = []
      window.addEventListener('keydown', (e) => { if (e.key === 'Escape') window.__seen.push(e.defaultPrevented) })
    })
    await page.keyboard.press('Escape')
    expect(await page.evaluate(() => window.__seen)).toEqual([false])
  }
})

test('a menu state left true at lg does not swallow Escape', async ({ page }) => {
  await open(page, 'header-wide', 1280)
  await page.evaluate(() => {
    window.Alpine.$data(document.querySelector('[data-atom-header]')).menu = true
    window.__seen = []
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') window.__seen.push(e.defaultPrevented) })
  })
  await page.keyboard.press('Escape')
  expect(await page.evaluate(() => window.__seen)).toEqual([false])
})

test('a held Escape does not close a layer', async ({ page }) => {
  await open(page, 'header-wide', 1100)
  const trigger = page.locator('[data-atom-header-more] > [data-atom-navlist-group-trigger]')
  await likeSafari(trigger)
  await trigger.click()
  await page.evaluate(() => window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true, repeat: true })))
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  await page.keyboard.press('Escape')
  await expect(trigger).toHaveAttribute('aria-expanded', 'false')
})

test('a navlist.group in the sidebar is not closed or disturbed by Escape', async ({ page }) => {
  await open(page, 'sidebar', 1280)
  const errors = []
  page.on('pageerror', (e) => errors.push(e.message))
  const group = page.locator('[data-atom-navlist-group-toggle]').first()
  await expect(group).toHaveAttribute('aria-expanded', 'true')
  await group.focus()
  await page.keyboard.press('Escape')
  await expect(group).toHaveAttribute('aria-expanded', 'true')
  expect(errors).toEqual([])
})

test('an open modal has Escape first: a navlist.group open behind it stays', async ({ page }) => {
  await open(page, 'header', 1280)
  const trigger = page.locator('[data-atom-navlist-group-trigger]', { hasText: 'Reports' })
  await likeSafari(trigger)
  await trigger.click()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  await page.evaluate(() => {
    const d = document.createElement('dialog')
    d.id = 'zz'
    d.innerHTML = '<button>ok</button>'
    document.body.append(d)
    d.showModal()
  })
  await page.keyboard.press('Escape')
  await expect(page.locator('dialog#zz')).toBeHidden()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
})
