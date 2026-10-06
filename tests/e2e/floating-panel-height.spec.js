import { test, expect } from '@playwright/test'

// Drives /atom/e2e/floating-panel-height (resources/views/e2e/floating-panel-height.blade.php).
//
// Reported from smgdms: on a short screen (1366x620, 1280x650, field at y of about 284) an open
// <atom:date-picker time> panel, about 360px tall, fits neither below the field nor above it.
// floating-ui picked the side with less overflow and clipped the rest, and the panel had no
// max-height, so the TIME row (below) or the calendar header (above) sat off-screen with no way
// to scroll to it. The helper only ran offset(), flip() and shift(), no size().
//
// "Reachable" is what the bug is about, so that is what is asserted: the element at the centre
// of the target is the target itself, either as the panel opens or after the PANEL (never the
// page) has been scrolled to it.

// The rig ships no Tailwind. Stand in for a consumer's build with the rules these components
// use, defined as Tailwind emits them, so the panel is the ~360px tall one the report describes.
const CONSUMER_CSS = `
  *, ::before, ::after { box-sizing: border-box; }
  body { margin: 0; }
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
  .mb-2 { margin-bottom: 8px; }
  .p-2 { padding: 8px; }
  .p-\\[\\.3125rem\\] { padding: 5px; }
  .pointer-events-none { pointer-events: none; }
  .w-full { width: 100%; }
  .h-full { height: 100%; }
  .h-10 { height: 40px; }
  .py-2 { padding-top: 8px; padding-bottom: 8px; }
  .pl-3 { padding-left: 12px; }
  .pr-16 { padding-right: 64px; }
  .size-5 { width: 20px; height: 20px; }
  [data-atom-icon] > * { width: 100%; height: 100%; }
  .w-\\[300px\\] { width: 300px; }
  .text-sm { font-size: 14px; line-height: 20px; }
  .text-xl { font-size: 20px; line-height: 28px; }
  .uppercase { text-transform: uppercase; }
  [data-atom-date-picker-calendar] .pika-lendar { position: relative; padding: 12px; width: 100%; }
  [data-atom-date-picker-calendar] .pika-lendar > * + * { margin-top: 8px; }
  [data-atom-date-picker-calendar] .pika-title { display: inline-flex; align-items: center; gap: 8px; }
  [data-atom-date-picker-calendar] .pika-label { font-size: 20px; line-height: 28px; position: relative; }
  [data-atom-date-picker-calendar] .pika-table { width: 100%; }
  [data-atom-date-picker-calendar] .pika-table td button { width: 32px; height: 26px; text-align: center; }
  [data-atom-date-picker-calendar] .pika-table td { padding: 1px; text-align: center; }
  [data-atom-date-picker-calendar] .pika-table th { padding: 8px; font-size: 14px; }
`

const stack = (page) => page.locator('[data-probe="date"]')
const panel = (page) => stack(page).locator('[popover]')

async function open (page, viewport, top = 284) {
  await page.setViewportSize(viewport)
  await page.goto('/atom/e2e/floating-panel-height?top=' + top)
  await page.addStyleTag({ content: CONSUMER_CSS })
  await page.waitForFunction(() => window.Alpine)
  await stack(page).getByPlaceholder('Select date').click()
  await expect(panel(page)).toBeVisible()
}

// The time row is inert (pointer-events-none) until a day is picked, so a user has to pick a day
// before they can reach the hour. A real mouse click at the day's own coordinate: the calendar
// is the top of the panel, on screen whichever side the panel opened on.
async function pickADay (page) {
  const box = await stack(page).locator('.pika-table td:not(.is-disabled) button').nth(10).boundingBox()
  await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2)
  await expect(stack(page).getByPlaceholder('Select date')).not.toHaveValue('')
  await expect(panel(page)).toBeVisible()
}

// Is the target the thing under its own centre? If not, scroll the PANEL (never the page) so
// the target's centre is inside the panel's box, and ask again.
function reachable (page, targetSelector) {
  return page.evaluate((sel) => {
    const pop = document.querySelector('[data-probe="date"] [popover]')
    const target = pop.querySelector(sel)
    const hit = () => {
      const r = target.getBoundingClientRect()
      const el = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
      return !!el && target.contains(el)
    }

    const before = { x: window.scrollX, y: window.scrollY }
    const reachedAsOpened = hit()
    let reachedByScrollingPanel = reachedAsOpened

    if (!reachedAsOpened) {
      const p = pop.getBoundingClientRect()
      const t = target.getBoundingClientRect()
      pop.scrollTop += (t.top + t.height / 2) - (p.top + p.height / 2)
      reachedByScrollingPanel = hit()
    }

    return {
      reachedAsOpened,
      reachedByScrollingPanel,
      pageScrolled: window.scrollX !== before.x || window.scrollY !== before.y,
    }
  }, targetSelector)
}

const viewports = [
  { name: '1366x620', width: 1366, height: 620 },
  { name: '1280x650', width: 1280, height: 650 },
  // flips up here, so it is the calendar header that gets clipped
  { name: '1366x600', width: 1366, height: 600 },
]

for (const vp of viewports) {
  test(`${vp.name}: the time row and the calendar header of a date+time panel are reachable`, async ({ page }) => {
    await open(page, vp)

    // sanity: this really is the tall panel of the report, taller than either side gets
    const natural = await panel(page).evaluate((el) => el.scrollHeight)
    expect(natural).toBeGreaterThan(300)

    await pickADay(page)

    for (const sel of ['input[aria-label="Hour"]', '.pika-label']) {
      const result = await reachable(page, sel)
      expect(result.reachedByScrollingPanel, `${sel} unreachable`).toBe(true)
      expect(result.pageScrolled).toBe(false)
    }

    // the panel itself must sit inside the viewport, not hang off an edge
    const box = await panel(page).boundingBox()
    expect(box.y).toBeGreaterThanOrEqual(0)
    expect(box.y + box.height).toBeLessThanOrEqual(vp.height)

    // none of it was done by scrolling the page
    expect(await page.evaluate(() => window.scrollY)).toBe(0)
  })
}

test('a capped panel grows back to its natural height when the room returns', async ({ page }) => {
  await open(page, { width: 1366, height: 620 })

  await expect.poll(() => panel(page).evaluate((el) => el.offsetHeight < el.scrollHeight)).toBe(true)

  // a taller window: autoUpdate re-runs the helper while the panel stays open
  await page.setViewportSize({ width: 1366, height: 900 })

  await expect.poll(() => panel(page).evaluate((el) => [el.offsetHeight >= el.scrollHeight, el.style.maxHeight, el.style.overflowY])).toEqual([true, '', ''])
})

// What the cap must not disturb. Every other panel goes through the same helper.

async function gotoPlain (page, viewport = { width: 1366, height: 700 }, top = 0) {
  await page.setViewportSize(viewport)
  await page.goto('/atom/e2e/floating-panel-height?top=' + top)
  await page.addStyleTag({ content: CONSUMER_CSS })
  await page.waitForFunction(() => window.Alpine)
}

// Where the panel asked to be, where it is, and how far that is from its anchor.
const placement = (panelLocator, triggerLocator) => Promise.all([
  panelLocator.evaluate((el) => {
    const r = el.getBoundingClientRect()
    return { requested: { x: Math.round(parseFloat(el.style.left)), y: Math.round(parseFloat(el.style.top)) }, rendered: { x: Math.round(r.x), y: Math.round(r.y), bottom: r.bottom } }
  }),
  triggerLocator.boundingBox(),
]).then(([p, t]) => ({ ...p, trigger: t }))

test('a dropdown below the fold of a scrolled page still opens next to its trigger, and scrolls when taller than the room', async ({ page }) => {
  await gotoPlain(page, undefined, 1100)

  const root = page.locator('[data-probe="dropdown"]')
  const trigger = root.getByRole('button', { name: 'Many items' })
  const menu = root.locator('[data-atom-menu]')

  // put the trigger a third of the way down, well below where the page started
  await trigger.evaluate((el) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 230))
  const scrollY = await page.evaluate(() => window.scrollY)
  expect(scrollY).toBeGreaterThan(0)

  await trigger.click()
  await expect(menu).toBeVisible()

  const at = await placement(menu, trigger)
  expect(at.rendered.x).toBe(at.requested.x)
  expect(at.rendered.y).toBe(at.requested.y)
  // under the trigger (bottom-start, 2px offset), not displaced by the scroll distance
  expect(Math.abs(at.rendered.y - (at.trigger.y + at.trigger.height))).toBeLessThanOrEqual(4)
  expect(at.rendered.bottom).toBeLessThanOrEqual(700)

  // 40 items cannot fit, so the menu scrolls and its last item can be brought into view
  const scrolls = await menu.evaluate((el) => el.scrollHeight > el.clientHeight && getComputedStyle(el).overflowY === 'auto')
  expect(scrolls).toBe(true)
  await menu.evaluate((el) => { el.scrollTop = el.scrollHeight })
  const last = await menu.locator('[role="menuitem"]').last().boundingBox()
  expect(last.y + last.height).toBeLessThanOrEqual(700)
  expect(await page.evaluate(() => window.scrollY)).toBe(scrollY)
})

test('a select listbox below the fold still opens next to its trigger, inside the viewport', async ({ page }) => {
  await gotoPlain(page, undefined, 1100)

  const root = page.locator('[data-probe="select"]')
  const trigger = root.locator('[data-atom-dropdown] > button')
  const list = root.locator('[popover]')

  await trigger.evaluate((el) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 300))
  expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(0)

  await trigger.click()
  await expect(list).toBeVisible()

  const at = await placement(list, trigger)
  expect(at.rendered.y).toBe(at.requested.y)
  expect(Math.abs(at.rendered.y - (at.trigger.y + at.trigger.height))).toBeLessThanOrEqual(4)
  expect(at.rendered.bottom).toBeLessThanOrEqual(700)
})

test('a panel that fits is left alone: no max-height, no overflow written', async ({ page }) => {
  await gotoPlain(page, { width: 1366, height: 900 })

  const root = page.locator('[data-probe="small"]')
  await root.getByRole('button', { name: 'Few items' }).click()
  const menu = root.locator('[data-atom-menu]')
  await expect(menu).toBeVisible()

  expect(await menu.evaluate((el) => [el.style.maxHeight, el.style.overflowY])).toEqual(['', ''])
})

test('a panel\'s own max-height, from a class or inline, is never raised', async ({ page }) => {
  await gotoPlain(page)

  for (const [probe, name] of [['capped', 'Class cap'], ['inline', 'Inline cap']]) {
    const root = page.locator(`[data-probe="${probe}"]`)
    const trigger = root.getByRole('button', { name })
    const menu = root.locator('[data-atom-menu]')

    // twice: the second open must not read the first open's cap back as the panel's own
    for (let i = 0; i < 2; i++) {
      await trigger.click()
      await expect(menu).toBeVisible()
      expect(await menu.evaluate((el) => getComputedStyle(el).maxHeight), `${probe} open ${i + 1}`).toBe('120px')
      await page.keyboard.press('Escape')
      await expect(menu).toBeHidden()
    }
  }
})

test('a capped panel keeps its own scroll position through a reposition', async ({ page }) => {
  await open(page, { width: 1366, height: 620 })
  await expect.poll(() => panel(page).evaluate((el) => el.offsetHeight < el.scrollHeight)).toBe(true)

  // the user has scrolled the panel down to the time row
  await panel(page).evaluate((el) => { el.scrollTop = 40 })
  expect(await panel(page).evaluate((el) => el.scrollTop)).toBe(40)

  // a small page scroll makes autoUpdate reposition the open panel; it stays capped
  // (a few px more room, still short of its natural height)
  await page.evaluate(() => window.scrollTo(0, 10))
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(10)
  await page.waitForTimeout(300)

  expect(await panel(page).evaluate((el) => el.offsetHeight < el.scrollHeight)).toBe(true)
  // reset-to-measure drops the scroll position (overflow goes back to visible); it must be put back
  expect(await panel(page).evaluate((el) => el.scrollTop)).toBeGreaterThan(0)
})

test('flip measures the natural height, so a capped panel moves to the roomier side', async ({ page }) => {
  await page.setViewportSize({ width: 1366, height: 620 })
  await page.goto('/atom/e2e/floating-panel-height?top=400')
  await page.addStyleTag({ content: CONSUMER_CSS })
  await page.waitForFunction(() => window.Alpine)

  // Make the page end exactly where the window does when scrolled to the bottom. Growing the
  // window then also scrolls the page up by as much, so the field moves DOWN by the growth
  // while the room below it stays the same: one resize that opens room above but none below.
  await page.evaluate(() => {
    for (const el of document.querySelectorAll('[data-probe]:not([data-probe="date"])')) el.style.display = 'none'
    // field at page y 400, 40px tall; the page is 770 tall, so scrolled to the end at 620 it sits at y 250
    document.querySelector('[data-spacer]').style.height = (770 - 440) + 'px'
    window.scrollTo(0, 1e6)
  })
  expect(await page.evaluate(() => window.scrollY)).toBe(150)

  await stack(page).getByPlaceholder('Select date').click()
  await expect(panel(page)).toBeVisible()

  const geometry = () => Promise.all([panel(page).boundingBox(), stack(page).getByPlaceholder('Select date').boundingBox()])
    .then(([p, f]) => ({ p, f }))

  // at 620 the field has 243px above and 325px below, the panel needs 353: it opens below, capped to 325
  await expect.poll(() => panel(page).evaluate((el) => el.offsetHeight < el.scrollHeight)).toBe(true)
  let { p, f } = await geometry()
  expect(p.y).toBeGreaterThanOrEqual(f.y + f.height)

  // 750px: still 325 below, now 373 above, which holds the whole panel
  await page.setViewportSize({ width: 1366, height: 750 })

  await expect.poll(async () => {
    ({ p, f } = await geometry())
    return p.y + p.height <= f.y
  }).toBe(true)
  expect(await panel(page).evaluate((el) => el.offsetHeight >= el.scrollHeight)).toBe(true)
})
