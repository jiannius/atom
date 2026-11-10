import { test, expect } from '@playwright/test'
import { caseOf, openCase, observe, root, serverState } from './disabled-helpers.js'

// The rating, the colour input and the uploaders block a disabled state by more than what the eye or a CSS class can see (the contract's second rule):
// a node a utility hides, or a gesture the rig's mouse cannot make, still reaches the handler. This spec calls the handlers and the browser's own
// events directly and asserts nothing changes, with the same call made enabled first so it is proven to work. Run `node tests/consumer/build.mjs` first.

const ROUTES = ['prop', 'form']

/** Run `fn` in the page inside a real click (WebKit and Firefox open a file chooser only for a user gesture, which `evaluate` is not). */
async function inGesture(page, fn, arg) {
  await page.evaluate(([src, a]) => {
    window.addEventListener('click', () => new Function('a', `return (${src})(a)`)(a), { once: true, capture: true })
  }, [fn.toString(), arg])
  await page.mouse.click(2, 2)
}

// ---- rating

const percent = (loc) => loc.evaluate((el) => el.style.getPropertyValue('--atom-rating-percent'))

for (const route of ROUTES) {
  test(`${route}: rating: pointer and key events that reach the track change and preview nothing when disabled`, async ({ page }) => {
    const def = caseOf('rating')
    const track = () => root(page, def).locator('[data-atom-rating-track]')
    const act = () => track().evaluate((el) => {
      const r = el.getBoundingClientRect()
      const at = { clientX: r.left + r.width * 0.95, clientY: r.top + r.height / 2, bubbles: true }
      el.dispatchEvent(new PointerEvent('pointermove', at))
      const hovered = el.closest('[data-atom-rating]').style.getPropertyValue('--atom-rating-percent')
      el.dispatchEvent(new MouseEvent('click', at))
      for (const key of ['ArrowRight', 'End']) el.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
      return hovered
    })

    await openCase(page, def, { route })
    await act()
    await page.waitForTimeout(100)
    expect((await observe(page, def)).dom, 'enabled: the same events change the value').toBe('5')

    await openCase(page, def, { route, off: true })
    const initial = (await observe(page, def)).dom
    const painted = await percent(root(page, def).locator('[data-atom-rating]'))
    const hovered = await act()
    await page.waitForTimeout(150)
    expect((await observe(page, def)).dom, 'the disabled value').toBe(initial)
    expect(hovered, 'no hover preview').toBe(painted)
    expect(await percent(root(page, def).locator('[data-atom-rating]'))).toBe(painted)
    expect(page.errors).toEqual([])
  })

  test(`${route}: rating: disabled is a slider that is off, dimmed, with the not-allowed cursor and no tab stop`, async ({ page }) => {
    const def = caseOf('rating')
    await openCase(page, def, { route, off: true })
    const track = root(page, def).locator('[data-atom-rating-track]')

    await expect(track).toHaveAttribute('role', 'slider')
    await expect(track).toHaveAttribute('aria-disabled', 'true')
    await expect(track).toHaveAttribute('aria-valuenow', '3')
    await expect(track).not.toHaveAttribute('tabindex', /.*/)
    expect(await track.evaluate((el) => [getComputedStyle(el).opacity, getComputedStyle(el).cursor])).toEqual(['0.5', 'not-allowed'])

    await openCase(page, def, { route })
    expect(await track.evaluate((el) => [getComputedStyle(el).opacity, getComputedStyle(el).cursor])).toEqual(['1', 'pointer'])
  })
}

test('rating: readonly alone stays a display value, not drawn disabled', async ({ page }) => {
  await page.goto('/atom/docs/rating')
  const display = page.locator('[data-atom-rating-track][role="img"]').first()

  await expect(display).toHaveCount(1)
  expect(await display.evaluate((el) => [getComputedStyle(el).opacity, getComputedStyle(el).cursor])).toEqual(['1', 'default'])
})

// ---- colour

for (const route of ROUTES) {
  test(`${route}: input_color: the swatches and the dropdown change and open nothing when disabled`, async ({ page }) => {
    const def = caseOf('input_color')
    const pick = () => root(page, def).locator('[data-atom-scroll-region] > div').nth(5).evaluate((el) => el.click())
    const input = () => root(page, def).locator('input')

    await openCase(page, def, { route })
    const before = await input().inputValue()
    await pick()
    await page.waitForTimeout(100)
    expect(await input().inputValue(), 'enabled: the swatch picks').not.toBe(before)

    await openCase(page, def, { route, off: true })
    const initial = await input().inputValue()
    await pick()
    await page.waitForTimeout(150)
    expect(await input().inputValue(), 'the disabled colour').toBe(initial)

    // the dropdown's own opener, called the way a host trigger or a key would
    const opened = await root(page, def).locator('[data-atom-color-input] > div').first().evaluate((el) => {
      const data = window.Alpine.$data(el)
      data.show()
      return el.querySelector('[popover]').matches(':popover-open')
    })
    expect(opened).toBe(false)
    expect(page.errors).toEqual([])
  })
}

test('input_color: its own disabled is a really disabled box, a disabled form a read-only one', async ({ page }) => {
  const def = caseOf('input_color')

  await openCase(page, def, { route: 'prop', off: true })
  expect(await root(page, def).locator('input').evaluate((el) => [el.disabled, el.readOnly])).toEqual([true, true])

  await openCase(page, def, { route: 'form', off: true })
  expect(await root(page, def).locator('input').evaluate((el) => [el.disabled, el.readOnly])).toEqual([false, true])
})

// ---- uploader, dropzone, file input

for (const route of ROUTES) {
  for (const id of ['uploader', 'uploader_dropzone', 'input_file', 'input_file_slot']) {
    test(`${route}: ${id}: no chooser opens, whoever calls the file input, when disabled`, async ({ page }) => {
      const def = caseOf(id)
      const direct = () => inGesture(page, (id) => document.querySelector(`[data-case="${id}"] input[type=file]`).click(), def.id)

      await openCase(page, def, { route })
      await direct()
      await page.waitForTimeout(200)
      expect(page.choosers, 'enabled: input.click() opens the chooser').toBeGreaterThan(0)

      await openCase(page, def, { route, off: true })
      await direct()
      // the host's own trigger, and the button, as a script or a click on a child of a disabled button would reach them
      await root(page, def).locator('button').evaluateAll((els) => els.forEach((el) => el.click()))
      await page.waitForTimeout(250)
      expect(page.choosers, 'a chooser opened').toBe(0)
      await expect(root(page, def).locator('input[type=file]')).toBeDisabled()
    })
  }

  test(`${route}: uploader_dropzone: the zone, not its disabled button, is what a drag or a click lands on, and shows the not-allowed cursor`, async ({ page }) => {
    const def = caseOf('uploader_dropzone')
    const hit = () => root(page, def).locator('button').evaluate((button) => {
      const box = button.getBoundingClientRect()
      const el = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2)
      const zone = button.closest('[x-data]')
      return { onZone: el === zone, onButton: button.contains(el), cursor: getComputedStyle(el).cursor }
    })

    await openCase(page, def, { route })
    expect(await hit()).toMatchObject({ onZone: false, onButton: true })

    await openCase(page, def, { route, off: true })
    expect(await hit()).toEqual({ onZone: true, onButton: false, cursor: 'not-allowed' })
  })

  test(`${route}: uploader_dropzone: a drop is cancelled, uploads nothing and does not leave the page when disabled`, async ({ page }) => {
    const def = caseOf('uploader_dropzone')
    const drop = (target) => root(page, def).locator(target).first().evaluate((el) => {
      const dt = new DataTransfer()
      dt.items.add(new File(['abc'], 'a.txt', { type: 'text/plain' }))
      const result = {}
      for (const type of ['dragenter', 'dragover', 'drop']) {
        // a script-made DataTransfer ignores a write to dropEffect, so the writes are recorded: the zone says "none" when it is disabled, and says nothing when enabled
        if (type === 'dragover') {
          result.dropEffectWrites = []
          Object.defineProperty(dt, 'dropEffect', { configurable: true, get: () => 'copy', set: (v) => result.dropEffectWrites.push(v) })
        }
        result[type] = !el.dispatchEvent(new DragEvent(type, { dataTransfer: dt, bubbles: true, cancelable: true }))
        if (type === 'dragover') {
          // the zone's highlight and the cursor's drop effect while a file is held over it
          result.highlighted = Alpine.$data(el.closest('[x-data]')).dragging
        }
      }
      return result
    })
    const files = () => root(page, def).locator('input[type=file]').evaluate((el) => el.files.length)

    await openCase(page, def, { route })
    expect(await drop('button')).toEqual({ dragenter: false, dragover: true, highlighted: true, dropEffectWrites: [], drop: true })
    expect(await files(), 'enabled: the drop reaches the input').toBe(1)

    await openCase(page, def, { route, off: true })
    const url = page.url()
    const updates = page.updates.length

    // on the zone, and on the disabled button inside it: every drag event a browser fires for a file from the desktop
    for (const target of ['button', '[x-data]']) {
      expect(await drop(target), `${target}: the page's own handling of a file drop is cancelled, with no drop highlight and no drop cursor`).toEqual({ dragenter: false, dragover: true, highlighted: false, dropEffectWrites: ['none'], drop: true })
    }

    await page.waitForTimeout(1500)
    expect(await files(), 'nothing reached the input').toBe(0)
    expect(page.url()).toBe(url)
    expect(page.updates.length, 'nothing was sent to the server').toBe(updates)
    expect((await serverState(page)).file, 'the server received no file').toBeNull()
    expect(page.errors).toEqual([])
  })
}

// Chromium only: a drop from the desktop through the browser's own input pipeline, which is what a real user's drop is; a disabled <button> is a
// control Chromium has treated differently for mouse events before.
test('uploader_dropzone: a real file drop over the disabled zone is refused or cancelled, and the page stays', async ({ page, browserName }) => {
  test.skip(browserName !== 'chromium', 'Input.dispatchDragEvent is a Chromium protocol call')

  const def = caseOf('uploader_dropzone')
  await openCase(page, def, { route: 'prop', off: true })
  const url = page.url()
  await page.evaluate(() => {
    window.__drops = []
    document.addEventListener('drop', (e) => window.__drops.push(e.defaultPrevented), false)
    document.addEventListener('dragover', (e) => window.__over = e.defaultPrevented, false)
  })

  const box = await root(page, def).locator('button').boundingBox()
  const [x, y] = [box.x + box.width / 2, box.y + box.height / 2]
  const cdp = await page.context().newCDPSession(page)
  const data = { items: [], files: [new URL('../../package.json', import.meta.url).pathname], dragOperationsMask: 1 }
  await cdp.send('Input.dispatchDragEvent', { type: 'dragEnter', x, y, data })
  await cdp.send('Input.dispatchDragEvent', { type: 'dragOver', x, y, data })
  await cdp.send('Input.dispatchDragEvent', { type: 'drop', x, y, data })
  await page.waitForTimeout(500)

  // the zone answers the drag with a cancelled dragover and the drop effect "none", so a browser may refuse the drop outright; one that is delivered is cancelled
  expect(await page.evaluate(() => window.__over), 'the dragover was cancelled').toBe(true)
  expect((await page.evaluate(() => window.__drops)).every(Boolean), 'a drop that reached the page was cancelled').toBe(true)
  expect(page.url(), 'the page did not navigate to the file').toBe(url)
  expect(await root(page, def).locator('input[type=file]').evaluate((el) => el.files.length)).toBe(0)
})
