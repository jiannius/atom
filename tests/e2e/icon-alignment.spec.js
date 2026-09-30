import { test, expect } from '@playwright/test'

// The rig serves atom's base CSS only — every utility class comes from the CONSUMING
// app's Tailwind build, which testbench-serve has none of. So these are the utilities
// the two layouts under test are built from, defined exactly as Tailwind emits them.
//
// They must stay class-driven: a rule that centred the glyph by data-attribute would
// pass whatever the component's markup says, which is the whole question here.
const CONSUMER_CSS = `
  body { font-size: 14px; line-height: 21px; }

  .flex { display: flex; }
  .inline-flex { display: inline-flex; }
  .items-center { align-items: center; }
  .justify-center { justify-content: center; }
  .shrink-0 { flex-shrink: 0; }
  .gap-2 { gap: 8px; }
  .min-h-10 { min-height: 40px; }
  .h-10 { height: 40px; }
  .py-1\\.5 { padding-top: 6px; padding-bottom: 6px; }
  .py-3 { padding-top: 12px; padding-bottom: 12px; }
  .pl-3 { padding-left: 12px; }
  .pr-2 { padding-right: 8px; }
  .pr-3 { padding-right: 12px; }
  .size-5 { width: 20px; height: 20px; }
  .size-4 { width: 16px; height: 16px; }
  [data-atom-icon] > * { width: 100%; height: 100%; }
`

const centre = async locator => {
  const box = await locator.boundingBox()
  return box.y + box.height / 2
}

// A block wrapper around a bare icon is a line box: the glyph sits on ITS baseline
// with the descender gap left underneath, which lifts it ~2.5px above the centre line
// the row's align-items is holding the label at. Reported on a filter bar.
test('the filter trigger caret sits on the row centre, not on the text baseline', async ({ page }) => {
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')
  await page.addStyleTag({ content: CONSUMER_CSS })

  const trigger = page.getByRole('combobox', { name: 'Status' }).first()
  const caret = trigger.locator('[data-atom-icon]').last()

  const [triggerCentre, caretCentre, labelCentre] = await Promise.all([
    centre(trigger),
    centre(caret),
    centre(trigger.locator('div.font-medium').first()),
  ])

  // half a pixel of slack for sub-pixel rounding; the bug was 2.5px
  expect(Math.abs(caretCentre - triggerCentre), 'caret is off the trigger centre').toBeLessThan(0.5)
  expect(Math.abs(caretCentre - labelCentre), 'caret and label disagree').toBeLessThan(0.5)
})

// Same mechanism one component over: the listbox parks its caret in a fixed-height box
// at the field's right edge, where it alternates with the clear ✕ — and that one was
// already a flex box, so the two glyphs sat at different heights as a value came and went.
test('the listbox caret sits on the centre of the box it shares with the clear button', async ({ page }) => {
  await page.goto('/atom/e2e/table-filters')
  await page.waitForLoadState('networkidle')
  await page.addStyleTag({ content: CONSUMER_CSS })

  // the listbox lives in the third bar's overflow modal
  const bar = page.locator('[data-atom-table-filters]').nth(2)
  await bar.getByRole('button', { name: /More filters/ }).click()

  const affix = bar.locator('[data-atom-select-listbox] [data-atom-select-affix]').first()
  const caret = affix.locator('[data-atom-icon]').first()

  await expect(caret).toBeVisible()

  expect(Math.abs(await centre(caret) - await centre(affix)), 'caret is off its box centre').toBeLessThan(0.5)
})
