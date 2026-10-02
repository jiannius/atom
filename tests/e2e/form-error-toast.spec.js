import { test, expect } from '@playwright/test'
import { targets } from '../../resources/js/helpers/form-errors.js'

// <atom:form> shows a sticky error toast when a submit comes back with validation errors.
// The page is tests/Fixtures/FormErrorToastFixture.php: a "main" form (name, nickname,
// email; success raises the app's own "Saved" toast), more forms on the same component
// ("other", "twin" which shares other's method, "plain" which validates nothing, "gone"
// which a successful save removes, "many" with seven failing fields) and a modal form. Query flags flip the main form's props: ?error-toast=0,
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

test.describe('the error toast', () => {
  test('a failed submit shows a danger toast that lists each message once', async ({ page }) => {
    await open(page)
    await submit(page)

    await expect(toast(page)).toBeVisible()
    await expect(toast(page)).toContainText('Please check the form')

    // name and nickname fail with the same sentence: it appears once, not twice
    const items = toast(page).locator('li')
    await expect(items).toHaveText(['Name is required.', 'Email is required.'])

    // danger variant: the red error glyph shows, the success one does not
    await expect(toast(page).locator('span.text-red-500')).toBeVisible()
    await expect(toast(page).locator('span.text-green-500')).toBeHidden()
  })

  test('outside a modal it stays up past the 6s a modal one gets, until the X is clicked', async ({ page }) => {
    await open(page)
    await submit(page)
    await expect(toast(page)).toBeVisible()

    await page.waitForTimeout(7000)
    await expect(toast(page)).toBeVisible()

    await toast(page).getByRole('button', { name: 'Close' }).click()
    await expect(toast(page)).toBeHidden()
  })

  test('it does not scroll or move focus', async ({ page }) => {
    await open(page)
    const before = await page.evaluate(() => window.scrollY)
    await submit(page)
    await expect(toast(page)).toBeVisible()

    // the toast is the whole response: nothing is scrolled to, and no field is focused
    const after = await page.evaluate(() => ({ y: window.scrollY, onField: !!document.activeElement?.matches('input, textarea, select') }))
    expect(after.y).toBe(before)
    expect(after.onField).toBe(false)
  })

  test('a successful submit shows only the app\'s own toast', async ({ page }) => {
    await open(page)
    await page.locator('input[data-probe="name"]').fill('Acme')
    await page.locator('input[data-probe="nickname"]').fill('Ac')
    await page.locator('input[data-probe="email"]').fill('a@b.co')
    await submit(page)

    await expect(page.locator('[data-saves]')).toHaveText('1')
    await expect(toast(page)).toBeVisible()
    await expect(toast(page)).toContainText('Saved')
    await expect(toast(page)).not.toContainText('Please check the form')
  })

  test(':error-toast="false" shows nothing', async ({ page }) => {
    await open(page, '?error-toast=0')
    await submit(page)

    // the errors still reach the fields, so the round trip did finish
    await expect(page.locator('[data-atom-error]').first()).toBeVisible()
    await expect(toast(page)).toBeHidden()
  })

  test('a disabled form never opts in', async ({ page }) => {
    await open(page, '?disabled=1')

    await expect(page.locator('form[data-form="main"]')).not.toHaveAttribute('data-atom-error-toast', /.*/)
    await expect(page.locator('form[data-form="main"]')).not.toHaveAttribute('data-atom-error-heading', /.*/)
  })

  test('a page without <atom:toast> is a no-op: no throw, errors still on the fields', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))

    await open(page, '?no-toast=1')
    await submit(page)

    await expect(page.locator('[data-atom-error]').first()).toBeVisible()
    await expect(page.locator('[data-atom-toast]')).toHaveCount(0)
    expect(errors).toEqual([])
  })

  test('a reCAPTCHA form (which calls $wire.save() itself) still toasts', async ({ page }) => {
    await open(page, '?recaptcha=1')
    await submit(page)

    await expect(toast(page)).toBeVisible()
    await expect(toast(page).locator('li').first()).toHaveText('Name is required.')
  })

  test('only a form submit reacts: another action finishing leaves a dismissed toast down', async ({ page }) => {
    await open(page)
    await submit(page)
    await expect(toast(page)).toBeVisible()
    await toast(page).getByRole('button', { name: 'Close' }).click()
    await expect(toast(page)).toBeHidden()

    // the errors are still on the component; a non-submit action must not bring the toast back
    const responded = page.waitForResponse((r) => r.url().includes('livewire'))
    await page.locator('[data-touch]').click()
    await responded
    await page.waitForTimeout(300)
    await expect(toast(page)).toBeHidden()
  })

  test('the second form on the component reacts for its own submit, with its own message', async ({ page }) => {
    await open(page)
    await submit(page, 'other')

    await expect(toast(page)).toBeVisible()
    await expect(toast(page).locator('li')).toHaveText(['Other is required.'])
  })

  test('a network failure is not a failed submit: no toast from stale errors', async ({ page }) => {
    await open(page)
    await submit(page)
    await expect(toast(page)).toBeVisible()
    await toast(page).getByRole('button', { name: 'Close' }).click()
    await expect(toast(page)).toBeHidden()

    // errors from the first submit are still on the component's snapshot
    await page.route('**/livewire*/update', (route) => route.abort())
    await submit(page)
    await page.waitForTimeout(800)
    await expect(toast(page)).toBeHidden()
  })

  test('fixing the form and resubmitting closes the error toast', async ({ page }) => {
    await open(page)
    await submit(page, 'other')
    await expect(toast(page)).toBeVisible()
    await expect(toast(page)).toContainText('Other is required.')

    await page.locator('input[data-probe="other"]').fill('ok')
    await submit(page, 'other')

    await expect(toast(page)).toBeHidden()
  })

  test('closing on success leaves the app\'s own "Saved" toast alone', async ({ page }) => {
    await open(page)
    await submit(page)
    await expect(toast(page)).toContainText('Please check the form')

    await page.locator('input[data-probe="name"]').fill('Acme')
    await page.locator('input[data-probe="nickname"]').fill('Ac')
    await page.locator('input[data-probe="email"]').fill('a@b.co')
    await submit(page)

    // "Saved" replaced the error toast and must survive the form's own close request
    await expect(page.locator('[data-saves]')).toHaveText('1')
    await expect(toast(page)).toContainText('Saved')
    await page.waitForTimeout(600)
    await expect(toast(page)).toBeVisible()
    await expect(toast(page)).toContainText('Saved')
    await expect(toast(page)).not.toContainText('Please check the form')
  })

  // The flow above can't tell the source check from luck: the app's toast is shown on the
  // next tick, which can land after the close request, so a close with no check would also
  // leave it up. Dispatch the events by hand, in the order that matters.
  test('atom-toast-close with a source closes only a toast carrying that source', async ({ page }) => {
    await open(page)

    await page.evaluate(() => window.atom.toast({ message: 'Saved', delay: 0 }))
    await expect(toast(page)).toBeVisible()
    await page.evaluate(() => dispatchEvent(new CustomEvent('atom-toast-close', { detail: { source: 'atom-form-error' } })))
    await page.waitForTimeout(300)
    await expect(toast(page)).toBeVisible()

    await page.evaluate(() => window.atom.toast({ message: 'Oops', delay: 0, source: 'atom-form-error' }))
    await expect(toast(page)).toContainText('Oops')
    await page.evaluate(() => dispatchEvent(new CustomEvent('atom-toast-close', { detail: { source: 'atom-form-error' } })))
    await expect(toast(page)).toBeHidden()

    // no source: closes whatever is open
    await page.evaluate(() => window.atom.toast({ message: 'Saved', delay: 0 }))
    await expect(toast(page)).toBeVisible()
    await page.evaluate(() => dispatchEvent(new CustomEvent('atom-toast-close')))
    await expect(toast(page)).toBeHidden()
  })

  test('another form\'s clean submit does not close this form\'s error toast', async ({ page }) => {
    await open(page)
    await submit(page)
    await expect(toast(page)).toContainText('Name is required.')

    await page.locator('input[data-probe="other"]').fill('ok')
    const responded = page.waitForResponse((r) => r.url().includes('livewire'))
    await submit(page, 'other')
    await responded
    await page.waitForTimeout(400)

    await expect(toast(page)).toBeVisible()
    await expect(toast(page)).toContainText('Name is required.')
  })

  test('a clean submit closes the error toast over a modal too', async ({ page }) => {
    await open(page)
    await page.evaluate(() => window.atom.modal('fixture-modal').show())
    const dialog = page.locator('dialog[open]')
    await dialog.getByRole('button', { name: 'Save' }).click()
    await expect(toast(page)).toContainText('Modal field is required.')

    await dialog.locator('input[data-probe="modal-field"]').fill('ok')
    await dialog.getByRole('button', { name: 'Save' }).click()

    await expect(toast(page)).toBeHidden()
  })

  // The toast is a popover opened after the dialog, so it paints in the top layer above it.
  // But a modal dialog makes the rest of the page inert: the toast's X can't be clicked,
  // Escape doesn't reach a popover=manual, so a sticky toast would sit there for good.
  // Inside a dialog the toast is timed and also closes with the dialog.
  test('a form inside a modal toasts over the dialog', async ({ page }) => {
    await open(page)
    await page.evaluate(() => window.atom.modal('fixture-modal').show())

    const dialog = page.locator('dialog[open]')
    await expect(dialog).toBeVisible()
    await dialog.getByRole('button', { name: 'Save' }).click()

    await expect(toast(page)).toBeVisible()
    await expect(toast(page).locator('li')).toHaveText(['Modal field is required.'])

    await expect(toast(page)).toHaveJSProperty('popover', 'manual')
    expect(await toast(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
  })

  test('a form inside a modal gets a timed toast, which closes by itself', async ({ page }) => {
    test.setTimeout(30000)
    await open(page)
    await page.evaluate(() => window.atom.modal('fixture-modal').show())
    await page.locator('dialog[open]').getByRole('button', { name: 'Save' }).click()

    await expect(toast(page)).toBeVisible()
    await page.waitForTimeout(4000)
    await expect(toast(page)).toBeVisible()
    await expect(toast(page)).toBeHidden({ timeout: 5000 })
    await expect(page.locator('dialog[open]')).toBeVisible()
  })

  const closers = {
    'Escape': (page) => page.keyboard.press('Escape'),
    'the dialog\'s close button': (page) => page.locator('dialog[open]').getByRole('button', { name: 'Close' }).click(),
    'an atom-modal-close dispatch': (page) => page.evaluate(() => window.atom.modal('fixture-modal').close()),
  }

  for (const [how, close] of Object.entries(closers)) {
    test(`closing the modal with ${how} closes its error toast`, async ({ page }) => {
      await open(page)
      await page.evaluate(() => window.atom.modal('fixture-modal').show())
      await page.locator('dialog[open]').getByRole('button', { name: 'Save' }).click()
      await expect(toast(page)).toBeVisible()

      await close(page)

      await expect(page.locator('dialog[open]')).toHaveCount(0)
      await expect(toast(page)).toBeHidden({ timeout: 2000 })
    })
  }

  test('closing the modal leaves a toast that is not the form\'s own', async ({ page }) => {
    await open(page)
    await page.evaluate(() => window.atom.modal('fixture-modal').show())
    await page.locator('dialog[open]').getByRole('button', { name: 'Save' }).click()
    await expect(toast(page)).toContainText('Modal field is required.')

    await page.evaluate(() => window.atom.toast({ message: 'Elsewhere', delay: 0 }))
    await expect(toast(page)).toContainText('Elsewhere')
    await page.evaluate(() => window.atom.modal('fixture-modal').close())

    await page.waitForTimeout(400)
    await expect(toast(page)).toBeVisible()
    await expect(toast(page)).toContainText('Elsewhere')
  })
})

test.describe('whose errors, and when', () => {
  test('a form that is gone after a successful save takes its error toast with it', async ({ page }) => {
    await open(page)
    await submit(page, 'gone')
    await expect(toast(page)).toContainText('Gone field is required.')

    await page.locator('input[data-probe="gone-field"]').fill('ok')
    await submit(page, 'gone')

    await expect(page.locator('form[data-form="gone"]')).toHaveCount(0)
    await expect(toast(page)).toBeHidden()
  })

  test('a submit that validates nothing does not re-toast another form\'s errors', async ({ page }) => {
    await open(page)
    await submit(page)
    await expect(toast(page)).toContainText('Name is required.')
    await toast(page).getByRole('button', { name: 'Close' }).click()
    await expect(toast(page)).toBeHidden()

    // the main form's errors are still on the component
    await expect(page.locator('[data-atom-error]').first()).toBeVisible()
    const responded = page.waitForResponse((r) => r.url().includes('livewire'))
    await submit(page, 'plain')
    await responded
    await page.waitForTimeout(400)

    await expect(toast(page)).toBeHidden()
  })

  test('a call that names no origin and matches two forms toasts nothing rather than guess', async ({ page }) => {
    await open(page)
    const responded = page.waitForResponse((r) => r.url().includes('livewire'))
    await page.evaluate(() => {
      const el = document.querySelector('form[data-atom-form]').closest('[wire\\:id]')
      window.Livewire.find(el.getAttribute('wire:id')).saveOther()
    })
    await responded

    await expect(page.locator('form[data-form="other"] [data-atom-error]')).toBeVisible()
    await page.waitForTimeout(400)
    await expect(toast(page)).toBeHidden()
  })

  test('with no origin, the candidate form that last had focus is the one that toasts', async ({ page }) => {
    await open(page)
    await page.locator('input[data-probe="other"]').focus()
    await page.evaluate(() => {
      const el = document.querySelector('form[data-atom-form]').closest('[wire\\:id]')
      window.Livewire.find(el.getAttribute('wire:id')).saveOther()
    })

    await expect(toast(page).locator('li')).toHaveText(['Other is required.'])
  })

  test('a long list is cut at five messages and a count of the rest', async ({ page }) => {
    await open(page)
    await submit(page, 'many')

    await expect(toast(page).locator('li')).toHaveText([
      'Row a is required.', 'Row b is required.', 'Row c is required.', 'Row d is required.', 'Row e is required.',
      'and 2 more',
    ])
  })
})

test.describe('errors no field of the form renders', () => {
  // addError('general') belongs to no field of the form: it is the host's to report, so the
  // toast lists only the form's own field errors.
  test('a field-less error is not listed beside the form\'s own', async ({ page }) => {
    await open(page)
    await submit(page, 'orphan')

    await expect(toast(page).locator('li')).toHaveText(['Orphan field is required.'])
    await expect(toast(page)).not.toContainText('Something general went wrong.')
  })
})

test.describe('every atom control is found by the matcher', () => {
  // The matcher knows a field by its wire:model* or name attribute. A control that moved its
  // model onto something else, or wrote a name the matcher can't read, would silently drop
  // its errors from the toast. Fail every control on one form and read the error keys that
  // went into the toast (the lines are cut at five, the keys are not).
  const CONTROL_KEYS = [
    'selectListbox', 'selectNative', 'selectMultiple', 'dateSingle', 'dateRange', 'timePick',
    'tiptapEager', 'tiptapLazy', 'editorField', 'upload', 'agree', 'channels', 'plan', 'phone',
    'otp', 'mail', 'color', 'textField', 'toggled', 'volume', 'stars', 'notes',
  ]

  test('a toast for the controls form carries the key of every one', async ({ page }) => {
    await open(page)
    await page.evaluate(() => {
      window.__shown = []
      addEventListener('atom-toast-show', (e) => window.__shown.push(e.detail))
    })

    await submit(page, 'controls')
    await expect(toast(page)).toBeVisible()

    const shown = await page.evaluate(() => window.__shown)
    expect(shown).toHaveLength(1)
    expect([...shown[0].meta.keys].sort()).toEqual([...CONTROL_KEYS].sort())
    // five lines and the count of the rest
    await expect(toast(page).locator('li')).toHaveCount(6)
    await expect(toast(page).locator('li').last()).toHaveText(`and ${CONTROL_KEYS.length - 5} more`)
  })
})

test.describe('the toast as a live region', () => {
  test('it is a polite status region, whatever it shows', async ({ page }) => {
    await open(page)

    await expect(toast(page)).toHaveAttribute('role', 'status')
    await expect(toast(page)).toHaveAttribute('aria-live', 'polite')
  })
})

test.describe('wire:target', () => {
  // split on the commas between targets, not those inside an argument list or a string
  test('is read as a list of action names', () => {
    const form = (value) => ({ getAttribute: () => value })

    expect(targets(form('save'))).toEqual(['save'])
    expect(targets(form('save, other'))).toEqual(['save', 'other'])
    expect(targets(form('save(1, 2), other'))).toEqual(['save', 'other'])
    expect(targets(form(`save('a, saveOther()'), other(1, [2, 3])`))).toEqual(['save', 'other'])
    expect(targets(form(''))).toEqual([])
  })
})
