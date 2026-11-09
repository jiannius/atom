import { test, expect } from '@playwright/test'

// Escape closes the topmost layer only, wherever the focus is (#177): every popover a modal can hold, then the modal. Fixture:
// resources/views/e2e/modal-escape-layers.blade.php at /atom/e2e/modal-escape-layers (the Livewire fixture of modal-escape.spec.js holds the rest).
// The listener that does it is helpers/dialog-escape.js. A mention list is not a popover: the editor closes it and stops the key, so the
// modal around the editor stays for that press. The modal asks whether a popover took the press, not whether the key was prevented, since
// ProseMirror prevents every Escape in an editor and a prevented key would keep the modal from closing with the focus in a field.
//
// Real key presses; no page.evaluate between the presses of a hold (Playwright's evaluate counts as user activation in Chromium).

const go = async (page, query = '') => {
  await page.addInitScript(() => {
    window.__closed = []
    document.addEventListener('closed', (e) => window.__closed.push(e.detail))
  })
  await page.goto(`/atom/e2e/modal-escape-layers${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined && window.Livewire !== undefined)
}

const modal = (page, name = 'layers') => page.locator(`dialog[data-modal="${name}"]`)
const show = (page, name = 'layers') => page.evaluate((n) => atom.modal(n).show(), name)
const closedEvents = (page) => page.evaluate(() => window.__closed)
const press = async (page, key = 'Escape', times = 1) => {
  for (let i = 0; i < times; i++) await page.keyboard.press(key)
}
const settle = (page) => page.waitForTimeout(150)
const popoverOpen = (locator) => locator.evaluate((el) => el.matches(':popover-open'))
const focus = (page, selector) => page.locator(selector).evaluate((el) => el.focus())
const activate = async (page, selector) => {
  await focus(page, selector)
  await page.keyboard.press('Enter')
}
// whether the modal is still open, after a moment for a late close to land
const modalIs = async (page, open, name = 'layers') => {
  if (open) {
    await settle(page)
    expect(await modal(page, name).evaluate((el) => el.open)).toBe(true)
  }
  else await expect.poll(() => modal(page, name).evaluate((el) => el.open)).toBe(false)
}

for (const escapable of [false, true]) {
  const query = escapable ? '' : '?escapable=0'
  const lastIs = (page) => modalIs(page, !escapable)

  test.describe(escapable ? 'layers in an escapable modal' : 'layers in a modal with escapable false', () => {
    // a layer opened by `open`, seen by `isOpen`: the first Escape closes it alone, the next closes the modal (an escapable one)
    const oneLayer = (title, open, isOpen, afterFirst, focusInside) => {
      test(title, async ({ page }) => {
        await go(page, query)
        await show(page)
        await open(page)
        await expect.poll(() => isOpen(page)).toBe(true)

        if (focusInside) await focusInside(page)

        await press(page)
        await expect.poll(() => isOpen(page)).toBe(false)
        await modalIs(page, true)
        if (afterFirst) await afterFirst(page)

        await press(page, 'Escape', 3)
        await lastIs(page)
      })
    }

    oneLayer(
      'a date range takes the first Escape, then the modal',
      (page) => page.locator('[data-range] input[type="text"]').first().click(),
      (page) => popoverOpen(page.locator('[data-range] [popover]')),
    )

    oneLayer(
      'a typed date: Escape reverts the text and closes the calendar, then the modal',
      async (page) => {
        const field = page.locator('[data-typed] input[type="text"]').first()
        await field.click()
        await field.fill('02/1')
      },
      (page) => popoverOpen(page.locator('[data-typed] [popover]')),
      async (page) => {
        // the revert runs on the press that closed the calendar (the listener does not stop the key), and the half-typed text is gone
        await expect(page.locator('[data-typed] input[type="text"]').first()).toHaveValue('')
      },
    )

    oneLayer(
      'a filter select takes the first Escape, then the modal',
      (page) => page.locator('[data-filter-key="status"] [data-atom-filter-chip-body]').click(),
      (page) => popoverOpen(page.locator('[data-filter-key="status"] [popover]')),
    )

    oneLayer(
      'a searchable select takes the first Escape, then the modal',
      (page) => activate(page, '[data-searchable] button[aria-haspopup="listbox"]'),
      (page) => popoverOpen(page.locator('[data-searchable] [popover]')),
    )

    oneLayer(
      "a table's column picker takes the first Escape, then the modal",
      (page) => page.locator('[data-atom-column-picker-trigger]').click(),
      (page) => popoverOpen(page.locator('[data-atom-column-menu]')),
    )

    test('a date picker in a dropdown: the date picker, then the dropdown, then the modal', async ({ page }) => {
      await go(page, query)
      await show(page)
      await activate(page, '[data-open-nested-date]')
      const menu = page.locator('[data-nested-date]').locator('xpath=ancestor::*[@popover][1]')
      await expect.poll(() => popoverOpen(menu)).toBe(true)

      await page.locator('[data-nested-date] input[type="text"]').first().click()
      const picker = page.locator('[data-nested-date] [popover]')
      await expect.poll(() => popoverOpen(picker)).toBe(true)

      await press(page)
      await expect.poll(() => popoverOpen(picker)).toBe(false)
      expect(await popoverOpen(menu)).toBe(true)
      await modalIs(page, true)

      await press(page)
      await expect.poll(() => popoverOpen(menu)).toBe(false)
      await modalIs(page, true)

      await press(page, 'Escape', 3)
      await lastIs(page)
    })

    test('a dialog inside a popover: its dropdown, then the dialog, then the popover, then the modal', async ({ page }) => {
      await go(page, query)
      await show(page)
      await activate(page, '[data-open-host]')
      const host = page.locator('[data-host-menu]')
      await expect.poll(() => popoverOpen(host)).toBe(true)

      await activate(page, '[data-open-inside]')
      await expect(modal(page, 'inside')).toBeVisible()
      await activate(page, '[data-open-inside-dropdown]')
      const item = page.getByRole('menuitem', { name: 'Inside item' })
      await expect(item).toBeVisible()

      await press(page)
      await expect(item).toBeHidden()
      await expect(modal(page, 'inside')).toBeVisible()

      // with the dropdown gone the open dialog inside the popover is the top layer, not the popover that holds it
      await press(page)
      await expect(modal(page, 'inside')).toBeHidden()
      expect(await popoverOpen(host)).toBe(true)
      await modalIs(page, true)

      await press(page)
      await expect.poll(() => popoverOpen(host)).toBe(false)
      await modalIs(page, true)

      await press(page, 'Escape', 3)
      await lastIs(page)
    })

    test('an editor: Escape in the text, and with a mention list open the list takes the first one', async ({ page }) => {
      await go(page, query)
      await show(page)
      const content = page.locator('[data-editor] .editor-content').first()
      await expect(content).toBeVisible()

      // no list: Escape from the text is the modal's
      await content.click()
      await press(page, 'Escape', 3)
      await lastIs(page)

      if (escapable) {
        await show(page)
        await content.click()
        await page.keyboard.type('hi @')
        const list = page.locator('[data-editor] .tiptap-mention').getByText('Alice')
        await expect(list).toBeVisible()

        await press(page)
        await expect(list).toBeHidden()
        await modalIs(page, true)

        await press(page)
        await modalIs(page, false)
      }
    })
  })
}

test.describe('the slide-over', () => {
  for (const escapable of [true]) {
    test('a dropdown in a slide-over takes the first Escape, then the slide-over', async ({ page }) => {
      await go(page)
      await page.evaluate(() => atom.modal('slide').slide())
      await expect(modal(page, 'slide')).toBeVisible()
      await activate(page, '[data-open-slide-dropdown]')
      const item = page.getByRole('menuitem', { name: 'Slide item' })
      await expect(item).toBeVisible()

      await press(page)
      await expect(item).toBeHidden()
      await expect(modal(page, 'slide')).toBeVisible()

      await press(page)
      await expect(modal(page, 'slide')).toBeHidden()
      expect(await closedEvents(page)).toEqual(['slide'])
    })
  }
})

test.describe('a press has one owner', () => {
  test('three fast presses close the dropdown and the modal, and the modal says closed once', async ({ page }) => {
    await go(page)
    await show(page)
    await activate(page, '[data-open-nested-date]')
    const menu = page.locator('[data-nested-date]').locator('xpath=ancestor::*[@popover][1]')
    await expect.poll(() => popoverOpen(menu)).toBe(true)

    await press(page, 'Escape', 3)

    await expect(modal(page)).toBeHidden()
    await settle(page)
    expect(await closedEvents(page)).toEqual(['layers'])
  })

  test('an Escape another handler prevented still closes the modal (ProseMirror prevents every one)', async ({ page }) => {
    await go(page)
    await page.evaluate(() => window.addEventListener('keydown', (e) => { if (e.key === 'Escape') e.preventDefault() }, { capture: true }))
    await show(page)
    await focus(page, '[data-open-nested-date]')

    await press(page)

    await expect(modal(page)).toBeHidden()
    await settle(page)
    expect(await closedEvents(page)).toEqual(['layers'])
  })
})
