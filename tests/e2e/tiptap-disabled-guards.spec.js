import { test, expect } from '@playwright/test'
import { caseOf, openCase, observe, root, flip, serverState } from './disabled-helpers.js'

// The tiptap editor and the chat composer block a disabled state by more than what the eye or a CSS class can see (the contract's second rule): a node a
// utility hides, a menu the editor shows on its own, or a gesture the rig's mouse cannot make, still reaches the handler. This spec calls the handlers and
// the browser's own events directly and asserts nothing changes, with the same call made enabled first so it is proven to work. Run `node tests/consumer/build.mjs` first.

const ROUTES = ['prop', 'form']

const DOC = JSON.stringify({
  type: 'doc',
  content: [
    { type: 'paragraph', content: [{ type: 'text', text: 'go there', marks: [{ type: 'link', attrs: { href: '/atom/docs', target: '_blank' } }] }] },
    { type: 'table', content: [{ type: 'tableRow', content: [{ type: 'tableCell', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'cell' }] }] }] }] },
    { type: 'paragraph', content: [{ type: 'image', attrs: { src: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="red"/></svg>' } }] },
    { type: 'paragraph', content: [{ type: 'text', text: 'end' }] },
  ],
})

const data = (page, def, fn, arg) => root(page, def).locator('[data-atom-tiptap]').evaluate((el, [src, a]) => new Function('data', 'a', `return (${src})(data, a)`)(window.Alpine.$data(el), a), [fn.toString(), arg])
const html = (page, def) => data(page, def, (d) => d.editor().getHTML())

for (const route of ROUTES) {
  test(`${route}: tiptap: a disabled editor is a non-editable textbox with no toolbar and no menu, and an enabled one is none of that`, async ({ page }) => {
    const def = caseOf('tiptap')
    const surface = () => root(page, def).locator('.ProseMirror')
    const marked = route === 'prop' ? 'self' : 'inherited'

    await openCase(page, def, { route, off: true })
    await expect(surface()).toHaveAttribute('contenteditable', 'false')
    await expect(surface()).toHaveAttribute('role', 'textbox')
    await expect(surface()).toHaveAttribute(route === 'prop' ? 'aria-disabled' : 'aria-readonly', 'true')
    await expect(surface()).not.toHaveAttribute(route === 'prop' ? 'aria-readonly' : 'aria-disabled', /.*/)
    await expect(root(page, def).locator('[data-atom-tiptap]')).toHaveAttribute('data-atom-disabled', marked)
    await expect(root(page, def).locator('[role="toolbar"]')).toBeHidden()
    await expect(root(page, def).locator('.tiptap-menu')).toBeHidden()

    const drop = () => root(page, def).locator('[x-ref="editor"]').evaluate((el) => {
      const dt = new DataTransfer()
      dt.items.add(new File(['abc'], 'a.txt', { type: 'text/plain' }))
      const e = new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true })
      el.dispatchEvent(e)
      return e.defaultPrevented
    })
    expect(await drop(), 'a file dropped on a disabled editor is cancelled, so it cannot navigate the page away').toBe(true)

    await openCase(page, def, { route })
    expect(await drop(), 'enabled: the editor handles a drop itself').toBe(false)
    await expect(surface()).toHaveAttribute('contenteditable', 'true')
    await expect(surface()).not.toHaveAttribute('aria-disabled', /.*/)
    await expect(surface()).not.toHaveAttribute('aria-readonly', /.*/)
    await expect(root(page, def).locator('[role="toolbar"]')).toBeVisible()
  })

  test(`${route}: tiptap: a morph that disables the editor takes its caret, toolbar and menus away, and the way back gives them back`, async ({ page }) => {
    const def = caseOf('tiptap')
    const surface = () => root(page, def).locator('.ProseMirror')

    await openCase(page, def, { route, value: DOC })
    // the document starts in a link, so the link menu is up on load with nothing focused: a morph that disables the editor has to close it (a focused menu closes on blur)
    const linkMenu = () => root(page, def).evaluate((el) => [...el.querySelectorAll('.link-menu')].some((m) => m.textContent.includes('/atom/docs') && m.getBoundingClientRect().height > 0 && getComputedStyle(m).visibility === 'visible'))
    await expect.poll(linkMenu, { message: 'enabled: the link menu shows on load' }).toBe(true)
    await flip(page)
    await expect.poll(linkMenu, { message: 'the link menu that was showing is gone' }).toBe(false)
    await flip(page)
    await surface().locator('td p').click()
    await expect(root(page, def).locator('.table-menu')).toBeVisible()

    await flip(page)
    await expect(surface()).toHaveAttribute('contenteditable', 'false')
    await expect(surface()).toHaveAttribute(route === 'prop' ? 'aria-disabled' : 'aria-readonly', 'true')
    await expect(root(page, def).locator('[role="toolbar"]')).toBeHidden()
    expect(await surface().evaluate((el) => el === document.activeElement), 'the editor gave up the focus').toBe(false)
    await expect(root(page, def).locator('.table-menu'), 'a bubble menu that was showing is gone').toBeHidden()

    await flip(page)
    await expect(surface()).toHaveAttribute('contenteditable', 'true')
    await expect(surface()).not.toHaveAttribute('aria-disabled', /.*/)
    await expect(surface()).not.toHaveAttribute('aria-readonly', /.*/)
    await expect(root(page, def).locator('[role="toolbar"]')).toBeVisible()
    await surface().locator('p').last().click()
    await page.keyboard.type('zq7')
    await expect(surface()).toContainText('zq7')
    expect(page.errors).toEqual([])
  })

  test(`${route}: tiptap: a flip sends no update of its own and leaves the server's value alone`, async ({ page }) => {
    const def = caseOf('tiptap')

    await openCase(page, def, { route })
    const initial = (await serverState(page)).v
    page.updates.length = 0

    await flip(page)
    await page.waitForTimeout(600)
    expect(page.updates.length, 'one update: the flip itself').toBe(1)
    await flip(page)
    await page.waitForTimeout(600)
    expect(page.updates.length, 'two updates: the flip back').toBe(2)
    expect((await serverState(page)).v).toBe(initial)
    expect(page.errors).toEqual([])
  })

  test(`${route}: tiptap: toolbar buttons, the link menu and sync() change nothing when disabled, however they are reached`, async ({ page }) => {
    const def = caseOf('tiptap')
    // the buttons are hidden when disabled; a DOM click on a hidden node, a handler called by name, and the menu's own data are how a script reaches them
    const act = () => root(page, def).locator('[data-atom-tiptap]').evaluate((el) => {
      const d = window.Alpine.$data(el)
      const seen = []
      // the bubble menu moves out of `.tiptap-menu` when it shows (a document that starts in a link shows it on load): it is the last one in the editor
      const menu = window.Alpine.$data([...el.querySelectorAll('.link-menu')].pop())
      d.editor().commands.setTextSelection(2)
      menu.href = 'https://changed.test/'
      menu.save()
      seen.push(d.editor().getHTML())
      menu.remove()
      seen.push(d.editor().getHTML())
      el.querySelector('button[aria-label="Horizontal Rule"]').click()
      seen.push(d.editor().getHTML())
      d.commands().setContent('<p>replaced</p>')
      d.sync()
      seen.push(d.editor().getHTML())
      return seen
    })

    await openCase(page, def, { route, value: DOC })
    const [relinked, unlinked, ruled, replaced] = await act()
    expect(relinked, 'enabled: the link menu saves a new address').toContain('href="https://changed.test/"')
    expect(unlinked, 'enabled: the link menu removes the link').not.toContain('href=')
    expect(ruled, 'enabled: the toolbar button inserts a rule').toContain('<hr')
    expect(replaced, 'enabled: commands() replaces the content').toBe('<p>replaced</p>')

    await openCase(page, def, { route, off: true, value: DOC })
    const before = await html(page, def)
    const after = await act()
    await page.waitForTimeout(300)
    expect(after).toEqual([before, before, before, before])
    expect(before).toContain('href="/atom/docs"')
    expect((await serverState(page)).v, 'the model').toBe(DOC)
    expect(page.errors).toEqual([])
  })

  test(`${route}: tiptap: the toolbar's image input uploads nothing when disabled`, async ({ page }) => {
    const def = caseOf('tiptap')
    const png = { name: 'a.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') }
    let uploads = 0
    page.on('request', (r) => { if (/upload-file/.test(r.url())) uploads++ })

    await openCase(page, def, { route })
    await root(page, def).locator('input[type=file][accept="image/*"]').setInputFiles(png)
    await expect.poll(() => uploads, { message: 'enabled: choosing an image uploads it' }).toBeGreaterThan(0)

    uploads = 0
    await openCase(page, def, { route, off: true })
    await root(page, def).locator('input[type=file][accept="image/*"]').setInputFiles(png)
    await page.waitForTimeout(800)
    expect(uploads, 'uploads while disabled').toBe(0)
    expect(page.errors).toEqual([])
  })

  test(`${route}: tiptap: no bubble menu opens on a click in a table, on a link or on an image when disabled, and a link still opens`, async ({ page }) => {
    const def = caseOf('tiptap')
    const surface = () => root(page, def).locator('.ProseMirror')
    const menus = () => root(page, def).evaluate((el) => ({
      table: getComputedStyle(el.querySelector('.table-menu')).visibility === 'visible',
      image: getComputedStyle(el.querySelector('.image-menu')).visibility === 'visible',
      link: [...el.querySelectorAll('.link-menu')].some((m) => m.textContent.includes('/atom/docs') && m.getBoundingClientRect().height > 0 && getComputedStyle(m).visibility === 'visible'),
    }))
    const clicks = { table: 'td p', image: 'img[src^="data:"]', link: 'a' }
    test.setTimeout(70_000)
    // a click on the link opens it in a tab (below): not one to keep while the menus are read
    const close = (popup) => popup.close().catch(() => {})
    page.on('popup', close)

    for (const kind of Object.keys(clicks)) {
      await openCase(page, def, { route, value: DOC })
      await surface().locator(clicks[kind]).first().click()
      await expect.poll(async () => (await menus())[kind], { message: `enabled: a click on the ${kind} shows its menu` }).toBe(true)

      await openCase(page, def, { route, off: true, value: DOC })
      // `force`: Playwright counts a node under `aria-disabled` as not enabled and would wait for it; the click is a person's
      await surface().locator(clicks[kind]).first().click({ force: true })
      await page.waitForTimeout(600)
      expect((await menus())[kind], `a click on the ${kind} showed its menu`).toBe(false)
    }

    page.off('popup', close)

    // a link in a read-only text still opens, as a link does in any page (the editor's own click handler is off)
    await openCase(page, def, { route, off: true, value: DOC })
    const [popup] = await Promise.all([page.waitForEvent('popup', { timeout: 5000 }), surface().locator('a').first().click({ force: true })])
    expect(popup.url()).toContain('/atom/docs')
    await popup.close()
  })
}

test('tiptap_mention: a mention list does not open on a disabled editor, however the text gets its trigger', async ({ page }) => {
  const def = caseOf('tiptap_mention')
  const popup = () => root(page, def).locator('.tiptap-mention [x-ref="dropdown"]').evaluate((el) => !el.classList.contains('invisible'))
  const trigger = () => data(page, def, (d) => { d.editor().chain().focus('end').insertContent(' @Al').run() })

  await openCase(page, def, { route: 'prop' })
  await trigger()
  await expect.poll(popup, { message: 'enabled: the trigger opens the list' }).toBe(true)

  await openCase(page, def, { route: 'prop', off: true })
  await trigger()
  await page.waitForTimeout(500)
  expect(await popup(), 'the list opened on a disabled editor').toBe(false)
  expect(page.errors).toEqual([])
})

// ---- readonly follows a morph (decision D6)

test('tiptap_readonly: a host readonly that a morph changes takes effect, and is not drawn disabled', async ({ page }) => {
  const def = caseOf('tiptap_readonly')
  const surface = () => root(page, def).locator('.ProseMirror')
  const editable = () => surface().getAttribute('contenteditable')

  // editable first: readonly arrives with a morph, and takes the toolbar away
  await openCase(page, def, { route: 'prop' })
  expect(await editable()).toBe('true')
  await flip(page)
  expect(await editable()).toBe('false')
  await expect(surface()).toHaveAttribute('aria-readonly', 'true')
  await expect(surface()).not.toHaveAttribute('aria-disabled', /.*/)
  await expect(root(page, def).locator('[role="toolbar"]')).toBeHidden()
  await expect(root(page, def).locator('[data-atom-tiptap]')).not.toHaveAttribute('data-atom-disabled', /.*/)
  const before = await html(page, def)
  await surface().click({ force: true })
  await page.keyboard.type('zq7')
  expect(await html(page, def)).toBe(before)

  await flip(page)
  expect(await editable()).toBe('true')
  await expect(surface()).not.toHaveAttribute('aria-readonly', /.*/)
  await expect(root(page, def).locator('[role="toolbar"]')).toBeVisible()

  // read-only first: it becomes editable, and has no toolbar (it was never drawn; the limit is documented)
  await openCase(page, def, { route: 'prop', off: true })
  expect(await editable()).toBe('false')
  await expect(root(page, def).locator('[role="toolbar"]')).toHaveCount(0)
  await flip(page)
  expect(await editable()).toBe('true')
  await surface().click()
  await page.keyboard.type('zq8')
  await expect(surface()).toContainText('zq8')
  expect(page.errors).toEqual([])
})

// ---- the chat composer

/** Run `fn` in the page inside a real click (WebKit and Firefox open a file chooser only for a user gesture, which `evaluate` is not). */
async function inGesture(page, fn, arg) {
  await page.evaluate(([src, a]) => {
    window.addEventListener('click', () => new Function('a', `return (${src})(a)`)(a), { once: true, capture: true })
  }, [fn.toString(), arg])
  await page.mouse.click(2, 2)
}

for (const route of ROUTES) {
  test(`${route}: tiptap_chat: paste, drop, attach and the send paths do nothing when disabled`, async ({ page }) => {
    const def = caseOf('tiptap_chat')
    const files = () => data(page, def, (d) => d.files.length)
    const surface = () => root(page, def).locator('[x-ref="editor"]')
    const send = () => data(page, def, (d) => {
      d.editor().commands.setContent('<p>hello</p>')
      d.editor().options.element.dispatchEvent(new CustomEvent('editor-enter'))
      d.sync()
      d.$el.querySelector('button[aria-label="Submit"]').click()
    })
    // A plain Event with the transfer object defined on it, and the transfer a plain object: engines differ in what a script-built ClipboardEvent, DragEvent
    // or DataTransfer carries (Firefox ignores `clipboardData` in the ClipboardEvent init and may hold no files in a script-made DataTransfer), and the
    // handlers only read `items`, `files` and `getData`
    const fire = (type, prop) => surface().evaluate((el, [type, prop]) => {
      const file = new File(['abc'], 'a.txt', { type: 'text/plain' })
      const e = new Event(type, { bubbles: true, cancelable: true })
      Object.defineProperty(e, prop, { value: { items: [{ kind: 'file', getAsFile: () => file }], files: [file], getData: () => '' } })
      el.dispatchEvent(e)
      return e.defaultPrevented
    }, [type, prop])
    const paste = () => fire('paste', 'clipboardData')
    const drop = () => fire('drop', 'dataTransfer')

    await openCase(page, def, { route })
    await paste()
    expect(await files(), 'enabled: a pasted file is attached').toBe(1)
    await drop()
    expect(await files(), 'enabled: a dropped file is attached').toBe(2)
    await send()
    await page.waitForTimeout(200)
    expect((await observe(page, def)).sent, 'enabled: Enter, sync() and the button send').toBeGreaterThan(0)
    await inGesture(page, () => document.querySelector('button[aria-label="Attach"]').click())
    await page.waitForTimeout(200)
    expect(page.choosers, 'enabled: the attach button opens the chooser').toBeGreaterThan(0)

    await openCase(page, def, { route, off: true })
    await paste()
    expect(await drop(), 'a drop is cancelled, so the file cannot navigate the page away').toBe(true)
    await send()
    // the hidden button, clicked inside a real click (a chooser opens only for a user gesture), and the input's own handler
    await inGesture(page, () => document.querySelector('button[aria-label="Attach"]').click())
    await inGesture(page, () => window.Alpine.$data(document.querySelector('[data-atom-tiptap]')).readFiles([new File(['abc'], 'b.txt')]))
    await page.waitForTimeout(300)
    expect(await files(), 'attached while disabled').toBe(0)
    expect((await observe(page, def)).sent, 'sent while disabled').toBe(0)
    expect(page.choosers, 'a chooser opened while disabled').toBe(0)
    await expect(root(page, def).locator('button[aria-label="Submit"]')).toBeHidden()
    expect(page.errors).toEqual([])
  })
}

test('tiptap_chat: a morph that disables the composer hides its buttons, and the way back shows them', async ({ page }) => {
  const def = caseOf('tiptap_chat')
  const submit = () => root(page, def).locator('button[aria-label="Submit"]')

  await openCase(page, def, { route: 'prop' })
  await expect(submit()).toBeVisible()
  await flip(page)
  await expect(submit()).toBeHidden()
  await expect(root(page, def).locator('.ProseMirror')).toHaveAttribute('contenteditable', 'false')
  await flip(page)
  await expect(submit()).toBeVisible()
  await expect(root(page, def).locator('.ProseMirror')).toHaveAttribute('contenteditable', 'true')
})
