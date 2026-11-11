import { test, expect } from '@playwright/test'
import { caseOf, openCase, observe, flip } from './disabled-helpers.js'

// The tel and email inputs under the disabled-state contract (issues #186 and #190). The rig's rows drive the mouse and the keyboard; this spec calls the
// listeners of the nodes the rig cannot reach (hidden by CSS, as a click on a child of a disabled button reaches its own listener) and proves the guards hold the
// value without any utility class, measures the hidden nodes on both Tailwind builds, and holds the tel model to what a user wrote. Run
// `node tests/consumer/build.mjs` first, as for the rest of the rig.

const phone = (page) => page.locator('[data-case="input_tel"] input[type=tel]')
const dial = (page) => page.locator('[data-case="input_tel"] select')
const model = (page) => page.evaluate(() => window.Livewire.first().get('v'))

for (const route of ['prop', 'form']) {
  test(`${route}: the dial code select is a real disabled select and the number box follows the text-field table`, async ({ page }) => {
    const def = caseOf('input_tel')

    await openCase(page, def, { route })
    await expect(dial(page)).toBeEnabled()
    await expect(phone(page)).toBeEnabled()
    await expect(phone(page)).not.toHaveJSProperty('readOnly', true)

    await openCase(page, def, { route, off: true })
    await expect(dial(page)).toBeDisabled()
    if (route === 'prop') await expect(phone(page)).toBeDisabled()
    else {
      await expect(phone(page)).toBeEnabled()
      await expect(phone(page)).toHaveJSProperty('readOnly', true)
    }
  })

  test(`${route}: input_tel: changing the dial code of a disabled control writes nothing`, async ({ page }) => {
    const def = caseOf('input_tel')

    await openCase(page, def, { route, off: true })
    // a script (or a browser extension) can set a disabled select and fire its events: the handler refuses
    await dial(page).evaluate((el) => { el.value = '+65'; el.dispatchEvent(new Event('change', { bubbles: true })) })
    await page.waitForTimeout(250)
    expect(await model(page)).toBe('+60123456789')
    expect(page.updates).toEqual([])
  })

  // A pick from a native list a morph disabled while it was open still changes the select and fires input and change (WebKit's list outlives the disable
  // and its callback has no disabled check; #204). The dial code shown went with it, and the next digit typed once the control was enabled wrote it into the model.
  for (const event of ['change', 'input']) {
    test(`${route}: input_tel: ${event === 'input' ? 'an' : 'a'} ${event} that reaches a disabled dial code select puts the code back, so no digit writes it later`, async ({ page }) => {
      const def = caseOf('input_tel')
      const shownCode = () => page.evaluate(() => window.Alpine.$data(document.querySelector('[data-case="input_tel"] [data-atom-input-tel]')).code)

      await openCase(page, def, { route, off: true })
      await expect(dial(page)).toHaveValue('+60')
      await dial(page).evaluate((el, type) => { el.value = '+65'; el.dispatchEvent(new Event(type, { bubbles: true })) }, event)
      await page.waitForTimeout(250)
      await expect(dial(page)).toHaveValue('+60')
      expect(await shownCode()).toBe('+60')
      expect(await model(page)).toBe('+60123456789')
      expect(page.updates).toEqual([])

      await flip(page)
      await expect(dial(page)).toBeEnabled()
      await expect(dial(page)).toHaveValue('+60')
      await phone(page).click()
      await page.keyboard.press('End')
      await page.keyboard.type('9', { delay: 20 })
      await expect.poll(() => model(page)).toBe('+601234567899')
    })
  }
}

// #190: a push reached the number box only when the page loaded with it
test('input_tel: a server push shows in both boxes, enabled', async ({ page }) => {
  const def = caseOf('input_tel')

  await openCase(page, def)
  await expect(phone(page)).toHaveValue('123456789')
  await expect(dial(page)).toHaveValue('+60')

  await page.evaluate(() => window.Livewire.first().push('+6598765432'))
  await expect(phone(page)).toHaveValue('98765432')
  await expect(dial(page)).toHaveValue('+65')
  expect(page.updates.length, 'a push is displayed, not sent back').toBe(1)

  await page.evaluate(() => window.Livewire.first().push(null))
  await expect(phone(page)).toHaveValue('')
  await expect(dial(page)).toHaveValue('+60')
})

test('input_tel: the number follows an Alpine parent too, and an edit afterwards still writes', async ({ page }) => {
  const def = caseOf('input_tel')

  await openCase(page, def, { bind: 'x' })
  await page.evaluate(() => { window.Alpine.$data(document.querySelector('[data-xmodel]')).pv = '+6598765432' })
  await expect(phone(page)).toHaveValue('98765432')
  await expect(dial(page)).toHaveValue('+65')

  await phone(page).fill('123')
  expect((await observe(page, def, { bind: 'x' })).value).toBe(JSON.stringify(['+65123', null]))
})

test('input_tel: an edit is written once and not split back over what the user is typing', async ({ page }) => {
  const def = caseOf('input_tel')

  await openCase(page, def)
  await dial(page).selectOption('+65')
  expect(await model(page)).toBe('+65123456789')

  // an empty number clears the model; the dial code the user chose stays
  await phone(page).fill('')
  await expect.poll(() => model(page)).toBe(null)
  await expect(dial(page)).toHaveValue('+65')

  await phone(page).pressSequentially('9123', { delay: 20 })
  await expect.poll(() => model(page)).toBe('+659123')
  await expect(phone(page)).toHaveValue('9123')
})

test('input_tel: a number without a dial code is shown under the default one and left alone until an edit', async ({ page }) => {
  const def = caseOf('input_tel')

  await openCase(page, def, { value: '0123456789' })
  await page.waitForTimeout(800)
  await expect(phone(page)).toHaveValue('0123456789')
  await expect(dial(page)).toHaveValue('+60')
  expect(page.updates).toEqual([])
  expect(await model(page)).toBe('0123456789')

  await phone(page).click()
  await page.keyboard.press('End')
  await page.keyboard.type('4', { delay: 20 })
  await expect.poll(() => model(page)).toBe('+6001234567894')
})

// Email: the chip remove and clear all are Alpine nodes, hidden by a variant and refused by their handlers.
const emailTargets = [
  { id: 'input_email_multiple', what: 'a chip remove', selector: '[data-atom-chip-remove]' },
  { id: 'input_email_multiple', what: 'the clear-all button', selector: 'button[aria-label="Clear all"]' },
  { id: 'input_email_options', what: 'a chip remove', selector: '[data-atom-chip-remove]' },
]

for (const route of ['prop', 'form']) {
  for (const t of emailTargets) {
    test(`${route}: ${t.id}: calling the listener of ${t.what} changes nothing when disabled`, async ({ page }) => {
      const def = caseOf(t.id)

      await openCase(page, def, { route })
      const before = (await observe(page, def)).value
      await page.locator(`[data-case="${def.id}"] ${t.selector}`).first().evaluate((el) => el.click())
      await page.waitForTimeout(150)
      expect((await observe(page, def)).value, `${t.what} changes the value, enabled`).not.toBe(before)

      await openCase(page, def, { route, off: true })
      const initial = (await observe(page, def)).value
      await page.locator(`[data-case="${def.id}"] ${t.selector}`).first().evaluate((el) => el.click())
      await page.waitForTimeout(250)
      expect((await observe(page, def)).value, `${t.what} changed the value of a disabled control`).toBe(initial)
      expect(page.errors).toEqual([])
    })
  }

  test(`${route}: input_email_options: Enter, Space and ; add nothing when disabled`, async ({ page }) => {
    const def = caseOf('input_email_options')

    await openCase(page, def, { route })
    await page.locator('[data-case="input_email_options"] input[type=email]').click()
    await page.keyboard.press('Enter')
    await page.waitForTimeout(150)
    expect(JSON.parse((await observe(page, def)).value)[0], 'Enter adds the option, enabled').toHaveLength(2)

    await openCase(page, def, { route, off: true })
    const initial = (await observe(page, def)).value
    // the keydown listeners sit on the root, so they hear a key from any node in it, whatever the input does
    for (const key of ['Enter', ' ', ';']) {
      await page.locator('[data-case="input_email_options"] [data-atom-input-email]').evaluate((el, key) => {
        el.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
      }, key)
    }
    await page.waitForTimeout(250)
    expect((await observe(page, def)).value).toBe(initial)
  })
}

// Hidden by a variant on a plain wrapper, as the select family does: the computed display decides, on both builds the hosts use.
for (const tw of ['floor', 'latest']) {
  for (const t of [emailTargets[0], emailTargets[1]]) {
    test(`${tw} build: ${t.id}: ${t.what} is drawn enabled and not drawn disabled`, async ({ page }) => {
      const def = caseOf(t.id)
      const drawn = () => page.locator(`[data-case="${def.id}"] ${t.selector}`).first().evaluate((el) => {
        const box = el.getBoundingClientRect()
        return box.width > 0 && box.height > 0
      })

      await openCase(page, def, { route: 'prop', tw })
      expect(await drawn(), 'enabled').toBe(true)

      await openCase(page, def, { route: 'prop', off: true, tw })
      expect(await drawn(), 'disabled').toBe(false)
    })
  }

  test(`${tw} build: input_email_multiple: a chip takes subtle text disabled`, async ({ page }) => {
    const def = caseOf('input_email_multiple')
    const color = () => page.locator(`[data-case="${def.id}"] [data-atom-chip-remove]`).first().evaluate((el) => getComputedStyle(el.closest('[class*="h-control-xs"]')).color)

    await openCase(page, def, { route: 'prop', tw })
    const live = await color()
    await openCase(page, def, { route: 'prop', off: true, tw })
    expect(await color()).not.toBe(live)
  })
}
