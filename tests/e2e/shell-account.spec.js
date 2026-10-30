import { test, expect } from '@playwright/test'

// The account menu in the brand row (account="brand", the default) of both shells, with atom's own brand: the logo tile
// goes home, the app name and the caret open the menu. On the consumer Tailwind build (`node tests/consumer/build.mjs` first).

async function open (page, shell, tw) {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/shell-account?tw=${tw}&shell=${shell}`)
  await page.waitForFunction(() => window.Alpine !== undefined)
}

const inViewport = (locator, width) => locator.evaluate((el, width) => {
  const r = el.getBoundingClientRect()
  return r.width > 0 && r.x >= 0 && r.right <= width
}, width)

for (const tw of ['floor', 'latest']) {
  for (const shell of ['sidebar', 'header']) {
    test.describe(`${shell} shell, ${tw} consumer build`, () => {
      test('the brand row opens the account menu by keyboard, and Escape closes it', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 })
        await open(page, shell, tw)

        const brand = page.locator('[data-atom-shell-brand]')
        await expect(brand.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/app')

        const trigger = brand.getByRole('button', { name: /Account/ })
        await expect(trigger).toBeVisible()
        // the only desktop account trigger: no foot row, no avatar at the bar's end
        await expect(page.locator('[data-atom-account-trigger]:visible, button[aria-label="Account"]:visible')).toHaveCount(1)

        await trigger.focus()
        await page.keyboard.press('Enter')

        const logout = page.getByRole('menuitem', { name: 'Log out' })
        await expect(logout).toBeVisible()
        await expect(page.locator('[data-atom-account-card]:visible')).toContainText('alex@example.com')
        // the menu opens downward from the brand row
        expect((await logout.boundingBox()).y).toBeGreaterThan((await trigger.boundingBox()).y)

        await page.keyboard.press('Escape')
        await expect(logout).toBeHidden()
        await expect(trigger).toBeFocused()
      })

      if (shell === 'sidebar') {
        test('the logo is centred on the name block, and the caret on the app name\'s line', async ({ page }) => {
          await page.setViewportSize({ width: 1280, height: 800 })
          await open(page, shell, tw)

          const centres = await page.evaluate(() => {
            const button = document.querySelector('[data-atom-shell-brand] [data-atom-account-trigger]')
            const mid = (el) => { const r = el.getBoundingClientRect(); return r.y + r.height / 2 }
            return {
              logo: mid(document.querySelector('[data-atom-shell-brand] a')),
              block: mid(button),
              name: mid(button.querySelector('.font-semibold')),
              caret: mid(button.querySelector('svg')),
            }
          })

          expect(Math.abs(centres.logo - centres.block)).toBeLessThanOrEqual(0.5)
          expect(Math.abs(centres.caret - centres.name)).toBeLessThanOrEqual(0.5)
        })
      }

      test('Log out is reachable at 390px without opening the sidebar', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 800 })
        await open(page, shell, tw)

        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390)

        // the sidebar shell keeps its top-bar account button on a phone; the top nav's brand row is always in view
        const trigger = shell === 'sidebar'
          ? page.locator('[data-atom-header]').getByRole('button', { name: 'Account' })
          : page.locator('[data-atom-shell-brand]').getByRole('button', { name: /Account/ })

        await expect(trigger).toBeVisible()
        expect(await inViewport(trigger, 390)).toBe(true)

        await trigger.click()

        const logout = page.getByRole('menuitem', { name: 'Log out' })
        await expect(logout).toBeVisible()
        expect(await inViewport(logout, 390)).toBe(true)
        await expect(logout).toHaveAttribute('href', '?page=logout')
      })
    })
  }
}
