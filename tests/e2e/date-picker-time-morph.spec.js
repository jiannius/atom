import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/date-picker-time-morph
// (tests/Fixtures/DatePickerTimeMorphFixture.php).
//
// Regression cover for GitHub issue #53: <atom:date-picker time wire:model.live="...">
// (the single, non-range variant) lost its calendar and its floating-ui position
// whenever a Livewire update landed while the panel was open.
//
// Only a `time`-flagged picker bound `.live` can hit this: a plain date-only picker
// closes itself the instant a date is picked (date-picker.js's `!config.time &&
// this.popover.hidePopover()`), so it never survives an update while the panel is open.
// With `time`, the panel stays open after the pick, `wire:model.live` round-trips the
// value to the server, and the response re-renders the component around the still-open
// panel.
//
// components/date-picker/calendar.blade.php renders an empty
// `<div data-atom-date-picker-calendar>` — resources/js/alpinejs/date-picker.js's
// setCalendar() fills it client-side by prepending a Pikaday instance, and only runs
// once per open (watching `visible`, not re-run on every render). Without `wire:ignore`
// on the panel, the update replaces that div with the server's empty markup, wiping the
// calendar out from under the still-mounted Alpine instance, and the panel shrinks —
// the time row jumps up to where the calendar used to be.
//
// A scripted Playwright `.click()` on a locator re-queries that locator's current
// bounding box immediately before clicking, so it always lands on the target wherever
// it has moved to — it cannot reproduce "the user clicks where the field visually was
// and misses". Reproducing that requires a real mouse event dispatched at a FIXED
// viewport coordinate captured before the update, exactly the shape used below.

// The rig serves atom's base CSS only — every utility class comes from the CONSUMING
// app's Tailwind build, which testbench-serve has none of. Without it every icon (no
// intrinsic size) and every `absolute`-positioned overlay renders in normal flow at
// its default size, which is exactly what would wreck a bounding-box-based click test:
// the panel and its controls would land at arbitrary, oversized positions that have
// nothing to do with the real (Tailwind-styled) layout the bug report describes. Stand
// in for a consumer's build with the exact classes these components use, defined as
// Tailwind emits them, so the coordinates captured below mean what they would in a real
// app.
const CONSUMER_CSS = `
  .relative { position: relative; }
  .absolute { position: absolute; }
  .top-0 { top: 0; }
  .bottom-0 { bottom: 0; }
  .right-0 { right: 0; }
  .z-1 { z-index: 1; }
  .flex { display: flex; }
  .items-center { align-items: center; }
  .justify-center { justify-content: center; }
  .gap-2 { gap: 8px; }
  .pr-3 { padding-right: 12px; }
  .pl-3 { padding-left: 12px; }
  .px-2 { padding-left: 8px; padding-right: 8px; }
  .pb-2 { padding-bottom: 8px; }
  .pointer-events-none { pointer-events: none; }
  .w-full { width: 100%; }
  .h-full { height: 100%; }
  .h-10 { height: 40px; }
  .w-8 { width: 32px; }
  .size-5 { width: 20px; height: 20px; }
  [data-atom-icon] > * { width: 100%; height: 100%; }
  .w-\\[300px\\] { width: 300px; }
`

const probe = (page) => page.locator('[data-probe="live"]')
const popover = (page) => probe(page).locator('[popover]')
const calendarGrids = (page) => probe(page).locator('.pika-lendar')
const hourInput = (page) => probe(page).locator('input[aria-label="Hour"]')

// Triggers the bump action directly through Livewire's own JS API rather than
// clicking the button in the page: the bump button sits outside the popover, and a
// real click there would light-dismiss the (native, `popover=auto`) panel before the
// round trip even starts — closing the panel is correct behaviour for an actual
// outside click, but it isn't the thing this helper is trying to isolate (a plain
// re-render landing while the panel stays open).
async function bump (page) {
  const renders = page.locator('[data-renders]')
  const before = Number(await renders.textContent())
  await page.evaluate(() => window.Livewire.first().call('bump'))
  await expect(renders).toHaveText(String(before + 1))
}

async function open (page) {
  // The trigger is the plain input wrapper (no explicit button in the slot-less
  // variant) — a real click at its own bounding box is fine here since nothing has
  // moved yet.
  await probe(page).getByPlaceholder('Select date').click()
  await expect(popover(page)).toBeVisible()
  await expect(calendarGrids(page)).toHaveCount(1)
}

async function pickADayWithRealMouse (page) {
  // A real day button inside the still-empty-of-bugs calendar. Avoid the very first
  // row in case it's a disabled overflow day from the previous month.
  const day = probe(page).locator('.pika-table td:not(.is-disabled) button').nth(10)
  const box = await day.boundingBox()

  await Promise.all([
    page.waitForResponse((r) => r.url().includes('livewire')),
    (async () => {
      await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
      await page.mouse.down()
      await page.mouse.up()
    })(),
  ])

  // let the response settle: Alpine re-evaluates x-bind:class, dayjs formats, etc.
  await page.waitForTimeout(300)
}

test.beforeEach(async ({ page }) => {
  await page.goto('/atom/e2e/date-picker-time-morph')
  await page.addStyleTag({ content: CONSUMER_CSS })
  await page.waitForFunction(() => window.Livewire && window.Alpine)
})

test('renders exactly one calendar grid on open', async ({ page }) => {
  await open(page)
})

test('a plain Livewire re-render does not wipe the calendar (wire:ignore holds)', async ({ page }) => {
  await open(page)
  await bump(page)
  await expect(calendarGrids(page)).toHaveCount(1)
  await expect(popover(page)).toBeVisible()
})

test('a wire:model.live round trip from picking a day keeps the calendar and the panel position', async ({ page }) => {
  await open(page)

  const styleBefore = await popover(page).getAttribute('style')
  expect(styleBefore).toBeTruthy()

  await pickADayWithRealMouse(page)

  // the literal bug: the calendar container goes from 1 child to 0 and the panel
  // shrinks, because the update replaced the empty server markup over it
  await expect(calendarGrids(page)).toHaveCount(1)
  await expect(popover(page)).toBeVisible()

  const styleAfter = await popover(page).getAttribute('style')
  expect(styleAfter).toBeTruthy()
})

test('a real click at the time field pre-update position still reaches it, not the browser\'s native click-outside-close', async ({ page }) => {
  await open(page)

  // Capture the hour input's on-screen position BEFORE the round trip — this is the
  // position a user watching the (still-disabled) time row would have seen and where
  // they will click once the panel appears interactive, whether or not the layout has
  // silently shifted underneath them in the meantime.
  const boxBefore = await hourInput(page).boundingBox()

  await pickADayWithRealMouse(page)

  // Real mouse event at the FIXED, pre-update coordinate — not a re-queried locator
  // click, which would simply follow the hour input wherever the bug moved it and
  // could never reproduce a miss.
  await page.mouse.move(boxBefore.x + boxBefore.width / 2, boxBefore.y + boxBefore.height / 2)
  await page.mouse.down()
  await page.mouse.up()
  await page.waitForTimeout(100)

  // With the panel's layout preserved, that fixed coordinate still lands on the hour
  // input: it focuses, and the popover — never hit by an outside click — stays open.
  await expect(hourInput(page)).toBeFocused()
  await expect(popover(page)).toBeVisible()
})
