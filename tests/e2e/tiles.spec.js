import { test, expect } from '@playwright/test'
import { open, contrastOf, style, focusByKeyboard } from './controls-helpers.js'

// Selectable tiles, `variant="tile"` on <atom:radio.group> and <atom:checkbox.group> (issue #213; fixture tests/Fixtures/TilesFixture.php, page
// resources/views/e2e/tiles.blade.php). The tile is a label around a real, sr-only input, so the keyboard is the browser's: Tab enters a radio group
// once and the arrows move inside it, Space toggles a checkbox. What atom adds is the look: the grid, the checked ring, the keyboard ring on the tile,
// the disabled and invalid states, drawn from `:has()` in atom.css. On the consumer's Tailwind (floor and latest), light and dark, three accents.

const ACCENTS = ['#4c55c9', '#f5c518', '#9f5afd']
const tiles = (page, group) => page.getByTestId(group).locator('[data-atom-tile]')
const tile = (page, group, text) => tiles(page, group).filter({ hasText: text })
const input = (page, group, text) => tile(page, group, text).locator('input')
const serverPlan = (page) => page.getByTestId('server-plan')
const serverExtras = (page) => page.getByTestId('server-extras')
const box = (locator) => locator.evaluate((el) => { const r = el.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height } })

const show = async (page, { tw = 'latest', dark = false, accent = null, width = 1000 } = {}) => {
  await page.setViewportSize({ width, height: 900 })
  await open(page, { path: 'tiles', tw, dark, accent })
  await page.waitForFunction(() => window.Livewire && window.Alpine)
  await expect(tiles(page, 'plan-group')).toHaveCount(4)
}

/** A token's resolved colour, read the way the browser serialises a computed `color`. */
const token = (page, name) => page.evaluate((n) => {
  const probe = document.createElement('i')
  probe.style.color = `var(${n})`
  document.body.append(probe)
  const colour = getComputedStyle(probe).color
  probe.remove()
  return colour
}, name)

/**
 * Put the keyboard's focus on a control, ring and all: focusByKeyboard, and where the engine's Tab did not come back to it (Firefox leaves the page's first
 * Tab stop to the browser's own UI) a key press and a script focus, which every engine reads as the keyboard's.
 */
const keyboardFocus = async (page, locator) => {
  await focusByKeyboard(page, locator)
  if (!(await locator.evaluate((el) => document.activeElement === el && el.matches(':focus-visible')))) {
    await page.keyboard.press('Shift')
    await locator.focus()
  }
}

const ring = (el) => { const s = getComputedStyle(el); return { border: s.borderTopColor, shadow: s.boxShadow, outline: s.outlineStyle === 'none' ? 'none' : `${s.outlineWidth} ${s.outlineStyle}`, outlineColor: s.outlineColor, offset: s.outlineOffset, bg: s.backgroundColor, cursor: s.cursor } }

for (const tw of ['floor', 'latest']) {
  test.describe(`selectable tiles, ${tw} consumer build`, () => {
    test('the group is a grid of tiles at least 12rem wide, which wraps by the room and never scrolls the page', async ({ page }) => {
      for (const [width, columns] of [[1000, 3], [600, 2], [360, 1], [320, 1]]) {
        await show(page, { tw, width })
        const lefts = await tiles(page, 'plan-group').evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().left)))
        expect(new Set(lefts).size, `${width}px wide`).toBe(columns)
        const extras = await tiles(page, 'extras-group').evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().left)))
        expect(new Set(extras).size, `${width}px wide, checkbox group`).toBe(Math.min(columns, 3))

        const wide = await tiles(page, 'plan-group').evaluateAll((els) => els.map((el) => el.getBoundingClientRect().width))
        for (const w of wide) expect(w, `${width}px wide`).toBeGreaterThanOrEqual(width === 320 ? 200 : 192)

        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `${width}px: no sideways scroll`).toBe(true)
        // a tile is as wide as its column, not its text
        const cols = await tiles(page, 'plan-group').evaluateAll((els) => els.slice(0, 2).map((el) => el.getBoundingClientRect().width))
        if (columns > 1) expect(Math.abs(cols[0] - cols[1])).toBeLessThan(1)
      }
    })

    test('a tile is a bordered panel with the indicator over its top end, clear of the text', async ({ page }) => {
      await show(page, { tw })
      const t = tile(page, 'plan-group', 'Starter')
      const s = await style(t, 'border-top-left-radius', 'border-top-width', 'padding-top', 'padding-left')
      const panel = await page.evaluate(() => { const p = document.createElement('i'); p.className = 'rounded-panel'; document.body.append(p); const r = getComputedStyle(p).borderTopLeftRadius; p.remove(); return r })
      expect([s['border-top-left-radius'], s['border-top-width'], s['padding-top'], s['padding-left']]).toEqual([panel, '1px', '12px', '12px'])

      const tb = await box(t)
      const dot = await box(t.locator('[data-atom-tile-indicator]'))
      expect([dot.width, dot.height]).toEqual([16, 16])
      expect(Math.round(tb.right - dot.right)).toBe(13)
      expect(Math.round(dot.top - tb.top)).toBe(13)
      // the label's text ends before the indicator starts
      const text = await t.locator('[data-atom-tile-body]').evaluate((el) => { const r = document.createRange(); r.selectNodeContents(el); return r.getBoundingClientRect().right })
      expect(text).toBeLessThanOrEqual(dot.left)
    })

    test('the input is a real sr-only radio or checkbox in the label, not hidden from the browser', async ({ page }) => {
      await show(page, { tw })
      for (const [group, text, type] of [['plan-group', 'Starter', 'radio'], ['extras-group', 'Gift wrap', 'checkbox']]) {
        const el = input(page, group, text)
        expect(await el.getAttribute('type')).toBe(type)
        const s = await el.evaluate((n) => { const c = getComputedStyle(n); const r = n.getBoundingClientRect(); return { display: c.display, visibility: c.visibility, w: r.width, h: r.height, position: c.position } })
        expect(s.display).not.toBe('none')
        expect(s.visibility).toBe('visible')
        expect([s.w, s.h, s.position]).toEqual([1, 1, 'absolute'])
        // a click anywhere on the tile, its caption included, toggles it
        await tile(page, group, text).locator('[data-atom-caption]').click()
        await expect(el).toBeChecked()
      }
    })

    test('radio tiles: Tab enters the group once, the arrows move the choice and skip a disabled tile, and the server follows', async ({ page }) => {
      await show(page, { tw })
      await expect(input(page, 'plan-group', 'Growth')).toBeChecked()

      // the first Tab stop of the page is the checked radio of the first group
      await page.keyboard.press('Tab')
      await expect(input(page, 'plan-group', 'Growth')).toBeFocused()

      // Scale is disabled: the arrow goes past it to Custom
      await page.keyboard.press('ArrowRight')
      await expect(input(page, 'plan-group', 'Custom')).toBeFocused()
      await expect(input(page, 'plan-group', 'Custom')).toBeChecked()
      await expect(serverPlan(page)).toHaveText('custom')

      await page.keyboard.press('ArrowUp')
      await expect(input(page, 'plan-group', 'Growth')).toBeChecked()
      await page.keyboard.press('ArrowLeft')
      await expect(input(page, 'plan-group', 'Starter')).toBeChecked()
      await expect(serverPlan(page)).toHaveText('starter')

      // one Tab leaves the group (the other three tiles are not stops)
      await page.keyboard.press('Tab')
      await expect(page.getByTestId('extras-group').locator('input').first()).toBeFocused()
    })

    test('checkbox tiles: Space toggles, each enabled tile is a Tab stop, arrows do nothing', async ({ page }) => {
      await show(page, { tw })
      await input(page, 'extras-group', 'Gift wrap').focus()
      await expect(input(page, 'extras-group', 'Gift wrap')).not.toBeChecked()

      await page.keyboard.press('Space')
      await expect(input(page, 'extras-group', 'Gift wrap')).toBeChecked()
      await expect(serverExtras(page)).toHaveText('express,gift')

      await page.keyboard.press('ArrowRight')
      await expect(input(page, 'extras-group', 'Gift wrap')).toBeFocused()

      await page.keyboard.press('Space')
      await expect(input(page, 'extras-group', 'Gift wrap')).not.toBeChecked()
      await expect(serverExtras(page)).toHaveText('express')

      // Tab goes to the next enabled tile, past the disabled one
      await page.keyboard.press('Tab')
      await expect(input(page, 'extras-group', 'Express delivery')).toBeFocused()
      await page.keyboard.press('Tab')
      await expect(input(page, 'extras-group', 'Insurance')).not.toBeFocused()
    })

    test('the keyboard ring is a 2px outline on the tile, only for the keyboard, and not drawn on a click', async ({ page }) => {
      await show(page, { tw })
      const t = tile(page, 'plan-group', 'Growth')
      expect((await t.evaluate(ring)).outline).toBe('none')

      // WebKit does not match :focus-visible on a radio that an arrow key moved the focus to (a plain radio gets no ring there either), so the ring
      // is read on the tile that Tab reached; the arrows are proved above
      await keyboardFocus(page, input(page, 'plan-group', 'Growth'))
      const r = await t.evaluate(ring)
      expect([r.outline, r.offset]).toEqual(['2px solid', '2px'])
      expect(r.outlineColor).toBe(await token(page, '--atom-focus'))
      // and on the others, not
      expect((await tile(page, 'plan-group', 'Starter').evaluate(ring)).outline).toBe('none')

      // a checkbox tile too
      await keyboardFocus(page, input(page, 'extras-group', 'Gift wrap'))
      expect((await tile(page, 'extras-group', 'Gift wrap').evaluate(ring)).outline).toBe('2px solid')

      // a click draws none
      await tile(page, 'plan-group', 'Custom').click()
      await expect(input(page, 'plan-group', 'Custom')).toBeChecked()
      expect((await tile(page, 'plan-group', 'Custom').evaluate(ring)).outline).toBe('none')
    })

    test('checked: an accent border with a 1px ring; unchecked: the hairline; hover: the stronger hairline', async ({ page }) => {
      await show(page, { tw })
      const ink = await token(page, '--atom-accent-ink')
      const border = await token(page, '--atom-border')
      const strong = await token(page, '--atom-border-strong')

      const checked = await tile(page, 'plan-group', 'Growth').evaluate(ring)
      expect(checked.border).toBe(ink)
      expect(checked.shadow).toContain(ink)
      expect(checked.shadow).toMatch(/0px 0px 0px 1px/)

      const plain = await tile(page, 'plan-group', 'Starter').evaluate(ring)
      expect([plain.border, plain.shadow]).toEqual([border, 'none'])

      await tile(page, 'plan-group', 'Starter').hover()
      expect((await tile(page, 'plan-group', 'Starter').evaluate(ring)).border).toBe(strong)

      // the indicator is the filled dot of the same ink
      const dot = tile(page, 'plan-group', 'Growth').locator('[data-atom-tile-indicator]')
      expect(await dot.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(ink)
    })

    test('wire:model round-trips from a click and from the server, and survives a morph', async ({ page }) => {
      await show(page, { tw })

      await tile(page, 'plan-group', 'Starter').click()
      await expect(serverPlan(page)).toHaveText('starter')
      await tile(page, 'extras-group', 'Gift wrap').click()
      await expect(serverExtras(page)).toHaveText('express,gift')

      // a round trip that re-renders the page keeps both choices, and the ring that is drawn from them
      await page.getByTestId('bump').click()
      await expect(page.getByTestId('ticks')).toHaveText('1')
      await expect(input(page, 'plan-group', 'Starter')).toBeChecked()
      await expect(input(page, 'extras-group', 'Gift wrap')).toBeChecked()
      await expect(input(page, 'extras-group', 'Express delivery')).toBeChecked()
      expect((await tile(page, 'plan-group', 'Starter').evaluate(ring)).shadow).toMatch(/0px 0px 0px 1px/)
      expect((await tile(page, 'plan-group', 'Growth').evaluate(ring)).shadow).toBe('none')

      // the server changes the choice: the tile follows
      await page.evaluate(() => { const wire = window.Livewire.first(); wire.plan = 'custom'; wire.extras = [] })
      await page.getByTestId('bump').click()
      await expect(input(page, 'plan-group', 'Custom')).toBeChecked()
      await expect(input(page, 'extras-group', 'Gift wrap')).not.toBeChecked()
      expect((await tile(page, 'plan-group', 'Custom').evaluate(ring)).shadow).toMatch(/0px 0px 0px 1px/)
      expect((await tile(page, 'extras-group', 'Express delivery').evaluate(ring)).shadow).toBe('none')
    })

    test('a keyboard user keeps the focus and the ring across a morph', async ({ page }) => {
      await show(page, { tw })
      await keyboardFocus(page, input(page, 'plan-group', 'Growth'))
      await expect(tile(page, 'plan-group', 'Growth').locator('input')).toBeFocused()

      await page.evaluate(() => window.Livewire.first().bump())
      await expect(page.getByTestId('ticks')).toHaveText('1')
      await expect(input(page, 'plan-group', 'Growth')).toBeFocused()
      expect((await tile(page, 'plan-group', 'Growth').evaluate(ring)).outline).toBe('2px solid')
    })

    test('x-model works on the same tiles: the initial choice, a click and the arrow keys', async ({ page }) => {
      await show(page, { tw })
      await expect(input(page, 'size-group', 'Medium')).toBeChecked()
      await expect(input(page, 'toppings-group', 'Cheese')).toBeChecked()
      await expect(page.getByTestId('alpine-size')).toHaveText('m')

      await tile(page, 'size-group', 'Large').click()
      await expect(page.getByTestId('alpine-size')).toHaveText('l')
      await expect(input(page, 'size-group', 'Medium')).not.toBeChecked()
      expect((await tile(page, 'size-group', 'Large').evaluate(ring)).shadow).toMatch(/0px 0px 0px 1px/)

      await input(page, 'size-group', 'Large').focus()
      await page.keyboard.press('ArrowLeft')
      await expect(page.getByTestId('alpine-size')).toHaveText('m')

      await tile(page, 'toppings-group', 'Olives').click()
      await expect(page.getByTestId('alpine-toppings')).toHaveText('cheese,olives')
      await tile(page, 'toppings-group', 'Cheese').click()
      await expect(page.getByTestId('alpine-toppings')).toHaveText('olives')
      expect((await tile(page, 'toppings-group', 'Cheese').evaluate(ring)).shadow).toBe('none')

      // and a morph leaves the Alpine state alone
      await page.getByTestId('bump').click()
      await expect(page.getByTestId('ticks')).toHaveText('1')
      await expect(input(page, 'size-group', 'Medium')).toBeChecked()
      await expect(page.getByTestId('alpine-toppings')).toHaveText('olives')
    })

    test('disabled: the tile is sunken with a faint border and takes no click, key or ring; a group and a form disable theirs', async ({ page }) => {
      await show(page, { tw })
      const scale = tile(page, 'plan-group', 'Scale')
      const sunken = await token(page, '--atom-surface-sunken')
      const r = await scale.evaluate(ring)
      expect([r.bg, r.cursor, r.border]).toEqual([sunken, 'not-allowed', await token(page, '--atom-border')])
      expect(await scale.locator('input').getAttribute('data-atom-disabled')).toBe('self')

      await scale.click({ force: true })
      await expect(input(page, 'plan-group', 'Scale')).not.toBeChecked()
      await expect(serverPlan(page)).toHaveText('growth')

      // the indicator is dimmed, the text is the subtle colour
      expect(await scale.locator('[data-atom-tile-indicator]').evaluate((el) => getComputedStyle(el).opacity)).toBe('0.5')
      expect(await scale.locator('[data-atom-tile-body]').evaluate((el) => getComputedStyle(el).color)).toBe(await token(page, '--atom-fg-subtle'))

      // a checked tile of a disabled group or form: still shown checked, with no ring, and nothing to click
      for (const group of ['disabled-radios', 'disabled-form']) {
        const t = page.getByTestId(group).locator('[data-atom-tile]').first()
        const input = t.locator('input')
        expect(await input.getAttribute('data-atom-disabled'), group).toBe('inherited')
        await expect(input, group).toBeChecked()
        await expect(input, group).toBeDisabled()
        const s = await t.evaluate(ring)
        expect([s.shadow, s.bg], group).toEqual(['none', sunken])
        // but a faint accent border says what was chosen: not the plain disabled hairline, and not the full ink of an enabled tile
        expect(s.border, group).not.toBe(await token(page, '--atom-border'))
        expect(s.border, group).not.toBe(await token(page, '--atom-accent-ink'))
      }
      expect((await page.getByTestId('disabled-radios').locator('[data-atom-tile]').nth(1).evaluate(ring)).border).toBe(await token(page, '--atom-border'))

      await page.getByTestId('disabled-radios').locator('[data-atom-tile]').nth(1).click({ force: true })
      await expect(page.getByTestId('disabled-radios').locator('input').nth(1)).not.toBeChecked()

      // Tab never lands on a disabled tile
      await page.getByTestId('bump').focus()
      await page.keyboard.press('Tab')
      expect(await page.evaluate(() => document.activeElement?.closest('[data-atom-tile]') === null)).toBe(true)
    })

    test('invalid: the group error or a checkbox own error draws the danger border, and a checked invalid tile rings in danger', async ({ page }) => {
      await show(page, { tw })
      const danger = await token(page, '--atom-danger-fg')
      const ink = await token(page, '--atom-accent-ink')

      for (const t of [tiles(page, 'invalid-radios').first(), tiles(page, 'invalid-radios').nth(1), tiles(page, 'invalid-checkboxes').first()]) {
        expect((await t.evaluate(ring)).border).toBe(danger)
      }
      // a valid group on the same page is not, and nor is a group in a field whose OTHER control has an error (only the error under the group counts)
      await expect(page.getByTestId('scoped-section').getByText('Note is bad')).toBeVisible()
      for (const t of await tiles(page, 'mixed-radios').all()) expect((await t.evaluate(ring)).border).not.toBe(danger)
      expect((await tile(page, 'plan-group', 'Starter').evaluate(ring)).border).not.toBe(danger)

      await expect(page.getByTestId('invalid-radios').locator('xpath=ancestor::*[@data-atom-input-field][1]').getByText('Choose a delivery option')).toBeVisible()
      // the checkbox's own message is inside its label (so it is read with it), under the caption
      await expect(tiles(page, 'invalid-checkboxes').first().locator('[data-atom-error]')).toHaveText('Accept the terms')

      await tile(page, 'invalid-radios', 'Post').click()
      const r = await tile(page, 'invalid-radios', 'Post').evaluate(ring)
      expect(r.border).toBe(danger)
      expect(r.shadow).toContain(danger)
      expect(r.shadow).not.toContain(ink)
      await tile(page, 'invalid-checkboxes', 'Terms').click()
      expect((await tile(page, 'invalid-checkboxes', 'Terms').evaluate(ring)).shadow).toContain(danger)
    })

    test('screen readers: each tile is named by its label and caption, the group by its field label or an aria-label', async ({ page }) => {
      await show(page, { tw })

      await expect(page.getByRole('radiogroup', { name: 'Plan' })).toBeVisible()
      await expect(page.getByRole('group', { name: 'Extras' })).toBeVisible()
      await expect(page.getByRole('radio', { name: 'Starter RM 50 a month' })).toBeVisible()
      await expect(page.getByRole('radio', { name: 'Scale Sold out' })).toBeDisabled()
      await expect(page.getByRole('radio', { name: /^Custom RM 999/ })).toBeVisible()
      await expect(page.getByRole('checkbox', { name: 'Gift wrap RM 5' })).toBeVisible()
      await expect(page.getByRole('checkbox', { name: 'Insurance Not for this order' })).toBeDisabled()
      // the dot is decorative: nothing of it in the tree
      expect(await tiles(page, 'plan-group').first().locator('[data-atom-tile-indicator]').getAttribute('aria-hidden')).toBe('true')

      await expect(tile(page, 'plan-group', 'Growth')).toMatchAriaSnapshot(`
        - radio "Growth RM 120 a month" [checked]
      `)
    })

    test('variant="card" is unchanged: one bordered panel of rows, no tile, the keyboard as before', async ({ page }) => {
      await show(page, { tw })
      const group = page.getByTestId('card-group')
      await expect(group.locator('[data-atom-tile]')).toHaveCount(0)
      await expect(page.locator('[data-atom-card] [data-testid="card-group"]')).toHaveCount(1)
      await expect(group.locator('[data-atom-radio]')).toHaveCount(2)

      await group.locator('input').first().focus()
      await page.keyboard.press('ArrowDown')
      await expect(group.locator('input').nth(1)).toBeChecked()
      expect((await style(group.locator('[data-atom-radio]').first(), 'padding-left'))['padding-left']).toBe('16px')
    })
  })
}

test.describe('selectable tiles, contrast in light and dark', () => {
  for (const dark of [false, true]) {
    for (const accent of ACCENTS) {
      test(`${dark ? 'dark' : 'light'}, accent ${accent}: text 4.5:1, indicator, checked ring and focus ring 3:1 against the tile`, async ({ page }) => {
        await show(page, { dark, accent })
        const checked = tile(page, 'plan-group', 'Growth')
        const plain = tile(page, 'plan-group', 'Starter')

        for (const t of [checked, plain, tile(page, 'plan-group', 'Custom'), tile(page, 'extras-group', 'Gift wrap')]) {
          expect((await t.locator('[data-atom-tile-body]').evaluate(contrastOf, { what: 'text' })).ratio, 'label').toBeGreaterThanOrEqual(4.5)
        }
        for (const t of [checked, plain, tile(page, 'extras-group', 'Gift wrap')]) {
          expect((await t.locator('[data-atom-caption]').evaluate(contrastOf, { what: 'text' })).ratio, 'caption').toBeGreaterThanOrEqual(4.5)
        }

        // the indicator's boundary tells the control from the tile; the checked fill and the ring are the accent ink
        expect((await plain.locator('[data-atom-tile-indicator]').evaluate(contrastOf, { what: 'border' })).ratio, 'unchecked indicator').toBeGreaterThanOrEqual(3)
        expect((await checked.locator('[data-atom-tile-indicator]').evaluate(contrastOf, { what: 'fill' })).ratio, 'checked indicator').toBeGreaterThanOrEqual(3)
        expect((await checked.evaluate(contrastOf, { what: 'border' })).ratio, 'checked ring').toBeGreaterThanOrEqual(3)

        await focusByKeyboard(page, plain.locator('input'))
        expect((await plain.evaluate(contrastOf, { what: 'outline' })).ratio, 'focus ring').toBeGreaterThanOrEqual(3)

        // an invalid tile's border and its message
        const danger = tiles(page, 'invalid-radios').first()
        expect((await danger.evaluate(contrastOf, { what: 'border' })).ratio, 'invalid border').toBeGreaterThanOrEqual(3)
        expect((await page.getByText('Accept the terms').evaluate(contrastOf, { what: 'text' })).ratio, 'error message').toBeGreaterThanOrEqual(4.5)
      })
    }
  }
})
