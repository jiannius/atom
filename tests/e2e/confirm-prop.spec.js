import { test, expect } from '@playwright/test'

// The `confirm` prop of <atom:button> and <atom:menu.item> (tests/Fixtures/ConfirmPropFixture.php), through the click gate in
// resources/js/helpers/confirm-gate.js: it stops the click before any handler, opens the confirm, and clicks the element again once
// the dialog is accepted. The page is on the consumer's Tailwind (latest).

const dialog = (page) => page.locator('dialog[x-data*="atom-confirm"]')
const confirmButton = (page) => dialog(page).locator('button[type="submit"]')
const confirmLabel = (page) => confirmButton(page).locator('[x-text]')
const log = async (page) => JSON.parse(await page.getByTestId('log').textContent())
const clicks = (page) => page.evaluate(() => window.__clicks ?? 0)

const open = async (page) => {
  await page.goto('/atom/e2e/confirm-prop')
  await page.waitForFunction(() => window.Livewire && window.Alpine && window.atom)
}

const asked = async (page, name) => {
  await page.getByTestId(name).click()
  await expect(dialog(page)).toHaveAttribute('data-open', '')
}

const accept = async (page) => {
  await dialog(page).locator('button[type="submit"]').click()
  await expect(dialog(page)).not.toHaveAttribute('data-open', '')
}

const reject = async (page) => {
  await dialog(page).getByRole('button', { name: /^(Cancel|Keep it)$/ }).click()
  await expect(dialog(page)).not.toHaveAttribute('data-open', '')
}

// the Livewire request that ran, so "nothing happened" is not read before a call could have landed
const settled = (page) => page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 400)))

test.describe('the confirm prop: the gate', () => {
  test('a click opens the dialog and runs nothing', async ({ page }) => {
    await open(page)
    await asked(page, 'bare')
    await settled(page)

    expect(await log(page)).toEqual([])
    await expect(dialog(page)).toContainText('Archive invoice?')
    await expect(confirmLabel(page)).toHaveText('Archive invoice')
  })

  test('Cancel does nothing, and the next click asks again', async ({ page }) => {
    await open(page)

    await asked(page, 'bare')
    await reject(page)
    await settled(page)
    expect(await log(page)).toEqual([])

    await asked(page, 'bare')
    await accept(page)
    await expect.poll(() => log(page)).toEqual(['bare'])
  })

  test('Escape does nothing either', async ({ page }) => {
    await open(page)

    await asked(page, 'bare')
    await page.keyboard.press('Escape')
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
    await settled(page)
    expect(await log(page)).toEqual([])

    await asked(page, 'bare')
    await accept(page)
    await expect.poll(() => log(page)).toEqual(['bare'])
  })

  test('Accept runs the wire:click once, with its arguments', async ({ page }) => {
    await open(page)

    await asked(page, 'passphrase')
    await dialog(page).locator('#atom-confirm-passphrase').fill('example.com')
    await accept(page)

    await expect.poll(() => log(page)).toEqual(['pass'])
    await settled(page)
    expect(await log(page)).toEqual(['pass'])
  })

  test('a wire:click expression ($set) runs after Accept, not before', async ({ page }) => {
    await open(page)

    await asked(page, 'set')
    await settled(page)
    await expect(page.getByTestId('value')).toHaveText('')

    await accept(page)
    await expect(page.getByTestId('value')).toHaveText('set')
  })

  test('x-on:click runs once after Accept, not on the first click', async ({ page }) => {
    await open(page)

    await asked(page, 'alpine')
    expect(await clicks(page)).toBe(0)

    await accept(page)
    await expect.poll(() => clicks(page)).toBe(1)
    await settled(page)
    expect(await clicks(page)).toBe(1)
  })

  test('x-on:click does not run after Cancel', async ({ page }) => {
    await open(page)

    await asked(page, 'alpine')
    await reject(page)
    await settled(page)

    expect(await clicks(page)).toBe(0)
  })

  test('an href navigates only after Accept', async ({ page }) => {
    await open(page)

    await asked(page, 'link')
    await settled(page)
    expect(new URL(page.url()).pathname).toBe('/atom/e2e/confirm-prop')

    await dialog(page).locator('button[type="submit"]').click()
    await expect(page).toHaveURL(/\/atom\/e2e\/confirm-prop\/landing$/)
    await expect(page.getByTestId('landing')).toBeVisible()
  })

  test('an href stays put after Cancel', async ({ page }) => {
    await open(page)

    await asked(page, 'link')
    await reject(page)
    await settled(page)

    expect(new URL(page.url()).pathname).toBe('/atom/e2e/confirm-prop')
  })

  test('newtab opens its tab after Accept and not before', async ({ page, context }) => {
    await open(page)
    const pages = []
    context.on('page', (p) => pages.push(p))

    await asked(page, 'newtab')
    await settled(page)
    expect(pages).toHaveLength(0)

    const opened = context.waitForEvent('page')
    await accept(page)
    const tab = await opened

    await tab.waitForLoadState()
    expect(new URL(tab.url()).pathname).toBe('/atom/e2e/confirm-prop/landing')
    expect(new URL(page.url()).pathname).toBe('/atom/e2e/confirm-prop')
  })

  test('a submit button submits the form after Accept, and not on the click', async ({ page }) => {
    await open(page)

    await asked(page, 'submit')
    await settled(page)
    expect(await log(page)).toEqual([])
    await accept(page)

    await expect.poll(() => log(page)).toEqual(['save'])
  })

  test('Enter in the form\'s field is a click on the submit button, so it asks too', async ({ page }) => {
    await open(page)

    await page.getByTestId('field').fill('x')
    await page.getByTestId('field').press('Enter')
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await settled(page)
    expect(await log(page)).toEqual([])

    await accept(page)
    await expect.poll(() => log(page)).toEqual(['save'])
  })

  test('the loading state still applies to the action that runs after Accept', async ({ page }) => {
    await open(page)
    const button = page.getByTestId('slow')

    await asked(page, 'slow')
    await expect(button).not.toHaveClass(/(^|\s)is-loading(\s|$)/)

    await accept(page)
    await expect(button).toHaveClass(/(^|\s)is-loading(\s|$)/)
    await expect.poll(() => log(page)).toEqual(['slow'])
    await expect(button).not.toHaveClass(/(^|\s)is-loading(\s|$)/)
  })

  test('a disabled element never asks, and does nothing', async ({ page }) => {
    await open(page)

    // a click on the button, a click on its label (a child of a disabled button still sends its click to the window), and a script's .click()
    await page.getByTestId('disabled').click({ force: true })
    await page.getByTestId('disabled').locator('[data-atom-button-label]').dispatchEvent('click')
    await page.getByTestId('disabled').evaluate((el) => el.click())
    await settled(page)

    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
    expect(await log(page)).toEqual([])
  })

  test('a button with a tooltip asks and runs too', async ({ page }) => {
    await open(page)

    await asked(page, 'tooltip')
    await accept(page)

    await expect.poll(() => log(page)).toEqual(['tooltip'])
  })

  test('the options of an array reach the dialog', async ({ page }) => {
    await open(page)

    await asked(page, 'array')
    await expect(dialog(page)).toContainText('Archive this client?')
    await expect(dialog(page)).toContainText('It leaves every list; its records stay.')
    await expect(dialog(page).getByRole('button', { name: 'Keep it' })).toBeVisible()
    await expect(confirmLabel(page)).toHaveText('Archive client')
    // the tone of variant="warning"
    await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-warning-fg/)
  })

  test('a passphrase in the array refuses the wrong text, and the action waits', async ({ page }) => {
    await open(page)

    await asked(page, 'passphrase')
    await dialog(page).locator('#atom-confirm-passphrase').fill('nope')
    await dialog(page).locator('button[type="submit"]').click()

    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await expect(dialog(page).locator('#atom-confirm-passphrase-error')).toBeVisible()
    await settled(page)
    expect(await log(page)).toEqual([])

    await dialog(page).locator('#atom-confirm-passphrase').fill('example.com')
    await accept(page)
    await expect.poll(() => log(page)).toEqual(['pass'])
  })
})

test.describe('the confirm prop: what was typed', () => {
  test('the element hears `confirmed` with the reason, before its action runs', async ({ page }) => {
    await open(page)

    await asked(page, 'reason')
    await dialog(page).locator('#atom-confirm-reason').fill('Duplicate')
    await accept(page)

    await expect.poll(() => log(page)).toEqual(['reason'])
    expect(await page.evaluate(() => window.__confirmed)).toEqual({ password: '', passphrase: '', reason: 'Duplicate' })
  })
})

const here = (page) => new URL(page.url()).pathname === '/atom/e2e/confirm-prop'

// Livewire navigates a `wire:navigate` link on mousedown and mouseup, and on Enter, without waiting for the click
test.describe('the confirm prop: a wire:navigate link', () => {
  for (const testid of ['nav', 'nav-delete']) {
    test(`${testid}: a mouse click asks first, and navigates only after Accept`, async ({ page }) => {
      await open(page)

      await asked(page, testid)
      await settled(page)
      expect(here(page)).toBe(true)

      await dialog(page).locator('button[type="submit"]').click()
      await expect(page).toHaveURL(/\/atom\/e2e\/confirm-prop\/landing$/)
      await expect(page.getByTestId('landing')).toBeVisible()
    })

    test(`${testid}: Cancel after a mouse click stays on the page`, async ({ page }) => {
      await open(page)

      await asked(page, testid)
      await reject(page)
      await settled(page)

      expect(here(page)).toBe(true)
    })

    test(`${testid}: Enter asks first, and navigates only after Accept`, async ({ page }) => {
      await open(page)

      await page.getByTestId(testid).focus()
      await page.keyboard.press('Enter')
      await expect(dialog(page)).toHaveAttribute('data-open', '')
      await settled(page)
      expect(here(page)).toBe(true)

      await dialog(page).locator('button[type="submit"]').click()
      await expect(page).toHaveURL(/\/atom\/e2e\/confirm-prop\/landing$/)
    })

    test(`${testid}: Cancel after Enter stays on the page`, async ({ page }) => {
      await open(page)

      await page.getByTestId(testid).focus()
      await page.keyboard.press('Enter')
      await expect(dialog(page)).toHaveAttribute('data-open', '')
      await reject(page)
      await settled(page)

      expect(here(page)).toBe(true)
    })
  }
})

// A new tab by the browser's own gestures is a click with a modifier or a middle click: both are stopped and asked about
// (Control with a click is a right click on a Mac: no click reaches the page, so it is not driven here)
test.describe('the confirm prop: a link opened in a new tab', () => {
  const gesture = {
    'a middle click': (locator) => locator.click({ button: 'middle' }),
    'a Meta click': (locator) => locator.click({ modifiers: ['Meta'] }),
    'a Shift click': (locator) => locator.click({ modifiers: ['Shift'] }),
  }

  for (const [name, press] of Object.entries(gesture)) {
    test(`${name} on a link asks, opens nothing before Accept, and opens a new tab after`, async ({ page, context }) => {
      await open(page)
      const pages = []
      context.on('page', (p) => pages.push(p))

      await press(page.getByTestId('link'))
      await expect(dialog(page)).toHaveAttribute('data-open', '')
      await settled(page)
      expect(pages).toHaveLength(0)
      expect(here(page)).toBe(true)

      const opened = context.waitForEvent('page')
      await dialog(page).locator('button[type="submit"]').click()
      const tab = await opened

      await tab.waitForLoadState()
      expect(new URL(tab.url()).pathname).toBe('/atom/e2e/confirm-prop/landing')
      expect(here(page)).toBe(true)
    })

    test(`${name} on a link and Cancel open nothing`, async ({ page, context }) => {
      await open(page)
      const pages = []
      context.on('page', (p) => pages.push(p))

      await press(page.getByTestId('link'))
      await expect(dialog(page)).toHaveAttribute('data-open', '')
      await reject(page)
      await settled(page)

      expect(pages).toHaveLength(0)
      expect(here(page)).toBe(true)
    })
  }

  test('a middle click on a wire:navigate link opens a new tab after Accept, and this page stays', async ({ page, context }) => {
    await open(page)

    await page.getByTestId('nav').click({ button: 'middle' })
    await expect(dialog(page)).toHaveAttribute('data-open', '')

    const opened = context.waitForEvent('page')
    await dialog(page).locator('button[type="submit"]').click()
    const tab = await opened

    await tab.waitForLoadState()
    expect(new URL(tab.url()).pathname).toBe('/atom/e2e/confirm-prop/landing')
    expect(here(page)).toBe(true)
  })
})

test.describe('the confirm prop: inside <atom:confirm.trigger>', () => {
  for (const [testid, call] of [['in-trigger', 'in-trigger'], ['in-trigger-delete', 'in-trigger-delete']]) {
    test(`${testid}: one dialog, and the action runs once after it`, async ({ page }) => {
      await open(page)

      await asked(page, testid)
      await settled(page)
      expect(await log(page)).toEqual([])
      await accept(page)

      await expect.poll(() => log(page)).toEqual([call])
      // a second dialog would be open by now
      await settled(page)
      await expect(dialog(page)).not.toHaveAttribute('data-open', '')
      expect(await log(page)).toEqual([call])
    })
  }

  test('a plain button in a trigger still asks through the trigger', async ({ page }) => {
    await open(page)

    await asked(page, 'plain-trigger')
    await expect(dialog(page)).toContainText('Plain trigger?')
  })
})

test.describe('the confirm prop: the markup is not trusted', () => {
  test('only the dialog options are read: html and anything else in the JSON never reach the dialog', async ({ page }) => {
    await open(page)

    await page.getByTestId('bare').evaluate((el) => el.setAttribute('data-atom-confirm', JSON.stringify({
      heading: 'Hostile?', html: '<img src="x" data-pwn>', onAccepted: 'deleteDomain', wireId: 'x', buttonConfirm: 'Go',
    })))
    await asked(page, 'bare')

    await expect(dialog(page)).toContainText('Hostile?')
    await expect(confirmLabel(page)).toHaveText('Go')
    await expect(dialog(page).locator('[data-pwn]')).toHaveCount(0)
    await expect(dialog(page).locator('img')).toHaveCount(0)
  })

  test('an array or a string in place of the options asks nothing and runs nothing', async ({ page }) => {
    await open(page)

    for (const value of ['[1,2]', '"text"', 'null', '{broken']) {
      await page.getByTestId('bare').evaluate((el, v) => el.setAttribute('data-atom-confirm', v), value)
      await page.getByTestId('bare').click()
      await settled(page)

      await expect(dialog(page)).not.toHaveAttribute('data-open', '')
      expect(await log(page)).toEqual([])
    }
  })
})

test.describe('the confirm prop: the keyboard', () => {
  for (const key of ['Enter', 'Space']) {
    test(`${key} on the focused element asks, and Accept with ${key} runs it`, async ({ page }) => {
      await open(page)

      await page.getByTestId('bare').focus()
      await page.keyboard.press(key)
      await expect(dialog(page)).toHaveAttribute('data-open', '')
      await settled(page)
      expect(await log(page)).toEqual([])

      // the dialog's buttons are in the keyboard's reach: Tab from the first focus to the confirm button
      await dialog(page).locator('button[type="submit"]').focus()
      await page.keyboard.press(key)
      await expect(dialog(page)).not.toHaveAttribute('data-open', '')
      await expect.poll(() => log(page)).toEqual(['bare'])
    })

    test(`${key} on a link asks before it navigates`, async ({ page }) => {
      test.skip(key === 'Space', 'Space does not activate a link')
      await open(page)

      await page.getByTestId('link').focus()
      await page.keyboard.press(key)
      await expect(dialog(page)).toHaveAttribute('data-open', '')
      await settled(page)
      expect(new URL(page.url()).pathname).toBe('/atom/e2e/confirm-prop')
    })
  }

  test('focus comes back to the trigger after Cancel', async ({ page }) => {
    await open(page)

    await page.getByTestId('bare').focus()
    await page.keyboard.press('Enter')
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await reject(page)

    await expect(page.getByTestId('bare')).toBeFocused()
  })
})

test.describe('the confirm prop: a menu item', () => {
  const menu = (page) => page.getByTestId('more').locator('[data-atom-menu]')

  const openMenu = async (page) => {
    await page.getByTestId('more-trigger').click()
    await expect(menu(page)).toBeVisible()
    await expect.poll(() => menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
  }

  test('opening the confirm closes the open menu, and draws the warning dialog', async ({ page }) => {
    await open(page)
    await openMenu(page)

    await page.getByTestId('item-warning').click()
    await expect(dialog(page)).toHaveAttribute('data-open', '')

    expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(false)
    await expect(page.getByTestId('more-trigger')).toHaveAttribute('aria-expanded', 'false')
    await expect(dialog(page)).toContainText('Cancel this job?')
    await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-warning-fg/)
    await expect(confirmLabel(page)).toHaveText('Cancel job')
    expect(await log(page)).toEqual([])
  })

  test('Accept runs the item once; the menu stays closed', async ({ page }) => {
    await open(page)
    await openMenu(page)

    await page.getByTestId('item-warning').click()
    await accept(page)

    await expect.poll(() => log(page)).toEqual(['cancel-job'])
    expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(false)
  })

  test('Cancel runs nothing, and focus goes back to the trigger', async ({ page }) => {
    await open(page)
    await openMenu(page)

    await page.getByTestId('item-warning').click()
    await reject(page)
    await settled(page)

    expect(await log(page)).toEqual([])
    await expect(page.getByTestId('more-trigger')).toBeFocused()
  })

  // The browser returns the focus to the element the click was on, which sat in a menu that is closed now, and Safari never focused it:
  // the gate puts it back on the dropdown's trigger (helpers/confirm-gate.js `returnFocus()`)
  test('Escape runs nothing, and focus goes back to the trigger', async ({ page }) => {
    await open(page)
    await openMenu(page)

    await page.getByTestId('item-warning').click()
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await page.keyboard.press('Escape')
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
    await settled(page)

    expect(await log(page)).toEqual([])
    await expect(page.getByTestId('more-trigger')).toBeFocused()
  })

  test('Cancel leaves the focus alone when something already has it', async ({ page }) => {
    await open(page)
    await openMenu(page)

    await page.getByTestId('item-warning').click()
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await page.getByTestId('bare').evaluate((el) => {
      let dlg = document.querySelector('dialog[x-data*="atom-confirm"]')

      dlg.addEventListener('close', () => el.focus(), { once: true })
    })
    await reject(page)
    await settled(page)

    await expect(page.getByTestId('bare')).toBeFocused()
  })

  test('a plain item still runs at once and closes the menu', async ({ page }) => {
    await open(page)
    await openMenu(page)

    await page.getByTestId('item-plain').click()

    await expect.poll(() => log(page)).toEqual(['plain-item'])
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
    expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(false)
  })

  test('the keyboard: Enter on an item of the open menu asks', async ({ page }) => {
    await open(page)
    await page.getByTestId('more-trigger').focus()
    await page.keyboard.press('Enter')
    await expect(menu(page)).toBeVisible()

    await page.getByTestId('item-warning').focus()
    await page.keyboard.press('Enter')
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await settled(page)
    expect(await log(page)).toEqual([])
    expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(false)
  })

  test('a delete item with its own wire:click asks in danger, then runs it', async ({ page }) => {
    await open(page)
    await openMenu(page)

    await page.getByTestId('item-delete').click()
    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await expect(dialog(page)).toContainText('Delete this record?')
    await expect(dialog(page)).toContainText("This can't be undone.")
    await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-danger-fg/)
    await expect(confirmLabel(page)).toHaveText('Delete invoice')
    expect(await log(page)).toEqual([])

    await accept(page)
    await expect.poll(() => log(page)).toEqual(['item-delete'])
  })
})

test.describe('the confirm prop: deletes', () => {
  test('type="delete" with a wire:click asks first (Q14), then runs it once', async ({ page }) => {
    await open(page)

    await asked(page, 'delete-handler')
    await expect(dialog(page)).toContainText('Delete this record?')
    await settled(page)
    expect(await log(page)).toEqual([])

    await accept(page)
    await expect.poll(() => log(page)).toEqual(['delete-handler'])
    await settled(page)
    expect(await log(page)).toEqual(['delete-handler'])
  })

  test(':confirm="false" runs the wire:click at once', async ({ page }) => {
    await open(page)

    await page.getByTestId('delete-optout').click()

    await expect.poll(() => log(page)).toEqual(['delete-optout'])
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
  })

  test('a delete with no action of its own asks once, then calls delete()', async ({ page }) => {
    await open(page)

    await asked(page, 'delete-bare')
    await settled(page)
    expect(await log(page)).toEqual([])

    await accept(page)
    await expect.poll(() => log(page)).toEqual(['delete'])
    await settled(page)
    expect(await log(page)).toEqual(['delete'])
  })

  test('a delete with no action and a `confirm` copy asks once with that copy', async ({ page }) => {
    await open(page)

    await asked(page, 'delete-copy')
    await expect(dialog(page)).toContainText('Really delete it?')
    await accept(page)

    await expect.poll(() => log(page)).toEqual(['delete'])
  })

  test('a delete with no action that opts out calls delete() at once', async ({ page }) => {
    await open(page)

    await page.getByTestId('delete-bare-optout').click()

    await expect.poll(() => log(page)).toEqual(['delete'])
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
  })
})

test.describe('the confirm prop: a morph', () => {
  test('a re-render while the dialog is open does not run the action or lose it', async ({ page }) => {
    await open(page)

    await asked(page, 'bare')
    // a Livewire update lands under the open dialog: the button is patched in place
    await page.evaluate(() => window.Livewire.first().call('bump'))
    await expect(page.getByTestId('renders')).toHaveText('1')
    expect(await log(page)).toEqual([])

    await accept(page)
    await expect.poll(() => log(page)).toEqual(['bare'])
  })

  test('the dialog reads the copy at click time', async ({ page }) => {
    await open(page)

    await page.getByTestId('bare').evaluate((el) => el.setAttribute('data-atom-confirm', JSON.stringify({ heading: 'Rewritten by a morph?' })))
    await asked(page, 'bare')

    await expect(dialog(page)).toContainText('Rewritten by a morph?')
  })

  test('an element a morph removed while the dialog was open is not clicked', async ({ page }) => {
    await open(page)

    await asked(page, 'bare')
    // a listener of its own: Livewire and Alpine drop theirs when the node goes, a plain one stays on the detached node
    await page.getByTestId('bare').evaluate((el) => { window.__raw = 0; el.addEventListener('click', () => window.__raw++); el.remove() })
    await accept(page)
    await settled(page)

    expect(await log(page)).toEqual([])
    expect(await page.evaluate(() => window.__raw)).toBe(0)
  })
})
