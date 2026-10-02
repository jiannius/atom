import { test, expect } from '@playwright/test'

// <atom:form> shows a sticky error toast when a submit comes back with validation errors.
// The page is tests/Fixtures/FormErrorToastFixture.php: a "main" form (name, nickname,
// email; success raises the app's own "Saved" toast), a second form on the same component
// ("other") and a modal form. Query flags flip the main form's props: ?error-toast=0,
// ?disabled=1, ?recaptcha=1; ?no-toast=1 drops <atom:toast> from the page.

const open = async (page, query = '') => {
  await page.goto('/atom/e2e/form-error-toast' + query)
  await page.waitForFunction(() => window.Livewire && window.atom)
  await expect(page.locator('input[data-probe="name"]')).toBeVisible()
}

const submit = (page, which = 'main') => page.locator(`[data-submit="${which}"]`).click()

// The first-time visible text of a spec-level view onto the toast.
const toast = (page) => page.locator('[data-atom-toast]')

test.describe('Livewire action timing the toast hook relies on', () => {
  // The hook reads $wire.$errors from Livewire.interceptAction's onFinish. That is only
  // sound if the snapshot carrying THIS response's errors has been merged by then (not the
  // previous response's), and if the app's own server-dispatched toast has already fired,
  // which is what lets "close the error toast on success" leave a "Saved" alone. Both are
  // Livewire internals, so pin them here: a Livewire upgrade that reorders them fails
  // this block instead of silently showing stale errors.
  const probe = (page) => page.evaluate(() => {
    window.__timeline = []
    window.addEventListener('atom-toast-show', (e) => window.__timeline.push({ at: 'toast-show', message: e.detail?.message }))
    window.Livewire.interceptAction(({ action, onSuccess, onFinish }) => {
      let before = action.component.$wire.$errors.count()
      window.__timeline.push({ at: 'send', name: action.name, errorsBefore: before })
      onSuccess(() => window.__timeline.push({ at: 'success', name: action.name }))
      onFinish(() => window.__timeline.push({
        at: 'finish',
        name: action.name,
        errors: action.component.$wire.$errors.count(),
        messages: action.component.$wire.$errors.all(),
        // the morph has run by now: the fields carry their error markup
        domShowsErrors: !!action.component.el.querySelector('[data-atom-error]'),
      }))
    })
  })

  test('onFinish sees the errors of the response that just arrived', async ({ page }) => {
    await open(page)
    await probe(page)

    await submit(page)
    await expect.poll(() => page.evaluate(() => window.__timeline.some(t => t.at === 'finish'))).toBe(true)

    const finish = await page.evaluate(() => window.__timeline.find(t => t.at === 'finish'))
    expect(finish.name).toBe('save')
    expect(finish.errors).toBe(3)
    expect(finish.messages).toContain('Email is required.')
    expect(finish.domShowsErrors).toBe(true)
  })

  test('onFinish after a fixed resubmit sees no errors, not the previous response\'s', async ({ page }) => {
    await open(page)
    await probe(page)

    await submit(page)
    await expect.poll(() => page.evaluate(() => window.__timeline.filter(t => t.at === 'finish').length)).toBe(1)

    await page.locator('input[data-probe="name"]').fill('Acme')
    await page.locator('input[data-probe="nickname"]').fill('Ac')
    await page.locator('input[data-probe="email"]').fill('a@b.co')
    await submit(page)
    await expect.poll(() => page.evaluate(() => window.__timeline.filter(t => t.at === 'finish').length)).toBe(2)

    const second = await page.evaluate(() => window.__timeline.filter(t => t.at === 'finish')[1])
    expect(second.errors).toBe(0)
    await expect(page.locator('[data-saves]')).toHaveText('1')
  })

  test('the app\'s own server-dispatched toast fires before onFinish', async ({ page }) => {
    await open(page)
    await probe(page)

    await page.locator('input[data-probe="name"]').fill('Acme')
    await page.locator('input[data-probe="nickname"]').fill('Ac')
    await page.locator('input[data-probe="email"]').fill('a@b.co')
    await submit(page)
    await expect.poll(() => page.evaluate(() => window.__timeline.some(t => t.at === 'finish'))).toBe(true)

    const order = await page.evaluate(() => window.__timeline.filter(t => t.at !== 'send').map(t => t.at))
    expect(order.indexOf('toast-show')).toBeGreaterThan(-1)
    expect(order.indexOf('toast-show')).toBeLessThan(order.indexOf('finish'))
  })

  test('a $wire.save() call (the reCAPTCHA path) carries the same action name as wire:submit', async ({ page }) => {
    await open(page)
    await probe(page)

    await page.evaluate(() => {
      const el = document.querySelector('form[data-atom-form]').closest('[wire\\:id]')
      window.Livewire.find(el.getAttribute('wire:id')).save()
    })
    await expect.poll(() => page.evaluate(() => window.__timeline.some(t => t.at === 'finish'))).toBe(true)

    expect(await page.evaluate(() => window.__timeline.find(t => t.at === 'finish').name)).toBe('save')
  })
})
