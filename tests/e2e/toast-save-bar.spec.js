import { test, expect } from '@playwright/test'

// A toast at the bottom of the screen sits ABOVE a pinned save bar (<atom:form.actions bar>) that is on screen, so it never covers the
// Cancel and Save buttons. The page is tests/Fixtures/FormToastBarFixture.php on the consumer's Tailwind (the rig has none; the toast's
// own `p-6` and the bar's classes need it): a form with a pinned bar, a failing submit (the sticky error toast) and a passing one (the
// "Saved" toast). ?no-bar=1 is the baseline, ?short=1 a page that fits the screen, ?inside=1 puts the toast inside the component.

// the Tailwind build the page runs on: the hosts' minimum (4.0.7) and the newest 4.x
let tw = 'latest'

const GAP = 24 // the toast root's own p-6

const open = async (page, query = '') => {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto('/atom/e2e/form-toast-bar' + (query ? query + '&' : '?') + `tw=${tw}`)
  await page.waitForFunction(() => window.Livewire?.first() && window.atom)
  await page.evaluate(() => document.fonts.ready)
  await expect(page.locator('input[data-probe="name"]')).toBeVisible()
}

const shot = async (page, name) => {
  if (process.env.SHOTS) await page.screenshot({ path: `${process.env.SHOTS}/${name}.png` })
}

const toast = (page) => page.locator('[data-atom-toast]')
const panel = (page) => page.locator('[data-atom-toast] > div')
const bar = (page) => page.locator('[data-atom-save-bar]')
const save = (page) => page.locator('[data-submit="main"]')

// the toast scales in over 100ms, and a rect is read through the scale
const settled = (page) => page.waitForFunction(() => document.querySelector('[data-atom-toast]').getAnimations().length === 0)

const rect = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, height: r.height }
})

const overlaps = (a, b) => a.top < b.bottom && b.top < a.bottom && a.left < b.right && b.left < a.right

// The toast panel (not its transparent p-6 root) clear of the bar, and the Save button the topmost thing at its centre. Polled, since a
// lift follows a change a frame later; the toast has to stay open for it (a closed one overlaps nothing).
const expectClear = async (page, label = '') => {
  await expect(toast(page), label).toHaveClass(/opacity-100/)
  await settled(page)

  await expect.poll(async () => {
    if (! await toast(page).evaluate((el) => el.matches(':popover-open'))) return 'closed'
    if (overlaps(await rect(panel(page)), await rect(bar(page)))) return 'on the bar'

    const topmost = await save(page).evaluate((el) => {
      const r = el.getBoundingClientRect()
      const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)

      return hit === el || el.contains(hit)
    })

    return topmost ? 'clear' : 'Save is covered'
  }, { message: `${label}: the toast and the bar` }).toBe('clear')
}

// where the panel's bottom edge is, as the distance from the bottom of the screen
const fromBottom = async (page) => {
  await settled(page)

  return (await page.evaluate(() => document.documentElement.clientHeight)) - (await rect(panel(page))).bottom
}

const failSubmit = async (page) => {
  await save(page).click()
  await expect(panel(page)).toBeVisible()
  await expect(panel(page)).toContainText('Name is required.')
}

for (const build of ['floor', 'latest']) {
  test.describe(`${build} consumer build`, () => {
    test.beforeEach(() => { tw = build })

    for (const [width, height] of [[390, 844], [1440, 900]]) {
      test.describe(`${width}px wide`, () => {
        test.use({ viewport: { width, height } })

        test('the error toast sits above the pinned save bar, and Save can be clicked', async ({ page }) => {
          await open(page)
          await failSubmit(page)
          await expectClear(page)

          // the toast's own padding is the gap above the bar
          const gap = (await rect(bar(page))).top - (await rect(panel(page))).bottom
          expect(Math.abs(gap - GAP)).toBeLessThan(1.5)

          // the bar is a card floating 12px above the bottom of the screen: the lift reads its top edge, so the toast clears the card, not the screen's edge
          const vh = await page.evaluate(() => document.documentElement.clientHeight)
          expect(Math.abs(vh - 12 - (await rect(bar(page))).bottom)).toBeLessThan(1.5)

          await shot(page, `toast-${width}`)

          // the toast is still sticky (a form error is not dismissed by time), and the button works through it
          await page.locator('input[data-probe="name"]').fill('Acme')
          await save(page).click()
          await expect(page.locator('[data-saves]')).toHaveText('1')
        })

        test('the Saved toast does not cover the bar either', async ({ page }) => {
          await open(page)
          await page.locator('input[data-probe="name"]').fill('Acme')
          await save(page).click()

          await expect(panel(page)).toContainText('Saved')
          await expectClear(page, 'Saved')
        })

        test('with no save bar the toast is where it always was', async ({ page }) => {
          await open(page, '?no-bar=1')
          await failSubmit(page)

          expect(await fromBottom(page)).toBeCloseTo(GAP, 0)
          expect(await toast(page).evaluate((el) => el.style.marginBottom)).toBe('')
        })
      })
    }

    test.describe('while the toast is open', () => {
      test.use({ viewport: { width: 1440, height: 900 } })

      test('the bar grows: the toast follows it up, and back down when it shrinks', async ({ page }) => {
        await open(page)
        await failSubmit(page)

        const before = await fromBottom(page)

        await bar(page).evaluate((el) => el.style.paddingTop = '120px')
        await expectClear(page, 'grown')
        await expect.poll(() => fromBottom(page)).toBeGreaterThan(before + 100)

        await bar(page).evaluate((el) => el.style.paddingTop = '')
        await expectClear(page, 'shrunk')
        await expect.poll(async () => Math.abs((await fromBottom(page)) - before)).toBeLessThan(1.5)
      })

      test('a resize that wraps the bar\'s buttons keeps the toast clear', async ({ page }) => {
        await open(page)
        await failSubmit(page)

        for (const size of [{ width: 390, height: 844 }, { width: 320, height: 568 }, { width: 1440, height: 900 }]) {
          await page.setViewportSize(size)
          await expectClear(page, `${size.width}x${size.height}`)
        }
      })

      test('the page scrolling does not leave the toast on the bar', async ({ page }) => {
        await open(page)
        await failSubmit(page)

        for (const y of [300, 99999, 0]) {
          await page.evaluate((y) => window.scrollTo(0, y), y)
          await expectClear(page, `scrolled to ${y}`)
        }
      })

      test('a Livewire round trip keeps the lift (toast beside the component)', async ({ page }) => {
        await open(page)
        await failSubmit(page)

        const lifted = await fromBottom(page)

        expect(lifted).toBeGreaterThan(GAP + 20)

        for (let n = 1; n <= 2; n++) {
          await page.locator('[data-touch]').click()
          await expect(page.locator('[data-touches]')).toHaveText(String(n))
          await expectClear(page, `round trip ${n}`)
          expect(Math.abs((await fromBottom(page)) - lifted)).toBeLessThan(1.5)
        }

        // a resubmit with the error still there closes and shows the toast again, lifted
        const update = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST')
        await save(page).click()
        await update
        await expectClear(page, 'resubmit')
        expect(Math.abs((await fromBottom(page)) - lifted)).toBeLessThan(1.5)
      })

      // A toast rendered inside the component is morphed with it. The morph strips the inline margin the lift wrote (written again by
      // onMorphed) and every class JS added by hand, so the open state is a bound class: it has to come back too.
      test('a Livewire round trip keeps the lift (toast inside the component)', async ({ page }) => {
        await open(page, '?inside=1')
        await failSubmit(page)

        const lifted = await toast(page).evaluate((el) => el.style.marginBottom)

        expect(parseFloat(lifted)).toBeGreaterThan(20)

        for (let n = 1; n <= 2; n++) {
          await page.locator('[data-touch]').click()
          await expect(page.locator('[data-touches]')).toHaveText(String(n))
          await expect.poll(() => toast(page).evaluate((el) => el.style.marginBottom), { message: `round trip ${n}` }).toBe(lifted)
          expect(await toast(page).evaluate((el) => el.matches(':popover-open'))).toBe(true)
          await expect(toast(page), `round trip ${n}: still shown`).toHaveClass(/opacity-100/)
          await expect(toast(page)).toHaveClass(/scale-100/)
          await settled(page)
          expect(await toast(page).evaluate((el) => getComputedStyle(el).opacity), `round trip ${n}: opacity`).toBe('1')
        }
      })

      test('a bar that leaves through a morph lets the toast down, and one that comes lifts it', async ({ page }) => {
        await open(page)
        await failSubmit(page)

        const lifted = await fromBottom(page)

        await page.locator('[data-toggle-bar]').click()
        await expect(bar(page)).toHaveCount(0)
        await expect.poll(() => fromBottom(page)).toBeCloseTo(GAP, 0)

        await page.locator('[data-toggle-bar]').click()
        await expect(bar(page)).toHaveCount(1)
        await expectClear(page, 'bar back')
        await expect.poll(async () => Math.abs((await fromBottom(page)) - lifted)).toBeLessThan(1.5)
      })

      test('a hidden bar (display: none) does not lift it', async ({ page }) => {
        await open(page)
        await bar(page).evaluate((el) => el.style.display = 'none')
        await page.evaluate(() => atom.toast({ message: 'Hello', delay: 0 }))

        await expect(panel(page)).toBeVisible()
        expect(await fromBottom(page)).toBeCloseTo(GAP, 0)
      })

      test('a bar that is not in the toast\'s way (a short page) does not lift it', async ({ page }) => {
        await open(page, '?short=1')

        // the bar is part of the page here, well above the bottom of the screen
        expect((await rect(bar(page))).bottom).toBeLessThan(500)

        await failSubmit(page)

        expect(await fromBottom(page)).toBeCloseTo(GAP, 0)
      })

      test('a toast in the centre is left alone', async ({ page }) => {
        await open(page)
        await page.evaluate(() => atom.toast({ message: 'Middle', position: 'center', delay: 0 }))

        await expect(panel(page)).toBeVisible()
        expect(await toast(page).evaluate((el) => el.style.marginBottom)).toBe('')

        const r = await rect(panel(page))
        expect(Math.abs((r.top + r.bottom) / 2 - 450)).toBeLessThan(2)
      })

      test('a bar so tall that the lift would leave the screen keeps the toast on screen', async ({ page }) => {
        await open(page)
        await bar(page).evaluate((el) => el.style.paddingTop = '2000px')
        await page.evaluate(() => atom.toast({ message: 'Tall', delay: 0 }))

        await expect(panel(page)).toBeVisible()
        expect((await rect(panel(page))).top).toBeGreaterThanOrEqual(0)
      })

        // what the page has live: timers set with a delay only this spec uses, and the capture-phase scroll listeners
      const spy = (page) => page.addInitScript(() => {
        const live = new Set()
        const [set, clear] = [window.setTimeout.bind(window), window.clearTimeout.bind(window)]
        const seen = { scroll: new Set(), close: new Set(), opened: new Set() }
        const [add, remove] = [window.addEventListener.bind(window), window.removeEventListener.bind(window)]
        const captures = (o) => o === true || o?.capture === true

        window.setTimeout = (fn, ms, ...rest) => {
          const id = set(() => { live.delete(id); fn() }, ms, ...rest)

          if (ms === 7777) live.add(id)

          return id
        }
        window.clearTimeout = (id) => { live.delete(id); return clear(id) }
        window.addEventListener = (type, fn, o) => { if (seen[type] && captures(o)) seen[type].add(fn); return add(type, fn, o) }
        window.removeEventListener = (type, fn, o) => { if (seen[type] && captures(o)) seen[type].delete(fn); return remove(type, fn, o) }
        window.__live = () => ({ timers: live.size, scrolls: seen.scroll.size, closes: seen.close.size, opens: seen.opened.size })
      })

      const live = (page) => page.evaluate(() => window.__live())
      const frames = (page) => page.evaluate(() => new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done))))

      test('two toasts shown in one tick leave one timer and one lift, and the next toast is not lifted by a leftover', async ({ page }) => {
        await spy(page)
        await open(page)

        const idle = await live(page)

        await page.evaluate(() => {
          atom.toast({ message: 'first', delay: 7777 })
          atom.toast({ message: 'second', delay: 7777 })
        })
        await expect(panel(page)).toContainText('second')
        await expectClear(page, 'second')

        const open2 = await live(page)

        expect(open2.timers, 'one timer is live').toBe(1)
        expect(open2.scrolls - idle.scrolls, 'one lift is watching').toBe(1)
        expect(open2.closes - idle.closes).toBe(1)
        expect(open2.opens - idle.opens).toBe(1)

        await page.locator('[data-atom-toast] button[aria-label]').click()
        await expect(panel(page)).toBeHidden()

        const closed = await live(page)

        expect(closed.timers).toBe(0)
        expect(closed).toEqual({ ...idle, timers: 0 })

        // a toast in the centre, and a resize: a leftover watcher of the first one would lift it
        await page.evaluate(() => atom.toast({ message: 'Middle', position: 'center', delay: 0 }))
        await expect(panel(page)).toBeVisible()
        await page.setViewportSize({ width: 1300, height: 900 })
        await page.evaluate(() => dispatchEvent(new Event('resize')))
        await frames(page)

        expect(await toast(page).evaluate((el) => el.style.marginBottom)).toBe('')
      })

      for (const inside of [false, true]) {
        test(`a toast that leaves the page stops its timer and its watcher (${inside ? 'swapped away with its component' : 'node removed'})`, async ({ page }) => {
          await spy(page)
          await open(page, inside ? '?inside=1' : '')

          const idle = await live(page)

          await page.evaluate(() => atom.toast({ message: 'Going', delay: 7777 }))
          await expect(panel(page)).toContainText('Going')
          await expectClear(page, 'open')

          const shown = await live(page)

          expect(shown.timers).toBe(1)
          expect(shown.scrolls - idle.scrolls).toBe(1)
          expect(shown.closes - idle.closes).toBe(1)

          if (inside) await page.locator('[data-toggle-toast]').click()
          else await toast(page).evaluate((el) => el.remove())

          await expect(toast(page)).toHaveCount(0)
          await expect.poll(() => live(page), { message: 'nothing left running' }).toEqual({ ...idle, timers: 0 })

          // and scrolling or resizing the page runs nothing on the removed root
          await page.evaluate(() => { dispatchEvent(new Event('resize')); window.scrollTo(0, 50) })
          await frames(page)
          expect(await live(page)).toEqual({ ...idle, timers: 0 })
        })
      }

      test('a bar behind an open modal does not lift a toast', async ({ page }) => {
        await open(page)
        await page.evaluate(() => atom.modal('over').show())
        await expect(page.getByTestId('over')).toBeVisible()
        await page.evaluate(() => atom.toast({ message: 'Over the modal', delay: 0 }))

        await expect(panel(page)).toBeVisible()
        expect(await fromBottom(page)).toBeCloseTo(GAP, 0)
        expect(await toast(page).evaluate((el) => el.style.marginBottom)).toBe('')
      })

      test('a modal that closes under an open toast lets it find the bar, and one that opens lets it go', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 })
        await spy(page)
        await open(page)

        const idle = await live(page)

        await page.evaluate(() => atom.modal('over').show())
        await expect(page.getByTestId('over')).toBeVisible()
        await page.evaluate(() => atom.toast({ message: 'Over the modal', delay: 0 }))

        // the toast opened over a modal: the bar behind it is out of reach, so no lift
        await expect(panel(page)).toBeVisible()
        await settled(page)
        expect(await fromBottom(page)).toBeCloseTo(GAP, 0)
        expect(await toast(page).evaluate((el) => el.style.marginBottom)).toBe('')

        // the modal closes, the toast stays: it must come up off the bar now
        await page.evaluate(() => atom.modal('over').close())
        await expect(page.getByTestId('over')).toBeHidden()
        await expectClear(page, 'modal closed')
        expect(await fromBottom(page)).toBeGreaterThan(GAP + 20)

        // and a modal that opens under it puts it back where it was
        await page.evaluate(() => atom.modal('over').show())
        await expect(page.getByTestId('over')).toBeVisible()
        await expect.poll(() => fromBottom(page), { message: 'modal opened' }).toBeCloseTo(GAP, 0)

        await page.evaluate(() => atom.modal('over').close())
        await expectClear(page, 'modal closed again')

        // the watchers go with the toast
        await page.locator('[data-atom-toast] button[aria-label]').click()
        await expect(panel(page)).toBeHidden()
        expect(await live(page)).toEqual({ ...idle, timers: 0 })
      })

      test('a bar under an [inert] ancestor does not lift a toast', async ({ page }) => {
        await open(page)
        await bar(page).evaluate((el) => el.parentElement.setAttribute('inert', ''))
        await page.evaluate(() => atom.toast({ message: 'Inert', delay: 0 }))

        await expect(panel(page)).toBeVisible()
        expect(await fromBottom(page)).toBeCloseTo(GAP, 0)
      })

      test('closing the toast puts nothing behind', async ({ page }) => {
        await open(page)
        await failSubmit(page)
        await page.locator('[data-atom-toast] button[aria-label]').click()

        await expect(panel(page)).toBeHidden()
        expect(await toast(page).evaluate((el) => el.style.marginBottom)).toBe('')
      })
    })
  })
}
