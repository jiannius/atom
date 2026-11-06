import { test, expect } from '@playwright/test'

// Issue #165: <atom:copy> copied "null" (its click handler is an arrow function, so `this.value` was never Alpine's
// `value`), `$clipboard` resolved even when the browser refused the write (so the copy button showed its success tick),
// and <atom:confirm> accepted any text for a passphrase. navigator.clipboard is stubbed: what was written is recorded in
// window.__copied, and `mode` decides whether the write resolves, rejects, or whether there is no clipboard at all.
const stubClipboard = (page, mode = 'resolve') => page.addInitScript((mode) => {
  window.__copied = []
  const clipboard = mode === 'missing' ? undefined : {
    writeText: (text) => {
      window.__copied.push(text)
      return mode === 'reject' ? Promise.reject(new DOMException('Write permission denied.', 'NotAllowedError')) : Promise.resolve()
    },
  }
  Object.defineProperty(Navigator.prototype, 'clipboard', { configurable: true, get: () => clipboard })
}, mode)

const open = async (page, mode) => {
  await stubClipboard(page, mode)
  await page.goto('/atom/e2e/copy-confirm')
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

  test('ignores whitespace around the typed text', async ({ page }) => {
    await open(page)
    await ask(page, { passphrase: 'DELETE' })

    await passphrase(page).fill('  DELETE ')
    await passphrase(page).press('Enter')

    await expect.poll(() => answer(page)).not.toBeNull()
    expect((await answer(page)).accepted).toBeTruthy()
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

  test('a confirm with no passphrase still accepts at once', async ({ page }) => {
    await open(page)
    await ask(page, { heading: 'Sure?' })
    await expect(dialog(page)).toHaveAttribute('data-open', '')

    await dialog(page).getByRole('button', { name: 'Confirm' }).click()

    await expect.poll(() => answer(page)).toEqual({ accepted: { password: '', passphrase: '', reason: '' } })
  })
})
