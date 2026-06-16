import { test, expect } from '@playwright/test'

// Drives the live demo on /atom/docs/tiptap.
// The editor mounts via x-data="tiptap({...})" which lazy-imports the v3 engine chunk;
// on onCreate it sets loading=false which reveals .editor-content.

test('atom:tiptap mounts and shows the toolbar', async ({ page }) => {
  const errors = []
  page.on('pageerror', (err) => errors.push(err.message))

  await page.goto('/atom/docs/tiptap')

  // Wait for the editor to finish mounting (loading=false reveals the .editor div)
  const editorWrap = page.locator('[x-data*="tiptap("]').first()
  await expect(editorWrap).toBeVisible()

  // The inner .editor div (x-show="!loading") becomes visible once the engine chunk loads
  const editorBox = editorWrap.locator('.editor').first()
  await expect(editorBox).toBeVisible({ timeout: 15000 })

  // Toolbar is rendered as role="toolbar"
  const toolbar = editorBox.getByRole('toolbar').first()
  await expect(toolbar).toBeVisible()

  expect(errors).toEqual([])
})

test('atom:tiptap is editable', async ({ page }) => {
  await page.goto('/atom/docs/tiptap')

  const editorWrap = page.locator('[x-data*="tiptap("]').first()
  const editorBox = editorWrap.locator('.editor').first()
  await expect(editorBox).toBeVisible({ timeout: 15000 })

  // The ProseMirror contenteditable div is .editor-content
  const content = editorBox.locator('.editor-content').first()
  await expect(content).toBeVisible()

  await content.click()
  await page.keyboard.type('hello tiptap')
  await expect(content).toContainText('hello tiptap')
})
