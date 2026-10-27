import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// A form footer (<atom:form.actions>, plain or `sticky`, as <atom:form.modal> draws it) is `justify-between`: the first child at the
// start, the last at the end. A LONE child (one button, or the default Save) used to land at the start; it sits at the end. On the
// consumer's Tailwind, at the hosts' minimum (floor, 4.0.7) and the newest 4.x (latest).

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { x: r.x, right: r.right, width: r.width }
})

const padding = (locator) => locator.evaluate((el) => {
  const s = getComputedStyle(el)
  return { left: parseFloat(s.paddingLeft), right: parseFloat(s.paddingRight) }
})

for (const tw of ['floor', 'latest']) {
  test.describe(`a lone footer action, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1280, height: 800 } })

    test('in a modal footer a lone Save ends at the footer\'s content edge', async ({ page }) => {
      await open(page, { path: 'form-modal-width', tw })

      const dialog = page.getByTestId('dialog')
      await expect(dialog).toHaveAttribute('open', '')
      await dialog.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished.catch(() => null))))

      const footer = dialog.locator('[data-atom-form-actions]')
      const bar = await box(footer)
      const pad = await padding(footer)
      const save = await box(footer.locator('button[type=submit]'))

      expect(await footer.locator('> *').count()).toBe(1)
      expect(Math.abs(save.right - (bar.right - pad.right))).toBeLessThanOrEqual(1)
      expect(save.x).toBeGreaterThan(bar.x + pad.left + 50)
    })

    test('two children: the first at the start, the last at the end', async ({ page }) => {
      await open(page, { path: 'form-modal-width', tw, extra: '&actions=1' })

      const dialog = page.getByTestId('dialog')
      await expect(dialog).toHaveAttribute('open', '')
      await dialog.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished.catch(() => null))))

      const footer = dialog.locator('[data-atom-form-actions]')
      const bar = await box(footer)
      const pad = await padding(footer)
      const save = await box(footer.locator('button[type=submit]'))
      const last = await box(footer.locator('[data-atom-form-modal-actions]'))

      expect(await footer.locator('> *').count()).toBe(2)
      expect(Math.abs(save.x - (bar.x + pad.left))).toBeLessThanOrEqual(1)
      expect(Math.abs(last.right - (bar.right - pad.right))).toBeLessThanOrEqual(1)
    })

    test('a start slot and a button (a slide-over footer): the label at the start, the button at the end', async ({ page }) => {
      await open(page, { path: 'slide-over-short', tw })

      const dialog = page.getByTestId('slide-over')
      await expect(dialog).toHaveAttribute('open', '')
      await dialog.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished.catch(() => null))))

      const footer = dialog.locator('[data-atom-form-actions]')
      const bar = await box(footer)
      const pad = await padding(footer)
      const start = await box(footer.locator('[data-atom-form-actions-start]'))
      const submit = await box(page.getByTestId('short-submit'))

      expect(Math.abs(start.x - (bar.x + pad.left))).toBeLessThanOrEqual(1)
      expect(Math.abs(submit.right - (bar.right - pad.right))).toBeLessThanOrEqual(1)
    })

    test('a plain footer (no sticky) with one button ends at the end too', async ({ page }) => {
      await open(page, { path: 'form-modal-width', tw, extra: '&plain=1' })

      const footer = page.getByTestId('plain-form').locator('[data-atom-form-actions]')
      const bar = await box(footer)
      const save = await box(footer.locator('button[type=submit]'))

      expect(await footer.locator('> *').count()).toBe(1)
      expect(Math.abs(save.right - bar.right)).toBeLessThanOrEqual(1)
      expect(save.x).toBeGreaterThan(bar.x + 50)
    })
  })
}
