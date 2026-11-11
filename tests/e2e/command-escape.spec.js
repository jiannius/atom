import { test, expect } from '@playwright/test'

// Escape and the command palette (#176). A <dialog> that the browser closes itself (Escape pressed with the focus on <body>, because a
// Tab walked off the end; a control in the slot that stops the key; `close()`, `requestClose()`,
// `<form method="dialog">`, `<button command="close">`) used to leave `data-open`, Alpine's `open` and the search text behind, and
// `data-open` kept `display: flex` on the closed dialog: the palette stayed painted with no backdrop and the page behind was not blocked.
// The layout is keyed on `[open]` now and a `close` listener resets the rest. On the consumer Tailwind build (`node tests/consumer/build.mjs`
// first): the rule that matters is a utility, and the rig has no Tailwind of its own.
//
// Fixtures: /atom/e2e/layers (palette `cmd`, 3 items), /atom/e2e/dropdown-morph (palette `morph-palette` in a Livewire component, `bump()`
// re-renders it), the docs page for the real shortcut, and the sidebar shell for the drawer. `page.evaluate` counts as user activation in
// Chromium, so where Escape is pressed more than once nothing evaluates in between.

const P = 'dialog[data-atom-command]'
const TW = 'latest'

const layers = async (page, tw = TW) => {
  await page.goto(`/atom/e2e/layers?tw=${tw}&section=command`)
  await page.waitForFunction(() => window.Alpine !== undefined)
}
const withTailwind = async (page) => {
  await page.addStyleTag({ url: `/atom/e2e/consumer-css/${TW}` })
  await page.waitForFunction(() => window.Alpine !== undefined)
}
const docs = async (page) => {
  await page.goto('/atom/docs/command')
  await withTailwind(page)
}
const morphPage = async (page) => {
  await page.goto('/atom/e2e/dropdown-morph')
  await withTailwind(page)
  await page.waitForFunction(() => window.Livewire !== undefined)
}

const show = async (page, name) => {
  await page.evaluate((n) => window.dispatchEvent(new CustomEvent('atom-command-show', { detail: { name: n } })), name)
  await page.waitForFunction(() => document.activeElement?.hasAttribute('data-atom-command-search'))
}
const bump = async (page) => {
  await page.evaluate(() => window.Livewire.first().call('bump'))
  await page.waitForTimeout(500)
}
// the browser closes a dialog in the key's own task, but its `close` event is queued
const settle = (page) => page.waitForTimeout(150)

const state = (page) => page.evaluate((sel) => {
  const d = document.querySelector(sel)
  const a = document.activeElement

  return {
    open: d.open,
    dataOpen: d.hasAttribute('data-open'),
    alpineOpen: window.Alpine.$data(d).open,
    text: window.Alpine.$data(d).text,
    display: getComputedStyle(d).display,
    modal: d.matches(':modal'),
    focus: a === document.body ? 'body' : a.hasAttribute('data-atom-command-search') ? 'search' : 'other',
  }
}, P)
const visibleItems = (page) => page.locator(P).locator('[data-atom-command-item]:not([hidden])').count()

const expectClean = async (page) => {
  await expect(page.locator(P)).toBeHidden()
  // the browser's `close` event is queued behind the key, so the reset is polled for
  await expect.poll(() => state(page)).toMatchObject({ open: false, dataOpen: false, alpineOpen: false, text: '', display: 'none', modal: false })
}
const expectOpen = async (page, extra = {}) => {
  expect(await state(page)).toMatchObject({ open: true, dataOpen: true, alpineOpen: true, display: 'flex', modal: true, ...extra })
}
// the palette opens again and closes with one Escape: nothing the stale state left behind gets in the way
const reopens = async (page, name) => {
  await show(page, name)
  await settle(page)
  await expectOpen(page, { text: '', focus: 'search' })
  await page.keyboard.press('Escape')
  await settle(page)
  await expectClean(page)
}

for (const tw of ['latest', 'floor']) {
  test(`Escape with the focus on <body> closes the palette (consumer Tailwind ${tw})`, async ({ page }) => {
    await layers(page, tw)
    await show(page, 'cmd')
    await page.keyboard.type('set')
    await expect.poll(() => visibleItems(page)).toBe(1)
    await page.evaluate(() => document.activeElement.blur())
    await page.keyboard.press('Escape')
    await expect(page.locator(P)).toBeHidden()
    await settle(page)
    await expectClean(page)

    // reopened, the list is whole again and the search empty
    await show(page, 'cmd')
    await settle(page)
    await expectOpen(page, { text: '', focus: 'search' })
    expect(await visibleItems(page)).toBe(3)
    expect(await page.locator(P).locator('[data-atom-command-search]').inputValue()).toBe('')
    await page.keyboard.press('Escape')
    await settle(page)
    await expectClean(page)
  })
}

test('Tab to the highlighted item, a morph patches it, Escape', async ({ page }) => {
  await morphPage(page)
  await show(page, 'morph-palette')
  await settle(page)
  await page.keyboard.press('Tab')
  await bump(page)
  await page.keyboard.press('Escape')
  await settle(page)
  await expectClean(page)
  await reopens(page, 'morph-palette')

  // a morph on the closed palette brings nothing back
  await bump(page)
  await expectClean(page)
})

test('Tab off the end, Escape, then the shortcut opens on one press and closes on the next', async ({ page }) => {
  await docs(page)
  await page.keyboard.press('Meta+k')
  await page.locator(`${P} [data-atom-command-search]`).waitFor({ state: 'visible' })
  await page.waitForFunction(() => document.activeElement?.hasAttribute('data-atom-command-search'))
  for (let i = 0; i < 12; i++) {
    await page.keyboard.press('Tab')
    if ((await state(page)).focus === 'body') break
  }
  expect((await state(page)).focus).toBe('body')
  await page.keyboard.press('Escape')
  await settle(page)
  await expectClean(page)

  await page.keyboard.press('Meta+k')
  await settle(page)
  await expectOpen(page, { text: '', focus: 'search' })
  await page.keyboard.press('Meta+k')
  await settle(page)
  await expectClean(page)
})

for (const [name, run] of [
  ['close()', (page) => page.evaluate((sel) => document.querySelector(sel).close(), P)],
  ['requestClose()', (page) => page.evaluate((sel) => document.querySelector(sel).requestClose(), P)],
  ['<form method="dialog">', async (page) => {
    await page.evaluate((sel) => {
      const f = document.createElement('form')
      f.method = 'dialog'
      f.innerHTML = '<button id="cmd-esc-form" type="submit">x</button>'
      document.querySelector(sel).querySelector('[data-atom-command-list]').prepend(f)
    }, P)
    await page.locator('#cmd-esc-form').click()
  }],
  ['<button command="close">', async (page) => {
    await page.evaluate((sel) => {
      const d = document.querySelector(sel)
      const b = document.createElement('button')
      d.id = 'cmd-esc-dialog'
      b.id = 'cmd-esc-button'
      b.type = 'button'
      b.setAttribute('commandfor', 'cmd-esc-dialog')
      b.setAttribute('command', 'close')
      b.textContent = 'x'
      d.querySelector('[data-atom-command-list]').prepend(b)
    }, P)
    await page.locator('#cmd-esc-button').click()
  }],
  ['an inner control that stops Escape', async (page) => {
    await page.evaluate((sel) => {
      const i = document.createElement('input')
      i.addEventListener('keydown', (e) => { if (e.key === 'Escape') e.stopPropagation() })
      document.querySelector(sel).querySelector('[data-atom-command-list]').prepend(i)
      i.focus()
    }, P)
    await page.keyboard.press('Escape')
  }],
]) {
  test(`a native close by ${name} resets the palette`, async ({ page }) => {
    await layers(page)
    await show(page, 'cmd')
    await page.keyboard.type('set')
    await settle(page)
    await run(page)
    await settle(page)
    await expectClean(page)
    await reopens(page, 'cmd')
  })
}

test('a natively closed palette is not painted while its close event is still queued', async ({ page }) => {
  await layers(page)
  await show(page, 'cmd')
  await settle(page)
  // `close` is queued, so `data-open` is still on in the same task: the layout must not hang on it (`flex` on `data-open` beat the UA's `display: none`)
  const during = await page.evaluate((sel) => {
    const d = document.querySelector(sel)
    d.close()

    return { dataOpen: d.hasAttribute('data-open'), display: getComputedStyle(d).display }
  }, P)
  expect(during).toEqual({ dataOpen: true, display: 'none' })
  await settle(page)
  await expectClean(page)
})

test('a close and a reopen in one tick stays open, by event and by a native close()', async ({ page }) => {
  await layers(page)
  await show(page, 'cmd')
  await settle(page)
  await page.evaluate(() => { atom.command('cmd').close(); atom.command('cmd').show() })
  await settle(page)
  await expectOpen(page)

  await page.evaluate((sel) => { document.querySelector(sel).close(); atom.command('cmd').show() }, P)
  await settle(page)
  await expectOpen(page)
})

test('a close that bubbles up from inside leaves an open palette alone', async ({ page }) => {
  await layers(page)
  await show(page, 'cmd')
  await page.keyboard.type('set')
  await settle(page)
  await page.evaluate((sel) => document.querySelector(sel).querySelector('[data-atom-command-list]').dispatchEvent(new CustomEvent('close', { bubbles: true })), P)
  await settle(page)
  await expectOpen(page, { text: 'set' })
})

test('a morph keeps an open palette filtered, and a Livewire close is clean', async ({ page }) => {
  await morphPage(page)
  await show(page, 'morph-palette')
  await page.keyboard.type('ing')
  await settle(page)
  await bump(page)
  await expectOpen(page, { text: 'ing', focus: 'search' })
  expect(await visibleItems(page)).toBe(2)

  await page.evaluate(() => window.Livewire.first().dispatch('atom-command-close', { name: 'morph-palette' }))
  await settle(page)
  await expectClean(page)
})

test('the sidebar drawer under a palette: the first Escape takes the palette, the second the drawer', async ({ page }) => {
  await page.addInitScript(() => { try { window.localStorage.removeItem('atom-sidebar-collapsed') } catch {} })
  await page.setViewportSize({ width: 390, height: 800 })
  await page.goto('/atom/e2e/shell-collapse/one?tw=latest&crumbs=1')
  await page.waitForFunction(() => window.Alpine !== undefined)
  // the real palette markup, at body level as a host puts it (the shell fixture has none)
  await page.evaluate(async () => {
    const html = await (await fetch('/atom/e2e/layers?tw=latest&section=command')).text()
    const palette = new DOMParser().parseFromString(html, 'text/html').querySelector('dialog[data-atom-command]')
    document.body.insertAdjacentHTML('beforeend', palette.outerHTML)
  })
  await page.waitForFunction(() => window.Alpine.$data(document.querySelector('dialog[data-atom-command]'))?.showCommand)
  await page.locator('[data-atom-sidebar-toggle]').click()
  await settle(page)
  const drawerOpen = () => page.evaluate(() => document.body.hasAttribute('data-show-stashed-sidebar'))
  expect(await drawerOpen()).toBe(true)

  await show(page, 'cmd')
  await settle(page)
  await page.evaluate(() => document.activeElement.blur())
  await page.keyboard.press('Escape')
  await settle(page)
  expect(await drawerOpen()).toBe(true)
  await expectClean(page)

  await page.keyboard.press('Escape')
  await settle(page)
  expect(await drawerOpen()).toBe(false)
})
