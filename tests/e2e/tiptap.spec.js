import { test, expect } from '@playwright/test'

// Drives the live demos on /atom/docs/tiptap (Basic = full toolbar, Chat, Mentions).
// The editor mounts client-side via x-data="tiptap({...})" which lazy-imports the v3
// engine chunk; onCreate sets loading=false which reveals the .editor div. No Livewire
// server is involved, so everything the editor does client-side is testable here:
// mount, toolbar commands, bubble-menu visibility, and the static-array mention popup.

// The basic editor is the first on the page (toolbar="full").
const basicEditor = (page) => page.locator('[x-data*="tiptap("]').first().locator('.editor').first()
// The mention editor is the one whose subtree contains a .tiptap-mention dropdown.
const mentionEditor = (page) => page.locator('.editor', { has: page.locator('.tiptap-mention') })

async function waitForEditor (locator) {
  await expect(locator).toBeVisible({ timeout: 15000 })
}

test('mounts and shows the toolbar', async ({ page }) => {
  const errors = []
  page.on('pageerror', (err) => errors.push(err.message))

  await page.goto('/atom/docs/tiptap')

  const editor = basicEditor(page)
  await waitForEditor(editor)
  await expect(editor.getByRole('toolbar').first()).toBeVisible()

  expect(errors).toEqual([])
})

test('is editable', async ({ page }) => {
  await page.goto('/atom/docs/tiptap')
  const editor = basicEditor(page)
  await waitForEditor(editor)

  const content = editor.locator('.editor-content').first()
  await content.click()
  await page.keyboard.type('hello tiptap')
  await expect(content).toContainText('hello tiptap')
})

test('a toolbar command applies to the document', async ({ page }) => {
  await page.goto('/atom/docs/tiptap')
  const editor = basicEditor(page)
  await waitForEditor(editor)

  const content = editor.locator('.editor-content').first()
  await content.click()
  await page.keyboard.type('para')

  // Horizontal Rule is a direct (non-dropdown) toolbar button → inserts an <hr>.
  await editor.getByRole('button', { name: 'Horizontal Rule' }).click()
  await expect(content.locator('hr')).toHaveCount(1)
})

test('bubble menus are hidden on load (regression: they used to render inline)', async ({ page }) => {
  // The table/image/youtube bubble menus carry static button content. Tiptap v3's
  // BubbleMenu positions them in place and only reveals them on node-selection, so
  // without a hidden-by-default they rendered permanently visible inline on load.
  await page.goto('/atom/docs/tiptap')
  const editor = basicEditor(page)
  await waitForEditor(editor)

  await expect(editor.locator('.table-menu')).toBeHidden()
  await expect(editor.locator('.image-menu')).toBeHidden()
  await expect(editor.locator('.youtube-menu')).toBeHidden()
})

test('a bubble menu floats when its node is selected', async ({ page }) => {
  await page.goto('/atom/docs/tiptap')
  const editor = basicEditor(page)
  await waitForEditor(editor)

  // Insert a table and put the cursor in it → the table bubble menu should appear.
  await editor.locator('.editor-content').first().click()
  await editor.evaluate((el) => {
    const ed = el.closest('[x-data]')._x_dataStack[0].editor()
    ed.chain().focus().insertTable({ rows: 2, cols: 2 }).run()
    ed.commands.focus()
  })

  await expect(editor.locator('.table-menu')).toBeVisible()
  // and it is positioned (floating), not inline in normal flow
  await expect(editor.locator('.table-menu')).toHaveCSS('position', 'absolute')
})

test('mention popup: typing @ filters options and inserts a mention', async ({ page }) => {
  await page.goto('/atom/docs/tiptap')
  const editor = mentionEditor(page)
  await waitForEditor(editor)

  const content = editor.locator('.editor-content').first()
  await content.click()
  await page.keyboard.type('hey @')

  // the static-array dropdown (['Alice','Bob','Carol']) appears
  const dropdown = editor.locator('.tiptap-mention')
  await expect(dropdown.getByText('Alice')).toBeVisible()
  await expect(dropdown.getByText('Bob')).toBeVisible()

  // filter to Alice, then commit with Enter
  await page.keyboard.type('Al')
  await expect(dropdown.getByText('Alice')).toBeVisible()
  await page.keyboard.press('Enter')

  // a mention node is inserted as <span class="mention">
  await expect(content.locator('span.mention')).toContainText('Alice')
})

test('link toolbar button opens the URL edit form (regression: empty popup)', async ({ page }) => {
  // The open handler used to sit on the child <atom:menu> as x-on:popover-open and
  // never fired, so the popup rendered empty. It now lives on <atom:dropdown> as
  // x-on:open and dispatches link-menu-edit → the URL input renders.
  await page.goto('/atom/docs/tiptap')
  const editor = basicEditor(page)
  await waitForEditor(editor)

  await editor.locator('.editor-content').first().click()
  await editor.getByRole('button', { name: 'Link' }).click()

  const urlInput = page.getByPlaceholder('Link URL')
  await expect(urlInput).toBeVisible()

  // Regression: clicking + typing inside the popup used to close it (the close-on-
  // inside-click handler fired because x-on:click.stop sat on the popover element,
  // not a child). The input must stay usable.
  await urlInput.click()
  await urlInput.fill('https://example.com')
  await expect(urlInput).toBeVisible()
  await expect(urlInput).toHaveValue('https://example.com')
})

test('link toolbar shows the current link when one is active', async ({ page }) => {
  await page.goto('/atom/docs/tiptap')
  const editor = basicEditor(page)
  await waitForEditor(editor)

  // create a link on the whole doc, then reopen the toolbar with the link active
  await editor.locator('.editor-content').first().click()
  await page.keyboard.type('example')
  await editor.evaluate((el) => {
    const ed = el.closest('[x-data]')._x_dataStack[0].editor()
    ed.chain().focus().selectAll().setLink({ href: 'https://example.com' }).run()
    ed.commands.focus()
  })

  await editor.getByRole('button', { name: 'Link' }).click()
  // link-menu-on → getLink populates the info view with the href
  await expect(page.getByText('https://example.com')).toBeVisible()
})
