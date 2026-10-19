import { test, expect } from '@playwright/test'
import { sweepText } from './display-helpers.js'

// The sweep the display specs read pages with has to find what it is meant to find: text in a display: contents element is drawn by
// its ancestor, so it is measured; text under a display: none or visibility: hidden ancestor is not drawn, so it is not.

const page_ = (body) => `<!doctype html><html><body style="margin:0;background:#fff">${body}</body></html>`

test('the sweep measures text in a display: contents element and skips text that is not drawn', async ({ page }) => {
  await page.setContent(page_(`
    <div style="display:contents"><span style="color:#ccc">low in contents</span></div>
    <p style="display:contents;color:#ccc">low text of the contents element itself</p>
    <div style="display:none"><span style="color:#ccc">low in none</span></div>
    <div style="visibility:hidden"><span style="color:#ccc">low hidden</span></div>
    <div style="display:contents"><div style="display:none"><span style="color:#ccc">low in contents then none</span></div></div>
    <span style="color:#000">ok one</span><span style="color:#000">ok two</span><span style="color:#000">ok three</span>
  `))

  const failures = await sweepText(page, 'body')

  expect(failures.map((f) => f.text)).toEqual(['low in contents', 'low text of the contents element itself'])
})

test('the sweep fails when it reads too little', async ({ page }) => {
  await page.setContent(page_('<span style="color:#000">only one</span>'))

  expect((await sweepText(page, 'body', 4.5, 3)).map((f) => f.text)).toEqual(['only 1 text nodes found under body'])
})
