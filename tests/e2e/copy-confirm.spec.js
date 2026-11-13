import { test, expect } from '@playwright/test'

// Issue #165: <atom:copy> copied "null" (its click handler is an arrow function, so `this.value` was never Alpine's
// `value`), `$clipboard` resolved even when the browser refused the write (so the copy button showed its success tick),
// and <atom:confirm> accepted any text for a passphrase. navigator.clipboard is stubbed: what was written is recorded in
// window.__copied, and `mode` decides whether the write resolves, rejects, or whether there is no clipboard at all.
// Unhandled rejections are recorded in window.__unhandled.
const stubClipboard = (page, mode = 'resolve') => page.addInitScript((mode) => {
  window.__copied = []
  window.__unhandled = []
  addEventListener('unhandledrejection', (event) => window.__unhandled.push(event.reason?.name ?? String(event.reason)))
  const clipboard = mode === 'missing' ? undefined : {
    writeText: (text) => {
      window.__copied.push(text)
      return mode === 'reject' ? Promise.reject(new DOMException('Write permission denied.', 'NotAllowedError')) : Promise.resolve()
    },
  }
  Object.defineProperty(Navigator.prototype, 'clipboard', { configurable: true, get: () => clipboard })
}, mode)

const open = async (page, mode, query = '') => {
  await stubClipboard(page, mode)
  await page.goto(`/atom/e2e/copy-confirm${query}`)
  await page.waitForFunction(() => window.Alpine && window.atom)
}

const copied = (page) => page.evaluate(() => window.__copied)

// what $clipboard(text) settles to: the magic is taken from an Alpine component on the page and called here
// (Alpine.evaluate() calls a function it gets back, so the magic is wrapped in one, and an evaluated call would hand back
// the resolved value, not the promise)
const clipboardOutcome = (page) => page.evaluate(async () => {
  const clipboard = window.Alpine.evaluate(document.querySelector('[data-testid="copy-value"]'), '() => $clipboard')

  try {
    await clipboard('x')
    return 'resolved'
  } catch (error) {
    return `rejected: ${error?.name}`
  }
})

test.describe('<atom:copy>', () => {
  test('copies its value prop', async ({ page }) => {
    await open(page)
    const copy = page.getByTestId('copy-value')

    await copy.locator('svg').click()

    await expect.poll(() => copied(page)).toEqual(['hello'])
    await expect(copy.locator('svg.lucide-check')).toHaveCount(1)
  })

  test('still copies data-copy-value when it has no value prop', async ({ page }) => {
    await open(page)

    await page.getByTestId('copy-attribute').locator('svg').click()

    await expect.poll(() => copied(page)).toEqual(['from-attribute'])
  })

  test('copies its value prop when the trigger is a slot', async ({ page }) => {
    await open(page)

    await page.getByTestId('copy-slot').getByRole('button', { name: 'Copy link' }).click()

    await expect.poll(() => copied(page)).toEqual(['slotted'])
  })

  test('copies a value of 0', async ({ page }) => {
    await open(page)

    await page.getByTestId('copy-zero').locator('svg').click()

    await expect.poll(() => copied(page)).toEqual(['0'])
  })

  test('a second click while the tick shows still puts the original icon back', async ({ page }) => {
    await open(page)
    const copy = page.getByTestId('copy-value')
    const icon = await copy.innerHTML()

    await copy.locator('svg').click()
    await expect(copy.locator('svg.lucide-check')).toHaveCount(1)
    await copy.locator('svg').click()
    await expect.poll(() => copied(page)).toEqual(['hello', 'hello'])

    // past both restores (700ms after each click): what is left is the original icon, not the tick
    await page.waitForTimeout(1600)
    expect(await copy.innerHTML()).toBe(icon)
  })

  test('shows a failure mark, not the success tick, when the browser refuses the write, then puts the icon back', async ({ page }) => {
    await open(page, 'reject')
    const copy = page.getByTestId('copy-value')
    const icon = await copy.innerHTML()

    await copy.locator('svg').click()

    await expect(copy.locator('[data-atom-copy-failed]')).toHaveCount(1)
    await expect(copy.locator('svg.lucide-check')).toHaveCount(0)
    await expect.poll(() => copy.innerHTML()).toBe(icon)
  })
})

test.describe('<atom:sharer> copy link', () => {
  test('a refused write is not an unhandled rejection', async ({ page }) => {
    await open(page, 'reject')

    await page.getByTestId('sharer').getByRole('button', { name: 'Copy Link' }).click()
    await expect.poll(() => copied(page)).toEqual(['https://example.com/post'])

    // unhandledrejection fires once the microtasks have run; give it a frame or two
    await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 100)))
    expect(await page.evaluate(() => window.__unhandled)).toEqual([])
  })
})

test.describe('$clipboard', () => {
  test('resolves when the write succeeds', async ({ page }) => {
    await open(page)

    expect(await clipboardOutcome(page)).toBe('resolved')
    expect(await copied(page)).toEqual(['x'])
  })

  test('rejects when the browser refuses the write', async ({ page }) => {
    await open(page, 'reject')

    expect(await clipboardOutcome(page)).toBe('rejected: NotAllowedError')
  })

  test('rejects when there is no clipboard (an insecure context)', async ({ page }) => {
    await open(page, 'missing')

    expect(await clipboardOutcome(page)).toBe('rejected: TypeError')
  })
})

test.describe('<atom:confirm> passphrase', () => {
  const dialog = (page) => page.locator('dialog[x-data*="atom-confirm"]')
  const passphrase = (page) => page.locator('#atom-confirm-passphrase')
  const error = (page) => page.locator('#atom-confirm-passphrase-error')

  const ask = (page, config) => page.evaluate((config) => {
    window.__answer = null
    atom.confirm(config)
      .then((answer) => { window.__answer = { accepted: answer } })
      .catch(() => { window.__answer = 'rejected' })
  }, config)

  const answer = (page) => page.evaluate(() => window.__answer)

  test('refuses text that does not match the passphrase, with an inline error, and accepts the passphrase', async ({ page }) => {
    await open(page)
    await ask(page, { heading: 'Delete the domain?', passphrase: 'example.com' })
    await expect(dialog(page)).toHaveAttribute('data-open', '')

    await passphrase(page).fill('x')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()

    await expect(error(page)).toBeVisible()
    await expect(error(page)).toHaveText('The text you typed does not match')
    await expect(passphrase(page)).toHaveAttribute('aria-invalid', 'true')
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    expect(await answer(page)).toBeNull()

    // the case counts
    await passphrase(page).fill('EXAMPLE.COM')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    expect(await answer(page)).toBeNull()

    await passphrase(page).fill('example.com')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()

    await expect.poll(() => answer(page)).toEqual({ accepted: { password: '', passphrase: 'example.com', reason: '' } })
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
  })

  test('ignores whitespace around the typed text, and hands on the trimmed text', async ({ page }) => {
    await open(page)
    await ask(page, { passphrase: 'example.com' })

    await passphrase(page).fill(' example.com ')
    await passphrase(page).press('Enter')

    await expect.poll(() => answer(page)).toEqual({ accepted: { password: '', passphrase: 'example.com', reason: '' } })
  })

  test('<atom:confirm.trigger> dispatches the trimmed passphrase in its confirmed detail', async ({ page }) => {
    await open(page)

    await page.getByRole('button', { name: 'Delete domain' }).click()
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await passphrase(page).fill('  example.com ')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()

    await expect.poll(() => page.evaluate(() => window.__confirmed ?? null)).toEqual({ password: '', passphrase: 'example.com', reason: '' })
  })

  test('opens with empty fields and no error each time it is shown', async ({ page }) => {
    await open(page)

    await ask(page, { passphrase: 'example.com', password: true, reason: true })
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await dialog(page).locator('input[type="password"]').fill('secret')
    await dialog(page).locator('#atom-confirm-reason').fill('Moving away')
    await passphrase(page).fill('wrong')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()
    await expect(error(page)).toBeVisible()
    await dialog(page).getByRole('button', { name: 'Cancel' }).click()
    await expect.poll(() => answer(page)).toBe('rejected')

    await ask(page, { passphrase: 'example.com', password: true, reason: true })
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await expect(passphrase(page)).toHaveValue('')
    await expect(dialog(page).locator('input[type="password"]')).toHaveValue('')
    await expect(dialog(page).locator('#atom-confirm-reason')).toHaveValue('')
    await expect(error(page)).toBeHidden()
  })

  test('the field is not drawn as an error until a wrong passphrase is submitted (consumer Tailwind)', async ({ page }) => {
    await open(page, 'resolve', '?tw=floor')
    const border = () => passphrase(page).evaluate((el) => getComputedStyle(el).borderTopColor)

    await ask(page, { passphrase: 'example.com' })
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    const before = await border()

    await passphrase(page).fill('x')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()
    await expect(error(page)).toBeVisible()
    const danger = await error(page).evaluate((el) => getComputedStyle(el).color)

    expect(await border()).toBe(danger)
    expect(before).not.toBe(danger)

    // typing again takes the error away, and the red border with it
    await passphrase(page).pressSequentially('y')
    await expect(error(page)).toBeHidden()
    expect(await border()).not.toBe(danger)
  })

  test('typing after a wrong submit clears the error and aria-invalid', async ({ page }) => {
    await open(page)
    await ask(page, { passphrase: 'example.com' })

    await passphrase(page).fill('x')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()
    await expect(error(page)).toBeVisible()
    await expect(passphrase(page)).toHaveAttribute('aria-invalid', 'true')

    await passphrase(page).pressSequentially('e')

    await expect(error(page)).toBeHidden()
    await expect(passphrase(page)).not.toHaveAttribute('aria-invalid')
    expect(await answer(page)).toBeNull()
  })

  test('a passphrase with $$ or $& in it is shown as written in the default label, and accepted', async ({ page }) => {
    await open(page)
    await ask(page, { passphrase: 'pa$$word$&' })

    await expect(dialog(page).locator('label[for="atom-confirm-passphrase"]')).toHaveText('Please type "pa$$word$&" to continue')

    await passphrase(page).fill('pa$$word$&')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()

    await expect.poll(() => answer(page)).toEqual({ accepted: { password: '', passphrase: 'pa$$word$&', reason: '' } })
  })

  test('a Livewire onAccepted method gets the trimmed passphrase, and is not called for a wrong one', async ({ page }) => {
    await open(page)
    const received = page.getByTestId('received')

    await page.getByRole('button', { name: 'Ask from PHP' }).click()
    await expect(dialog(page)).toHaveAttribute('data-open', '')

    await passphrase(page).fill('example')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()
    await expect(error(page)).toBeVisible()
    await expect(received).toBeEmpty()

    await passphrase(page).fill(' example.com ')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()

    await expect(received).not.toBeEmpty()
    expect(JSON.parse(await received.textContent())).toEqual({ password: '', passphrase: 'example.com', reason: '' })
  })

  // a menu item's `phrase` (the alias) and `passphrase`, and a delete button's `passphrase`, all ask for the text
  for (const [label, text, typed] of [
    ['Delete with phrase', 'remove-me', 'remove-me'],
    ['Delete with passphrase', "it's ok", "it's ok"],
    ['Delete button', 'btn-pass', 'btn-pass'],
  ]) {
    test(`"${label}" refuses wrong text and accepts the passphrase`, async ({ page }) => {
      await open(page)
      const received = page.getByTestId('received')

      await page.getByRole('menuitem', { name: label }).or(page.getByRole('button', { name: label, exact: true })).first().click()
      await expect(dialog(page)).toHaveAttribute('data-open', '')
      await expect(passphrase(page)).toBeVisible()
      await expect(dialog(page).locator('label[for="atom-confirm-passphrase"]')).toContainText(text)

      // the confirm button is named after the trigger
      await expect(dialog(page).locator('button[type=submit]')).toContainText(label)
      await passphrase(page).fill('nope')
      await dialog(page).locator('button[type=submit]').click()
      await expect(error(page)).toBeVisible()
      await expect(dialog(page)).toHaveAttribute('data-open', '')
      await expect(received).toBeEmpty()

      await passphrase(page).fill(typed)
      await dialog(page).locator('button[type=submit]').click()
      await expect(received).toHaveText('deleted')
    })
  }

  test('`phrase` in an atom.confirm() call is read as the passphrase', async ({ page }) => {
    await open(page)
    await ask(page, { heading: 'Sure?', phrase: 'alias' })
    await expect(passphrase(page)).toBeVisible()

    await passphrase(page).fill('x')
    await dialog(page).getByRole('button', { name: 'Confirm' }).click()
    await expect(error(page)).toBeVisible()
    expect(await answer(page)).toBeNull()
  })

  test('the confirm button is drawn in the dialog\'s tone (consumer Tailwind)', async ({ page }) => {
    await open(page, 'resolve', '?tw=floor')

    const background = async (config) => {
      await ask(page, { heading: 'Sure?', ...config })
      await expect(dialog(page)).toHaveAttribute('data-open', '')
      const color = await dialog(page).locator('button[type=submit]').evaluate((el) => getComputedStyle(el).backgroundColor)
      await dialog(page).getByRole('button', { name: 'Cancel' }).click()
      await expect(dialog(page)).not.toHaveAttribute('data-open', '')
      return color
    }

    const colours = {
      danger: await background({ variant: 'danger' }),
      warning: await background({ variant: 'warning' }),
      success: await background({ variant: 'success' }),
      plain: await background({}),
    }

    expect(new Set(Object.values(colours)).size).toBe(4)
  })

  test('a confirm with no passphrase still accepts at once', async ({ page }) => {
    await open(page)
    await ask(page, { heading: 'Sure?' })
    await expect(dialog(page)).toHaveAttribute('data-open', '')

    await dialog(page).getByRole('button', { name: 'Confirm' }).click()

    await expect.poll(() => answer(page)).toEqual({ accepted: { password: '', passphrase: '', reason: '' } })
  })
})
