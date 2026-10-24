import { test, expect } from '@playwright/test'
import { open } from './controls-helpers.js'

// <atom:form.modal cols="1"> derives max-w-xl from cols, and a width class from the caller replaces it: `w-[28rem]` is a 448px dialog
// (it lost to max-w-xl + w-full before) and `max-w-md` caps it at 28rem. On a phone the dialog still stays inside the viewport.
// Floor and latest consumer builds.

const settle = (locator) => locator.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished.catch(() => null))))

for (const tw of ['floor', 'latest']) {
  test.describe(`form.modal width, ${tw} consumer build`, () => {
    for (const [cls, expected] of [['fixed', 448], ['max', 448], ['none', 576]]) {
      test(`${cls}: the dialog is ${expected}px at 1440`, async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 800 })
        await open(page, { path: 'form-modal-width', tw, extra: `&class=${cls}` })

        const dialog = page.getByTestId('dialog')
        await expect(dialog).toHaveAttribute('open', '')
        await settle(dialog)

        expect(Math.round((await dialog.boundingBox()).width)).toBe(expected)
      })
    }

    for (const cls of ['fixed', 'max', 'none']) {
      test(`${cls}: the dialog stays inside a 390px phone`, async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 800 })
        await open(page, { path: 'form-modal-width', tw, extra: `&class=${cls}` })

        const dialog = page.getByTestId('dialog')
        await expect(dialog).toHaveAttribute('open', '')
        await settle(dialog)

        const box = await dialog.boundingBox()

        expect(box.x).toBeGreaterThanOrEqual(0)
        expect(box.x + box.width).toBeLessThanOrEqual(390)
        // the dialog's floor is min-w-sm (336px), and `w-[28rem]` is capped by max-w-full
        expect(Math.round(box.width)).toBeGreaterThanOrEqual(336)
      })
    }
  })
}
