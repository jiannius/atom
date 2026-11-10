import { test, expect } from '@playwright/test'
import { caseOf, openCase, observe } from './disabled-helpers.js'

// A disabled select blocks its value by more than a CSS class (the contract's second rule): the chip remove, the clear x and the option rows are hidden or
// covered by utilities, and a host that has not rebuilt its Tailwind has neither. The rig's mouse gestures cannot reach a hidden node, so this spec calls the
// node's own listener, as a click on a child of a disabled button does in every engine (`el.click()` ignores `display: none` and `pointer-events: none`),
// and asserts the handlers' guards hold the value. Run `node tests/consumer/build.mjs` first, as for the rest of the rig.

const targets = [
  { id: 'select_listbox', what: 'an option row', selector: '[data-atom-option]', nth: 1 },
  { id: 'select_listbox_clear', what: 'the clear x', selector: '[data-atom-select-affix] > div:last-of-type' },
  { id: 'select_listbox_multiple', what: 'a chip remove', selector: 'button[aria-haspopup] .border-r .cursor-pointer' },
  { id: 'select_listbox_multiple', what: 'an option row', selector: '[data-atom-option]', nth: 2 },
  { id: 'select_listbox_list', what: 'a list row remove', selector: '[data-atom-select-listbox] button.shrink-0' },
  { id: 'select_native_multiple', what: 'a chip remove', selector: '[data-atom-select-native] .border-r .cursor-pointer' },
  { id: 'select_filter', what: 'the clear x', selector: 'button[role=combobox] .cursor-pointer' },
  { id: 'select_filter', what: 'an option row', selector: '[data-atom-option]', nth: 1 },
]

for (const route of ['prop', 'form']) {
  for (const t of targets) {
    test(`${route}: ${t.id}: calling the listener of ${t.what} changes nothing when disabled`, async ({ page }) => {
      const def = caseOf(t.id)

      // enabled, the same call must work (or the probe proves nothing): the rows that are always drawn are the ones to call
      await openCase(page, def, { route })
      const before = (await observe(page, def)).value
      const called = await page.locator(`[data-case="${def.id}"] ${t.selector}`).nth(t.nth ?? 0).evaluate((el) => { el.click(); return true })
      expect(called).toBe(true)
      await page.waitForTimeout(150)
      expect((await observe(page, def)).value, `${t.what} changes the value, enabled`).not.toBe(before)

      await openCase(page, def, { route, off: true })
      const initial = (await observe(page, def)).value
      await page.locator(`[data-case="${def.id}"] ${t.selector}`).nth(t.nth ?? 0).evaluate((el) => el.click())
      await page.waitForTimeout(250)
      expect((await observe(page, def)).value, `${t.what} changed the value of a disabled control`).toBe(initial)
      expect(page.errors).toEqual([])
    })
  }
}

// atom.css gave every button `cursor: pointer` outside any layer, which beat the `cursor-not-allowed` utility a disabled control carries:
// it now applies to an enabled button only.
for (const route of ['prop', 'form']) {
  for (const id of ['select_listbox', 'select_filter']) {
    test(`${route}: ${id}: the disabled trigger shows the not-allowed cursor, the enabled one the pointer`, async ({ page }) => {
      const def = caseOf(id)
      const cursor = () => page.locator(`[data-case="${def.id}"] button[aria-haspopup]`).evaluate((el) => getComputedStyle(el).cursor)

      await openCase(page, def, { route })
      expect(await cursor()).toBe('pointer')

      await openCase(page, def, { route, off: true })
      expect(await cursor()).toBe('not-allowed')
    })
  }
}

// The trailing affix of a filled, clearable listbox holds an x (clear) and a caret (shown instead of the x while disabled). Exactly one has a box at a time:
// visibility sits on plain wrapper divs, because an icon's own `inline-flex` sorts after `hidden` in the consumer's CSS and would draw both. Measured as the
// browser draws it (a box), on both Tailwind builds the hosts use.
for (const tw of ['floor', 'latest']) {
  for (const route of ['prop', 'form']) {
    test(`${tw} build, ${route}: select_listbox_clear: the affix draws one icon, enabled with a value and disabled`, async ({ page }) => {
      const def = caseOf('select_listbox_clear')
      const drawn = () => page.locator(`[data-case="${def.id}"] [data-atom-select-affix] [data-atom-icon]`).evaluateAll((icons) => icons.filter((el) => {
        const box = el.getBoundingClientRect()
        return box.width > 0 && box.height > 0
      }).length)

      await openCase(page, def, { route, tw })
      expect(await drawn(), 'enabled: the x only').toBe(1)

      await openCase(page, def, { route, off: true, tw })
      expect(await drawn(), 'disabled: the caret only').toBe(1)
    })
  }
}

// The removes are hidden by a variant on an element that also has a display class of its own (`flex`): the variant must win in the consumer's CSS, which
// a class string cannot tell. Computed `display`, on both builds.
const removes = [
  { id: 'select_listbox_multiple', what: 'a chip remove', selector: 'button[aria-haspopup] .border-r .cursor-pointer' },
  { id: 'select_listbox_list', what: 'a list row remove', selector: '[data-atom-select-listbox] button.shrink-0' },
  { id: 'select_native_multiple', what: 'a chip remove', selector: '[data-atom-select-native] .border-r .cursor-pointer' },
  { id: 'select_filter', what: 'the clear x', selector: 'button[role=combobox] .cursor-pointer' },
]

for (const tw of ['floor', 'latest']) {
  for (const r of removes) {
    test(`${tw} build: ${r.id}: ${r.what} is drawn enabled and display: none disabled`, async ({ page }) => {
      const def = caseOf(r.id)
      const display = () => page.locator(`[data-case="${def.id}"] ${r.selector}`).first().evaluate((el) => getComputedStyle(el).display)

      await openCase(page, def, { route: 'prop', tw })
      expect(await display(), 'enabled').not.toBe('none')

      await openCase(page, def, { route: 'prop', off: true, tw })
      expect(await display(), 'disabled').toBe('none')
    })
  }
}
