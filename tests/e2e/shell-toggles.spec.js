import { test, expect } from '@playwright/test'

// The theme switcher in the shells' account menu, and the sidebar's collapse button. On the consumer Tailwind build
// (`node tests/consumer/build.mjs` first).

async function open (page, { shell = 'sidebar', tw = 'latest', query = '' } = {}) {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/shell-account?tw=${tw}&shell=${shell}${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const isDark = (page) => page.evaluate(() => document.documentElement.classList.contains('dark'))

for (const shell of ['sidebar', 'header']) {
  test.describe(`${shell} shell: the theme row`, () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 })
      // a clean slate once per tab: a reload keeps what the page stored
      await page.addInitScript(() => {
        try {
          if (!window.sessionStorage.getItem('st-clean')) {
            window.sessionStorage.setItem('st-clean', '1')
            window.localStorage.removeItem('darkmode')
          }
        } catch {}
      })
    })

    test('picking a theme applies it and keeps the account menu open', async ({ page }) => {
      await open(page, { shell, query: '&dark=1' })

      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()

      const row = page.locator('[data-atom-theme-row]:visible')
      await expect(row).toBeVisible()
      await expect(row.getByRole('group', { name: 'Theme' })).toBeVisible()

      const pressed = async () => ({
        light: await row.getByRole('button', { name: 'Light' }).getAttribute('aria-pressed'),
        dark: await row.getByRole('button', { name: 'Dark' }).getAttribute('aria-pressed'),
        system: await row.getByRole('button', { name: 'System' }).getAttribute('aria-pressed'),
      })

      expect(await pressed()).toEqual({ light: 'false', dark: 'false', system: 'true' })

      await row.getByRole('button', { name: 'Dark' }).click()

      expect(await isDark(page)).toBe(true)
      await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeVisible()
      await expect(row).toBeVisible()
      expect(await pressed()).toEqual({ light: 'false', dark: 'true', system: 'false' })

      await row.getByRole('button', { name: 'Light' }).click()
      expect(await isDark(page)).toBe(false)
      await expect(row).toBeVisible()
      expect(await pressed()).toEqual({ light: 'true', dark: 'false', system: 'false' })
    })

    test('the choice survives a reload, and the row shows it', async ({ page }) => {
      await open(page, { shell, query: '&dark=1' })

      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      await page.locator('[data-atom-theme-row]:visible').getByRole('button', { name: 'Dark' }).click()
      expect(await isDark(page)).toBe(true)

      await page.reload()
      await page.waitForFunction(() => window.Alpine !== undefined)

      expect(await isDark(page)).toBe(true)
      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      await expect(page.locator('[data-atom-theme-row]:visible').getByRole('button', { name: 'Dark' })).toHaveAttribute('aria-pressed', 'true')
    })

    test('there is no row, and no toggle, with dark mode off', async ({ page }) => {
      await open(page, { shell })

      await page.locator('[data-atom-shell-brand] [data-atom-account-trigger]').click()
      await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeVisible()
      await expect(page.locator('[data-atom-theme-row]')).toHaveCount(0)
      await expect(page.locator('[data-atom-darkmode-toggle]')).toHaveCount(0)
    })
  })
}
