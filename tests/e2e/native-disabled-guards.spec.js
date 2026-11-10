import { test, expect } from '@playwright/test'
import { caseOf, openCase, observe } from './disabled-helpers.js'

// The native-backed controls (OTP, time-picker, slider) block their value by more than the native `disabled` or `readonly`: the contract's second rule says
// blocking never depends on a utility class or on the browser holding an event back, so each handler that writes is guarded by the marker the server printed.
// The rig's mouse and keyboard gestures cannot reach a handler the browser already held back, so this spec drives the handlers themselves: the events a
// script can still send to a disabled box, and the factory's own methods, as a click on a child of a disabled button does in every engine.
// It runs enabled first (a call that changes nothing enabled proves nothing) and then disabled, on both routes. Run `node tests/consumer/build.mjs` first.

for (const route of ['prop', 'form']) {
  test(`${route}: input_otp: a pasted code, a typed digit and collect() write nothing when disabled`, async ({ page }) => {
    const def = caseOf('input_otp')
    const box = (n) => page.locator(`[data-case="${def.id}"] input[aria-label="Digit ${n}"]`)
    const paste = () => box(1).evaluate((el) => {
      const data = new DataTransfer()
      data.setData('text', '9999')
      el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }))
    })
    const type = () => box(2).evaluate((el) => {
      el.value = '8'
      el.dispatchEvent(new Event('input', { bubbles: true }))
    })
    const collect = () => page.locator(`[data-case="${def.id}"] [x-data]`).first().evaluate((el) => {
      const data = window.Alpine.$data(el)
      data.digits = ['7', '7', '7', '7']
      data.collect()
    })

    for (const gesture of [paste, type, collect]) {
      await openCase(page, def, { route })
      const before = (await observe(page, def)).value
      await gesture()
      await page.waitForTimeout(250)
      expect((await observe(page, def)).value, `${gesture.name}: changes the value, enabled`).not.toBe(before)

      await openCase(page, def, { route, off: true })
      const initial = (await observe(page, def)).value
      await gesture()
      await page.waitForTimeout(250)
      expect((await observe(page, def)).value, `${gesture.name}: changed the value of a disabled field`).toBe(initial)
      expect(page.errors).toEqual([])
    }
  })

  test(`${route}: time_picker: the arrows, AM/PM and setTime() write nothing when disabled`, async ({ page }) => {
    const def = caseOf('time_picker')
    const key = (label, k) => () => page.locator(`[data-case="${def.id}"] input[aria-label="${label}"]`).evaluate((el, k) => {
      el.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true }))
    }, k)
    const call = (method, ...args) => () => page.locator(`[data-case="${def.id}"] [x-data]`).first().evaluate((el, [method, args]) => {
      window.Alpine.$data(el)[method](...args)
    }, [method, args])
    const typed = () => page.locator(`[data-case="${def.id}"] [x-data]`).first().evaluate((el) => {
      window.Alpine.$data(el).hr = '07'
    })

    const gestures = {
      'ArrowUp on the hour': key('Hour', 'ArrowUp'),
      'ArrowDown on the minute': key('Minute', 'ArrowDown'),
      'ArrowUp on AM/PM': key('AM or PM', 'ArrowUp'),
      'up(hr)': call('up', 'hr', '3'),
      'down(min)': call('down', 'min', '3'),
      'up(am)': call('up', 'am'),
      'a typed hour reaching setTime()': typed,
    }

    for (const [name, gesture] of Object.entries(gestures)) {
      await openCase(page, def, { route })
      const before = (await observe(page, def)).value
      await gesture()
      await page.waitForTimeout(250)
      expect((await observe(page, def)).value, `${name}: changes the value, enabled`).not.toBe(before)

      await openCase(page, def, { route, off: true })
      const initial = (await observe(page, def)).value
      await gesture()
      await page.waitForTimeout(250)
      expect((await observe(page, def)).value, `${name}: changed the value of a disabled picker`).toBe(initial)
      expect(page.errors).toEqual([])
    }
  })

  test(`${route}: slider: clamp() writes nothing when disabled`, async ({ page }) => {
    const def = caseOf('slider')
    const outOfRange = () => page.locator(`[data-case="${def.id}"] [x-data]`).first().evaluate((el) => {
      const data = window.Alpine.$data(el)
      data.value = 500
      data.clamp()
      return data.value
    })

    await openCase(page, def, { route })
    expect(await outOfRange(), 'enabled: the slider clamps').toBe(100)

    await openCase(page, def, { route, off: true })
    expect(await outOfRange(), 'disabled: the slider keeps what it was given').toBe(500)
  })
}

// An OTP's paste guard reads the box's own `readonly` too, so a host's plain `readonly` (no disabled state) blocks a paste as it blocks typing.
test('input_otp: a host readonly blocks a paste', async ({ page }) => {
  const def = caseOf('input_otp')
  await openCase(page, def)
  const box = page.locator(`[data-case="${def.id}"] input[aria-label="Digit 1"]`)
  const before = (await observe(page, def)).value

  await box.evaluate((el) => {
    el.readOnly = true
    const data = new DataTransfer()
    data.setData('text', '9999')
    el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }))
  })
  await page.waitForTimeout(250)
  expect((await observe(page, def)).value).toBe(before)
})
