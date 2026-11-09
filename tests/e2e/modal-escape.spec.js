import { test, expect } from '@playwright/test'

// Escape and a modal (#174). A <dialog> that the browser closes itself (Escape pressed with the focus on <body>, or in a select that
// stops the key, so the dialog's own keydown handler never hears it) used to leave the state it set (`data-open`, `data-atom-modal*`)
// and never said `closed`, and a modal with `:escapable="false"` closed anyway. Two guards: `closedby="none"` (Chromium, Firefox) and a
// `cancel` listener (Safari, which has no closedby), plus a `close` listener that resets what the browser closed. Fixture:
// tests/Fixtures/ModalEscapeFixture.php at /atom/e2e/modal-escape, and the sidebar shell at /atom/e2e/shell-collapse for the drawer.
//
// #177 adds the other half: Escape closes the topmost layer only, so a dropdown, a context menu, a tooltip or a select list inside the dialog
// (the command palette, a dropdown over the sidebar drawer or the header's menu panel) takes the first press and the dialog the next, wherever the
// focus is and in every engine (WebKit closes a popover and its dialog on one native Escape). That is helpers/dialog-escape.js; the rest of the
// layers (date pickers, filters, the column picker, a dialog inside a popover, an editor) are in modal-escape-layers.spec.js.
//
// Chromium and Firefox let a page prevent only two cancels in a row, so a `cancel` listener alone passes two presses and fails the
// third: the hold tests press three times and read the state AFTER the last press, with no page.evaluate between presses
// (Playwright's evaluate counts as user activation in Chromium, which re-arms the cancel; a real user's Escape does not).

const go = async (page, query = '') => {
  await page.addInitScript(() => {
    window.__closed = []
    document.addEventListener('closed', (e) => window.__closed.push(e.detail))
  })
  await page.goto(`/atom/e2e/modal-escape${query}`)
  await page.waitForFunction(() => window.Alpine !== undefined && window.Livewire !== undefined)
}

const dialog = (page, name = 'esc') => page.locator(`dialog[data-modal="${name}"]`)
const show = (page, name = 'esc') => page.evaluate((n) => atom.modal(n).show(), name)
const blur = (page) => page.evaluate(() => document.activeElement?.blur())
const closedEvents = (page) => page.evaluate(() => window.__closed)
const escape = async (page, times = 1) => {
  for (let i = 0; i < times; i++) await page.keyboard.press('Escape')
}
// the browser closes a dialog in the key's own task, but its `close` event is queued (so a closed state is polled for, and a hold is given a moment to be proven)
const settle = (page) => page.waitForTimeout(150)
// the state the modal's script owns: open or not, and the attributes it writes
const state = (page, name = 'esc') => page.evaluate((n) => {
  const el = document.querySelector(`dialog[data-modal="${n}"]`)

  return {
    open: el.open,
    attributes: [...el.attributes].map((a) => a.name).filter((a) => a === 'data-open' || a.startsWith('data-atom-modal')).sort(),
  }
}, name)
const CLOSED = { open: false, attributes: [] }
const OPEN = { open: true, attributes: ['data-atom-modal', 'data-open'] }

// a key press the way a person makes it: the focus is put on the control, then Enter
const activate = async (page, selector) => {
  await page.locator(selector).evaluate((el) => el.focus())
  await page.keyboard.press('Enter')
}

for (const escapable of [false, true]) {
  const query = escapable ? '' : '?escapable=0'

  test.describe(escapable ? 'an escapable modal' : 'a modal with escapable false', () => {
    test('the dialog says so: closedby="none" only where Escape is off', async ({ page }) => {
      await go(page, query)

      const closedby = await dialog(page).evaluate((el) => el.getAttribute('closedby'))
      expect(closedby).toBe(escapable ? null : 'none')
    })

    test('a cancel is prevented only where Escape is off (the guard where closedby does not exist)', async ({ page }) => {
      await go(page, query)
      await show(page)

      const notPrevented = await dialog(page).evaluate((el) => el.dispatchEvent(new Event('cancel', { cancelable: true })))
      expect(notPrevented).toBe(escapable)
    })

    test('Escape with the focus on <body>', async ({ page }) => {
      await go(page, query)
      await show(page)
      await blur(page)

      await escape(page, 3)

      if (escapable) {
        await expect(dialog(page)).toBeHidden()
        await expect.poll(() => state(page)).toEqual(CLOSED)
        await expect.poll(() => closedEvents(page)).toEqual(['esc'])
      }
      else {
        await settle(page)
        expect(await state(page)).toEqual(OPEN)
        expect(await closedEvents(page)).toEqual([])
      }
    })

    // #177: one layer per press wherever the focus is, in every engine, the popover first (helpers/dialog-escape.js).
    for (const focus of ['trigger', 'menu', 'dialog', 'body']) {
      test(`a dropdown with the focus on the ${focus}: it closes alone, then the modal`, async ({ page }) => {
        await go(page, query)
        await show(page)
        await activate(page, '[data-open-dropdown]')
        const item = page.getByRole('menuitem', { name: 'Item one' })
        await expect(item).toBeVisible()

        if (focus === 'menu') await item.evaluate((el) => el.focus())
        if (focus === 'dialog') await dialog(page).evaluate((el) => { el.tabIndex = -1; el.focus() })
        if (focus === 'body') await blur(page)

        await escape(page)
        await expect(item).toBeHidden()
        expect(await state(page)).toEqual(OPEN)

        if (focus === 'menu') await expect(page.locator('[data-open-dropdown]')).toBeFocused()

        await escape(page, 3)

        if (escapable) {
          await expect(dialog(page)).toBeHidden()
          await expect.poll(() => state(page)).toEqual(CLOSED)
          await expect.poll(() => closedEvents(page)).toEqual(['esc'])
        }
        else {
          await settle(page)
          expect(await state(page)).toEqual(OPEN)
        }
      })
    }

    test('a context menu takes the first Escape, then the modal', async ({ page }) => {
      await go(page, query)
      await show(page)
      await page.locator('[data-context-area]').click({ button: 'right' })
      const item = page.getByRole('menuitem', { name: 'Context one' })
      await expect(item).toBeVisible()
      await item.evaluate((el) => el.focus())

      await escape(page)
      await expect(item).toBeHidden()
      expect(await state(page)).toEqual(OPEN)

      await escape(page, 3)

      if (escapable) await expect.poll(() => state(page)).toEqual(CLOSED)
      else {
        await settle(page)
        expect(await state(page)).toEqual(OPEN)
      }
    })

    test('a tooltip is dismissed by Escape, and only an escapable modal goes with it (a tooltip does not take the press)', async ({ page }) => {
      await go(page, query)
      await show(page)
      await page.locator('[data-tip-trigger]').evaluate((el) => el.focus())
      const tip = page.locator('[data-atom-tooltip-content]')
      await expect(tip).toBeVisible()

      await escape(page)

      if (escapable) await expect.poll(() => state(page)).toEqual(CLOSED)
      else {
        await expect(tip).toBeHidden()
        expect(await state(page)).toEqual(OPEN)
      }
    })

    test('Escape in a select whose list is closed (the select stops the key)', async ({ page }) => {
      await go(page, query)
      await show(page)
      await page.locator('[data-select-wrap] [role="combobox"]').evaluate((el) => el.focus())

      await escape(page, 3)

      if (escapable) {
        await expect(dialog(page)).toBeHidden()
        await expect.poll(() => state(page)).toEqual(CLOSED)
        await expect.poll(() => closedEvents(page)).toEqual(['esc'])
      }
      else {
        await settle(page)
        expect(await state(page)).toEqual(OPEN)
      }
    })

    test('an open select list takes the first Escape, then the modal', async ({ page }) => {
      await go(page, query)
      await show(page)
      await activate(page, '[data-select-wrap] [role="combobox"]')
      const option = page.getByRole('option', { name: 'Sent' })
      await expect(option).toBeVisible()

      await escape(page)
      await expect(option).toBeHidden()
      expect((await state(page)).open).toBe(true)

      await escape(page, 3)

      if (escapable) {
        await expect(dialog(page)).toBeHidden()
        await expect.poll(() => state(page)).toEqual(CLOSED)
        await expect.poll(() => closedEvents(page)).toEqual(['esc'])
      }
      else {
        await settle(page)
        expect(await state(page)).toEqual(OPEN)
      }
    })

    test('a Livewire morph that removes the focused button', async ({ page }) => {
      await go(page, query)
      await show(page)

      await activate(page, '[data-load]')
      await expect(page.locator('[data-loaded]')).toBeAttached()
      await expect(page.locator('[data-load]')).toHaveCount(0)

      await escape(page, 3)

      if (escapable) {
        await expect(dialog(page)).toBeHidden()
        await expect.poll(() => state(page)).toEqual(CLOSED)
        await expect.poll(() => closedEvents(page)).toEqual(['esc'])
      }
      else {
        await settle(page)
        expect(await state(page)).toEqual(OPEN)
      }
    })

    test('the focused button is disabled', async ({ page }) => {
      await go(page, query)
      await show(page)

      await activate(page, '[data-disable]')
      await expect(page.locator('[data-disable]')).toBeDisabled()

      await escape(page, 3)

      if (escapable) {
        await expect(dialog(page)).toBeHidden()
        await expect.poll(() => state(page)).toEqual(CLOSED)
      }
      else {
        await settle(page)
        expect(await state(page)).toEqual(OPEN)
      }
    })

    test('the focused button is hidden', async ({ page }) => {
      await go(page, query)
      await show(page)

      await activate(page, '[data-hide]')
      await expect(page.locator('[data-hide]')).toBeHidden()

      await escape(page, 3)

      if (escapable) {
        await expect(dialog(page)).toBeHidden()
        await expect.poll(() => state(page)).toEqual(CLOSED)
      }
      else {
        await settle(page)
        expect(await state(page)).toEqual(OPEN)
      }
    })

    test('an open dropdown takes the first Escape and the modal stays', async ({ page }) => {
      await go(page, query)
      await show(page)
      await activate(page, '[data-open-dropdown]')
      const item = page.getByRole('menuitem', { name: 'Item one' })
      await expect(item).toBeVisible()
      await blur(page)

      await escape(page)
      await expect(item).toBeHidden()
      expect(await state(page)).toEqual(OPEN)

      if (!escapable) {
        await escape(page, 3)
        await settle(page)
        expect(await state(page)).toEqual(OPEN)
      }
    })
  })
}

test.describe('the modal closed by something other than its own handler', () => {
  test('closeModal() says closed once (the queued native close finds nothing left to do)', async ({ page }) => {
    await go(page)
    await show(page)

    await page.getByRole('button', { name: 'Close', exact: true }).click()
    await expect(dialog(page)).toBeHidden()
    await settle(page)

    await expect.poll(() => state(page)).toEqual(CLOSED)
    await expect.poll(() => closedEvents(page)).toEqual(['esc'])
  })

  test('close() and show() in one tick leave it open, with one closed', async ({ page }) => {
    await go(page)
    await show(page)

    await page.evaluate(() => {
      atom.modal('esc').close()
      atom.modal('esc').show()
    })
    await settle(page)

    expect(await state(page)).toEqual(OPEN)
    await expect.poll(() => closedEvents(page)).toEqual(['esc'])
  })

  test('dialog.close() called directly: clean, closed once, and a later slide() carries only the slide attribute', async ({ page }) => {
    await go(page)
    await show(page)

    await dialog(page).evaluate((el) => el.close())
    await expect.poll(() => state(page)).toEqual(CLOSED)
    await expect.poll(() => closedEvents(page)).toEqual(['esc'])

    await page.evaluate(() => atom.modal('esc').slide())
    expect(await state(page)).toEqual({ open: true, attributes: ['data-atom-modal-slide', 'data-open'] })
  })

  test('<form method="dialog">: clean, closed once, and a later slide() carries only the slide attribute', async ({ page }) => {
    await go(page)
    await show(page)

    await page.locator('[data-submit-dialog]').click()
    await expect(dialog(page)).toBeHidden()
    await expect.poll(() => state(page)).toEqual(CLOSED)
    await expect.poll(() => closedEvents(page)).toEqual(['esc'])

    await page.evaluate(() => atom.modal('esc').slide('left'))
    expect(await state(page)).toEqual({ open: true, attributes: ['data-atom-modal-slide-left', 'data-open'] })
  })

  test('a dropdown toggled inside the modal does not reset it', async ({ page }) => {
    await go(page)
    await show(page)

    const item = page.getByRole('menuitem', { name: 'Item one' })
    await page.locator('[data-open-dropdown]').click()
    await expect(item).toBeVisible()
    await page.locator('[data-open-dropdown]').click()
    await expect(item).toBeHidden()
    await settle(page)

    expect(await state(page)).toEqual(OPEN)
    expect(await closedEvents(page)).toEqual([])
  })

  test('a close event bubbling up from inside is not the dialog\'s own: the state is kept', async ({ page }) => {
    await go(page)
    await show(page)

    // a dropdown's `close` bubbles through the dialog; the dialog's own `close` is queued after dialog.close(), so a handler on any
    // `close` would reset the state in this very tick, ahead of the queue
    const kept = await dialog(page).evaluate((el) => {
      el.close()
      el.querySelector('[data-plain]').dispatchEvent(new Event('close', { bubbles: true }))

      return el.hasAttribute('data-open')
    })
    expect(kept).toBe(true)

    await expect.poll(() => state(page)).toEqual(CLOSED)
    await expect.poll(() => closedEvents(page)).toEqual(['esc'])
  })
})

test.describe('stacked modals', () => {
  // the second is opened by a click inside the first, so the page has had real input between the opens
  const stack = async (page, first, second) => {
    await show(page, first)
    await dialog(page, first).locator('[data-open-other]').click()
    await expect(dialog(page, second)).toBeVisible()
    await blur(page)
  }

  for (const [first, second] of [['outer', 'inner'], ['inner', 'outer']]) {
    test(`both escapable, ${first} then ${second}: one layer per press, each clean`, async ({ page }) => {
      await go(page)
      await stack(page, first, second)

      await escape(page)
      await expect(dialog(page, second)).toBeHidden()
      await expect.poll(() => state(page, second)).toEqual(CLOSED)
      expect(await state(page, first)).toEqual(OPEN)

      await escape(page)
      await expect(dialog(page, first)).toBeHidden()
      await expect.poll(() => state(page, first)).toEqual(CLOSED)
      await expect.poll(async () => (await closedEvents(page)).sort()).toEqual(['inner', 'outer'])
    })
  }

  test('outer holds, inner escapable: inner closes, then outer holds for three presses', async ({ page }) => {
    await go(page, '?outer=0')
    await stack(page, 'outer', 'inner')

    await escape(page)
    await expect(dialog(page, 'inner')).toBeHidden()
    await expect.poll(() => state(page, 'inner')).toEqual(CLOSED)

    await escape(page, 3)
    await settle(page)
    expect(await state(page, 'outer')).toEqual(OPEN)
    await expect.poll(() => closedEvents(page)).toEqual(['inner'])
  })

  test('inner holds over an escapable outer: nothing closes in three presses', async ({ page }) => {
    await go(page, '?inner=0')
    await stack(page, 'outer', 'inner')

    await escape(page, 3)
    await settle(page)
    expect(await state(page, 'inner')).toEqual(OPEN)
    expect(await state(page, 'outer')).toEqual(OPEN)
    expect(await closedEvents(page)).toEqual([])
  })

  test('both hold, in either order: three presses close nothing', async ({ page }) => {
    for (const [first, second] of [['outer', 'inner'], ['inner', 'outer']]) {
      await go(page, '?outer=0&inner=0')
      await stack(page, first, second)

      await escape(page, 3)
      await settle(page)
      expect(await state(page, first)).toEqual(OPEN)
      expect(await state(page, second)).toEqual(OPEN)
      expect(await closedEvents(page)).toEqual([])
    }
  })
})

test.describe('the command palette (#177)', () => {
  for (const focus of ['search', 'menu', 'body']) {
    test(`a dropdown in it with the focus on the ${focus}: it closes alone, then the palette`, async ({ page }) => {
      await go(page)
      await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-command-show', { detail: { name: 'palette' } })))
      const palette = page.locator('[data-palette]')
      await expect(palette).toBeVisible()
      await activate(page, '[data-open-palette-dropdown]')
      const item = page.getByRole('menuitem', { name: 'Palette item' })
      await expect(item).toBeVisible()

      if (focus === 'search') await page.locator('[data-palette] [data-atom-command-search]').evaluate((el) => el.focus())
      if (focus === 'menu') await item.evaluate((el) => el.focus())
      if (focus === 'body') await blur(page)

      await escape(page)
      await expect(item).toBeHidden()
      await expect(palette).toBeVisible()

      await escape(page)
      await expect(palette).toBeHidden()
    })
  }
})

test.describe('the header menu panel under a modal', () => {
  test.use({ viewport: { width: 390, height: 800 } })

  const goHeader = async (page) => {
    const css = await page.request.get('/atom/e2e/consumer-css/latest')
    expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

    await page.goto('/atom/e2e/shell-header-wide?tw=latest&layers=1')
    await page.waitForFunction(() => window.Alpine !== undefined)
  }
  const panelOpen = (page) => page.evaluate(() => document.querySelector('[data-atom-header-nav]').hasAttribute('data-open'))

  test('a dropdown in the modal over the panel: dropdown, then modal, one a press (a click outside the panel closes it, as it does)', async ({ page }) => {
    await goHeader(page)
    await page.locator('[data-atom-header-menu-button]').click()
    await expect.poll(() => panelOpen(page)).toBe(true)
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-modal-show', { detail: { name: 'header-modal' } })))
    const dlg = page.locator('dialog[data-modal]')
    await expect(dlg).toBeVisible()
    await activate(page, '[data-open-modal-dropdown]')
    const item = page.getByRole('menuitem', { name: 'Modal item' })
    await expect(item).toBeVisible()
    await expect.poll(() => panelOpen(page)).toBe(false)

    await escape(page)
    await expect(item).toBeHidden()
    await expect(dlg).toBeVisible()

    await escape(page)
    await expect(dlg).toBeHidden()
  })

  test('the panel open, a modal over it: Escape closes the modal, then the panel', async ({ page }) => {
    await goHeader(page)
    await page.locator('[data-atom-header-menu-button]').click()
    await expect.poll(() => panelOpen(page)).toBe(true)
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-modal-show', { detail: { name: 'header-modal' } })))
    const dlg = page.locator('dialog[data-modal]')
    await expect(dlg).toBeVisible()
    await blur(page)

    await escape(page)
    await expect(dlg).toBeHidden()
    expect(await panelOpen(page)).toBe(true)

    await escape(page)
    await expect.poll(() => panelOpen(page)).toBe(false)
  })
})

test.describe('the sidebar drawer under a modal', () => {
  const goShell = async (page, query = '') => {
    const css = await page.request.get('/atom/e2e/consumer-css/latest')
    expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

    await page.addInitScript(() => {
      window.__closed = []
      document.addEventListener('closed', (e) => window.__closed.push(e.detail))
      try { window.localStorage.removeItem('atom-sidebar-collapsed') } catch {}
    })
    await page.setViewportSize({ width: 390, height: 800 })
    await page.goto(`/atom/e2e/shell-collapse/one?tw=latest&crumbs=1&layers=1${query}`)
    await page.waitForFunction(() => window.Alpine !== undefined)
  }

  const modal = (page) => page.locator('dialog[data-modal]')
  const stashed = (page) => page.evaluate(() => document.body.hasAttribute('data-show-stashed-sidebar'))
  const drawerShown = (page) => page.evaluate(() => {
    const el = document.querySelector('[data-atom-sidebar]')
    const r = el.getBoundingClientRect()

    return getComputedStyle(el).visibility === 'visible' && r.right > 0
  })
  const openBoth = async (page) => {
    await page.locator('[data-atom-sidebar-toggle]').click()
    await expect.poll(() => drawerShown(page)).toBe(true)
    await page.evaluate(() => window.dispatchEvent(new CustomEvent('atom-modal-show', { detail: { name: 'collapse-modal' } })))
    await expect(modal(page)).toBeVisible()
    await blur(page)
  }

  test('escapable, focus on <body>: the first Escape closes the modal and the drawer stays, the second closes the drawer', async ({ page }) => {
    await goShell(page)
    await openBoth(page)

    await escape(page)
    await expect(modal(page)).toBeHidden()
    expect(await stashed(page)).toBe(true)
    expect(await drawerShown(page)).toBe(true)
    expect(await page.evaluate(() => window.__closed)).toEqual(['collapse-modal'])
    expect(await modal(page).evaluate((el) => el.hasAttribute('data-open') || el.hasAttribute('data-atom-modal'))).toBe(false)

    await escape(page)
    expect(await stashed(page)).toBe(false)
    await expect.poll(() => drawerShown(page)).toBe(false)
  })

  test('a dropdown in the modal over the drawer: dropdown, then modal, then drawer, one a press', async ({ page }) => {
    await goShell(page)
    await openBoth(page)
    await activate(page, '[data-open-modal-dropdown]')
    await expect(page.locator('[data-modal] [data-atom-menu]')).toBeVisible()

    await escape(page)
    await expect(page.locator('[data-modal] [data-atom-menu]')).toBeHidden()
    await expect(modal(page)).toBeVisible()
    expect(await stashed(page)).toBe(true)

    await escape(page)
    await expect(modal(page)).toBeHidden()
    expect(await stashed(page)).toBe(true)

    await escape(page)
    expect(await stashed(page)).toBe(false)
  })

  test('not escapable: both hold for three presses', async ({ page }) => {
    await goShell(page, '&modalescape=0')
    await openBoth(page)

    await escape(page, 3)
    await settle(page)

    await expect(modal(page)).toBeVisible()
    expect(await modal(page).evaluate((el) => el.hasAttribute('data-open'))).toBe(true)
    expect(await stashed(page)).toBe(true)
    expect(await drawerShown(page)).toBe(true)
    expect(await page.evaluate(() => window.__closed)).toEqual([])
  })
})
