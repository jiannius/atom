import { test, expect } from '@playwright/test'
import { caseOf, openCase, observe, flip } from './disabled-helpers.js'

// The date pickers and the range block their value by more than the dropdown that refuses to open: the contract's second rule says blocking never depends on
// the browser holding an event back, so each handler that writes is guarded by the marker the server printed, read at event time. The rig's gestures cannot
// reach a handler the browser already held back (a disabled input takes no typing, a closed panel has no calendar), so this spec drives them: the factory's
// own methods, and a calendar that was open when the marker arrived. It runs enabled first (a call that changes nothing enabled proves nothing) and then
// disabled, on both routes. Run `node tests/consumer/build.mjs` first.

const rootOf = (page, def) => page.locator(`[data-case="${def.id}"] [x-data]`).first()
const call = (page, def, method, ...args) => rootOf(page, def).evaluate((el, [method, args]) => window.Alpine.$data(el)[method](...args), [method, args])
// The two ends a range holds inside: a disabled range keeps the ones it was given, whatever the model says (a guard in the handler that sets them is
// what stops the chain, and the model-level guard behind it would hide a handler that stopped guarding)
const ends = (page, def) => rootOf(page, def).evaluate((el) => { const data = window.Alpine.$data(el); return [data.startValue, data.endValue] })
const shown = (page, def) => page.locator(`[data-case="${def.id}"] input[type="text"]`).first().inputValue()

for (const route of ['prop', 'form']) {
  test(`${route}: date_picker: typing, the clear x and setTypedValue() write nothing when disabled`, async ({ page }) => {
    const def = caseOf('date_picker')
    const type = () => page.locator(`[data-case="${def.id}"] input[type="text"]`).first().evaluate((el) => {
      el.removeAttribute('readonly')
      el.removeAttribute('disabled')
      el.value = '05 Apr 2026'
      return window.Alpine.$data(el.closest('[x-data]')).commitTyped(el)
    })
    const gestures = {
      'commitTyped() of a typed date': type,
      'clear()': () => call(page, def, 'clear'),
      'setTypedValue()': () => call(page, def, 'setTypedValue', '2026-06-07'),
    }

    for (const [name, gesture] of Object.entries(gestures)) {
      await openCase(page, def, { route })
      const before = (await observe(page, def)).value
      await gesture()
      await page.waitForTimeout(250)
      expect((await observe(page, def)).value, `${name}: changes the value, enabled`).not.toBe(before)

      await openCase(page, def, { route, off: true })
      const initial = (await observe(page, def)).value
      const text = await shown(page, def)
      await gesture()
      await page.waitForTimeout(250)
      expect((await observe(page, def)).value, `${name}: changed the value of a disabled picker`).toBe(initial)
      expect(await shown(page, def), `${name}: changed what a disabled picker shows`).toBe(text)
      expect(page.errors).toEqual([])
    }
  })

  test(`${route}: date_picker: a day picked in a calendar that was open when the marker arrived writes nothing`, async ({ page }) => {
    const def = caseOf('date_picker')
    // the panel opens enabled; the marker is then written the way a morph writes it, and the day is clicked
    await openCase(page, def, { route })
    const before = (await observe(page, def)).value
    await page.locator(`[data-case="${def.id}"] input[type="text"]`).first().click()
    await page.locator(`[data-case="${def.id}"] .pika-table td:not(.is-disabled):not(.is-selected) button`).first().waitFor()
    await page.locator(`[data-case="${def.id}"] [data-atom-date-picker]`).evaluate((el) => el.setAttribute('data-atom-disabled', 'self'))
    await page.locator(`[data-case="${def.id}"] .pika-table td:not(.is-disabled):not(.is-selected) button`).first().click()
    await page.waitForTimeout(250)

    expect((await observe(page, def)).value).toBe(before)
    expect(page.errors).toEqual([])
  })

  test(`${route}: date_picker: setCalendar() builds no calendar when disabled`, async ({ page }) => {
    const def = caseOf('date_picker')
    const calendars = () => page.locator(`[data-case="${def.id}"] .pika-single`).count()

    await openCase(page, def, { route })
    await call(page, def, 'setCalendar')
    expect(await calendars(), 'enabled: the calendar is built').toBe(1)

    await openCase(page, def, { route, off: true })
    await call(page, def, 'setCalendar')
    expect(await calendars(), 'disabled: no calendar to pick from').toBe(0)
    expect(page.errors).toEqual([])
  })

  test(`${route}: date_range: typing, the clear x, a preset and the filter bar's clear write nothing when disabled`, async ({ page }) => {
    const def = caseOf('date_range')
    const type = () => page.locator(`[data-case="${def.id}"] input[type="text"]`).first().evaluate((el) => {
      el.removeAttribute('readonly')
      el.removeAttribute('disabled')
      el.value = '05 Apr 2026 to 09 Apr 2026'
      return window.Alpine.$data(el.closest('[x-data]')).commitTyped(el)
    })
    const gestures = {
      'commitTyped() of a typed range': type,
      'clear()': () => call(page, def, 'clear'),
      'preset()': () => call(page, def, 'preset', 'last-7-days'),
      'updateValue() from two new ends': () => rootOf(page, def).evaluate((el) => {
        const data = window.Alpine.$data(el)
        data.startValue = '2026-06-01T00:00:00.000Z'
        data.endValue = '2026-06-02T00:00:00.000Z'
        data.updateValue()
      }),
    }

    for (const [name, gesture] of Object.entries(gestures)) {
      await openCase(page, def, { route })
      const before = (await observe(page, def)).value
      await gesture()
      await page.waitForTimeout(250)
      expect((await observe(page, def)).value, `${name}: changes the value, enabled`).not.toBe(before)

      await openCase(page, def, { route, off: true })
      const initial = (await observe(page, def)).value
      const text = await shown(page, def)
      const held = await ends(page, def)
      await gesture()
      await page.waitForTimeout(250)
      // the gesture that sets the ends itself is the one the model guard is written for
      if (name !== 'updateValue() from two new ends') expect(await ends(page, def), `${name}: changed the ends a disabled range holds`).toEqual(held)
      expect((await observe(page, def)).value, `${name}: changed the value of a disabled range`).toBe(initial)
      expect(await shown(page, def), `${name}: changed what a disabled range shows`).toBe(text)
      expect(page.errors).toEqual([])
    }
  })

  test(`${route}: date_range: a preset and a day of either calendar clicked in a panel that was open when the marker arrived write nothing`, async ({ page }) => {
    const def = caseOf('date_range')
    const day = '.pika-table td:not(.is-disabled):not(.is-selected) button'
    const targets = {
      'a preset': (scope) => scope.locator('text=Last 7 Days').first(),
      'a day of the start calendar': (scope) => scope.locator('[data-atom-date-picker-calendar]').nth(0).locator(day).first(),
      'a day of the end calendar': (scope) => scope.locator('[data-atom-date-picker-calendar]').nth(1).locator(day).first(),
    }

    for (const [name, find] of Object.entries(targets)) {
      await openCase(page, def, { route })
      const scope = page.locator(`[data-case="${def.id}"]`)
      const before = (await observe(page, def)).value
      const held = await ends(page, def)
      await scope.locator('input[type="text"]').first().click()
      await find(scope).waitFor()
      await scope.locator('[data-atom-date-range]').evaluate((el) => el.setAttribute('data-atom-disabled', 'self'))
      await find(scope).click()
      await page.waitForTimeout(250)

      expect((await observe(page, def)).value, name).toBe(before)
      expect(await ends(page, def), `${name}: changed the ends a disabled range holds`).toEqual(held)
      expect(page.errors).toEqual([])
    }
  })

  test(`${route}: date_range_add: the own chip's clear and the pill write nothing when disabled`, async ({ page }) => {
    const def = caseOf('date_range_add')
    await openCase(page, def, { route, value: '2026-03-01 to 2026-03-09' })
    const before = (await observe(page, def)).value
    await call(page, def, 'clearChip')
    await expect.poll(async () => (await observe(page, def)).value, 'enabled: the chip is cleared').not.toBe(before)

    await openCase(page, def, { route, off: true, value: '2026-03-01 to 2026-03-09' })
    const initial = (await observe(page, def)).value
    await call(page, def, 'clearChip')
    await page.waitForTimeout(250)
    expect((await observe(page, def)).value).toBe(initial)
    await expect(page.locator(`[data-case="${def.id}"] [data-atom-date-range-add]`)).toBeDisabled()
  })
}

// A text input in the picker is selectable and takes a click in a disabled form, as the plan's table says of every inherited text field; its own `disabled` takes neither.
for (const id of ['date_picker', 'date_range']) {
  test(`${id}: in a disabled form the value is selectable and a click focuses the input; with its own disabled it is neither`, async ({ page }) => {
    const def = caseOf(id)
    const input = page.locator(`[data-case="${def.id}"] input[type="text"]`).first()

    await openCase(page, def, { route: 'form', off: true })
    await expect(input).toHaveJSProperty('readOnly', true)
    await input.dblclick()
    await expect(input).toBeFocused()
    expect(await input.evaluate((el) => el.selectionEnd - el.selectionStart), 'a double click selects text').toBeGreaterThan(0)
    expect((await observe(page, def)).popups, 'and opens no panel').toBe(0)

    await openCase(page, def, { route: 'prop', off: true })
    await expect(input).toBeDisabled()
  })
}

// The focused inherited field draws the focus border, as the enabled one does: the recipe's plain border must sort below the focus variant on both builds.
for (const tw of ['floor', 'latest']) {
  for (const id of ['date_picker', 'date_range']) {
    test(`${id} on the ${tw} build: a focused field in a disabled form draws the focus border`, async ({ page }) => {
      const def = caseOf(id)
      const input = page.locator(`[data-case="${def.id}"] input[type="text"]`).first()
      const border = () => input.evaluate((el) => getComputedStyle(el).borderTopColor)

      await openCase(page, def, { route: 'form', tw })
      await input.focus()
      await page.keyboard.press('Shift+Tab')
      await page.keyboard.press('Tab')
      await expect(input).toBeFocused()
      const focusedEnabled = await border()

      await openCase(page, def, { route: 'form', off: true, tw })
      await input.focus()
      await page.keyboard.press('Shift+Tab')
      await page.keyboard.press('Tab')
      await expect(input).toBeFocused()
      expect(await border(), 'the border of a focused inherited field').toBe(focusedEnabled)
    })
  }
}

// The nested time-picker of a panel carries no state of its own, so it must follow the picker around it: it writes nothing while the picker is disabled, and
// is editable again after the picker was disabled and enabled by a morph (a panel is wire:ignore'd, so a state baked in at the first render would stick).
for (const route of ['prop', 'form']) {
  test(`${route}: date_picker_time: the nested time-picker writes nothing while disabled and is editable after a flip back`, async ({ page }) => {
    const def = caseOf('date_picker_time')
    await openCase(page, def, { route, off: true })
    const initial = (await observe(page, def)).value
    // a hand-called edit of the nested picker: it must refuse
    await page.locator(`[data-case="${def.id}"] [data-atom-menu] [x-data]`).first().evaluate((el) => window.Alpine.$data(el).up('hr', '3'))
    await page.waitForTimeout(250)
    expect((await observe(page, def)).value, 'a disabled picker\'s time was changed through its panel').toBe(initial)

    await flip(page)
    await page.locator(`[data-case="${def.id}"] input[type="text"]`).first().click()
    await expect(page.locator(`[data-case="${def.id}"] .pika-table`)).toBeVisible()
    const before = (await observe(page, def)).value
    await page.locator(`[data-case="${def.id}"] [data-atom-menu] [x-data]`).first().evaluate((el) => window.Alpine.$data(el).up('hr', '3'))
    await expect.poll(async () => (await observe(page, def)).value, 'after the way back the nested picker writes').not.toBe(before)
    expect(page.errors).toEqual([])
  })
}

// The autosize plugin sets `wire:ignore` on a `wire:model` textarea, so a morph never reaches it: helpers/morphed.js carries its state. It must flip both
// ways, keep the height the text made, and block typing in between.
for (const route of ['prop', 'form']) {
  test(`${route}: textarea_autoresize: flips both ways through a morph and keeps its height`, async ({ page }) => {
    const def = caseOf('textarea_autoresize')
    const area = page.locator(`[data-case="${def.id}"] textarea`)
    const height = () => area.evaluate((el) => el.offsetHeight)
    const long = Array.from({ length: 9 }, (_, i) => `line ${i}`).join('\n')

    await openCase(page, def, { route, value: long })
    await expect(area).toBeEditable()
    const tall = await height()
    expect(tall, 'the text made it taller than two rows').toBeGreaterThan(100)

    await flip(page)
    await expect(area).not.toBeEditable()
    expect(await height(), 'disabled: the height stays').toBe(tall)
    await area.click({ force: true })
    await page.keyboard.type('zq7')
    expect(await area.inputValue(), 'disabled: nothing typed').toBe(long)

    await flip(page)
    await expect(area).toBeEditable()
    expect(await height(), 'enabled again: the height stays').toBe(tall)
    await area.click()
    await page.keyboard.type('zq7')
    expect(await area.inputValue(), 'enabled again: typing works').toContain('zq7')
    expect(page.errors).toEqual([])
  })
}

// What the carry hook leaves alone: a class that JS or a host put on the wire:ignore'd textarea survives a flip both ways (only the marker, `disabled` and
// `readonly` are server-owned on such a root).
for (const route of ['prop', 'form']) {
  test(`${route}: textarea_autoresize: a class added by JS survives the morph in both directions`, async ({ page }) => {
    const def = caseOf('textarea_autoresize')
    const area = page.locator(`[data-case="${def.id}"] textarea`)

    await openCase(page, def, { route })
    await area.evaluate((el) => el.classList.add('host-added-class'))

    await flip(page)
    await expect(area).not.toBeEditable()
    await expect(area).toHaveClass(/host-added-class/)

    await flip(page)
    await expect(area).toBeEditable()
    await expect(area).toHaveClass(/host-added-class/)
    expect(page.errors).toEqual([])
  })
}
