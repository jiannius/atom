import { test, expect } from '@playwright/test'

// A settings screen: <atom:page.columns> with a `nav` slot, in the sidebar shell (/atom/e2e/shell-settings, a Livewire component: eleven nav items, the second
// group expandable and collapsed, the last item current, a form with `form.actions bar dirty`). Beside the forms the nav is a sticky 200px column and the
// save bar spans nav and forms; below 768px of row the nav is a strip above them and the bar is the plain bar. On the consumer's Tailwind (the rig has none),
// at the newest 4.x (latest) and the hosts' minimum (floor).

const GAP = 22
const NAV = 200
const PAD = 16

const open = async (page, { width, height = 800, tw = 'latest', query = '' }) => {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.setViewportSize({ width, height })
  await page.addInitScript(() => { try { window.localStorage.removeItem('atom-sidebar-collapsed') } catch {} })
  await page.goto(`/atom/e2e/shell-settings?tw=${tw}${query}`)
  await page.evaluate(() => document.fonts.ready)
  await page.waitForFunction(() => window.Alpine !== undefined && window.Livewire !== undefined)
}

const box = (locator) => locator.evaluate((el) => {
  const r = el.getBoundingClientRect()
  return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom }
})

const strip = (page) => page.locator('[data-atom-page-columns-nav] [data-atom-navlist]')
const items = (page) => page.locator('[data-atom-page-columns-nav] [data-atom-navlist-item]')
const current = (page) => page.locator('[data-atom-page-columns-nav] [aria-current="page"]')
const inside = (item) => item.evaluate((el) => {
  const r = el.getBoundingClientRect()
  const s = el.closest('[data-atom-navlist]').getBoundingClientRect()

  return r.left >= s.left - 0.5 && r.right <= s.right + 0.5
})
const noSideScroll = (page) => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)

const wire = (page, call) => page.evaluate((c) => {
  const $wire = window.Livewire.all().find((component) => component.name === 'atom-e2e-settings-page').$wire

  return $wire[c.method](...(c.args ?? []))
}, call)
// the width a probe takes from `--atom-page-nav-offset` where it is put: the offset as a number, whatever calc() it was written as
const offsetIn = (locator) => locator.evaluate((el) => {
  const probe = document.createElement('div')

  probe.style.cssText = 'width: var(--atom-page-nav-offset, 0px); height: 1px'
  el.appendChild(probe)

  const width = probe.getBoundingClientRect().width

  probe.remove()

  return width
})

for (const tw of ['latest', 'floor']) {
  test.describe(`settings page, ${tw} consumer build`, () => {
    test('1440px: the nav is a column beside the forms, and the save bar spans both with its content where the forms start', async ({ page }) => {
      await open(page, { width: 1440, tw })

      const nav = await box(strip(page))
      const main = await box(page.locator('[data-atom-page-columns-main]'))
      const bar = await box(page.getByTestId('settings-bar'))
      const start = await box(page.getByTestId('settings-start'))
      const cancel = await box(page.getByTestId('settings-cancel'))
      const save = await box(page.getByTestId('settings-save'))

      expect(Math.abs(nav.width - NAV), `nav ${nav.width}`).toBeLessThanOrEqual(0.5)
      expect(Math.abs(main.x - nav.right - GAP)).toBeLessThanOrEqual(1)
      expect(nav.y, 'the nav is on the forms\' top line').toBeLessThanOrEqual(main.y + 1)

      // the bar: starts where the nav starts, ends where the forms end; a 1px border sits inside its edge, then the 16px of padding
      expect(Math.abs(bar.x - nav.x), 'the bar starts at the nav').toBeLessThanOrEqual(1)
      expect(Math.abs(bar.right - main.right), 'the bar ends at the forms').toBeLessThanOrEqual(1)
      expect(Math.abs(start.x - (main.x + PAD + 1)), 'its label starts where it would in a bar with no nav').toBeLessThanOrEqual(1)
      expect(Math.abs(cancel.right - (main.right - PAD - 1)), 'its buttons end there too').toBeLessThanOrEqual(1)
      expect(save.right).toBeLessThanOrEqual(cancel.x)
      expect(await noSideScroll(page)).toBe(true)
    })

    test('the nav stays in view while the page scrolls: 1rem under the top of the page (the shell\'s bar is not drawn)', async ({ page }) => {
      await open(page, { width: 1440, tw, height: 700 })

      for (const y of [300, 900, 1500]) {
        await page.evaluate((to) => window.scrollTo(0, to), y)
        await page.waitForTimeout(50)

        // the column's box is 1rem under the top less its 6px of bleed (the content is 1rem under it)
        const nav = await box(page.locator('[data-atom-page-columns-nav]'))

        expect(Math.abs(nav.y - 10), `scrolled ${y}: nav top ${nav.y}`).toBeLessThanOrEqual(1)
      }

      expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(0)
    })

    test('390px: the nav is a strip above the forms, in one row with the current item inside it, and the bar is the plain bar', async ({ page }) => {
      await open(page, { width: 390, tw })

      const nav = await box(page.locator('[data-atom-page-columns-nav]'))
      const main = await box(page.locator('[data-atom-page-columns-main]'))
      const bar = await box(page.getByTestId('settings-bar'))
      const start = await box(page.getByTestId('settings-start'))

      expect(nav.bottom, 'the nav is above the forms').toBeLessThanOrEqual(main.y + 1)
      expect(Math.abs(main.y - nav.bottom - GAP)).toBeLessThanOrEqual(1)

      // eleven items, one row
      const tops = await items(page).evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().top)))

      expect(tops).toHaveLength(11)
      expect(new Set(tops).size, `tops ${tops}`).toBe(1)

      // no group label or disclosure, the collapsed group's items shown
      for (const el of await page.locator('[data-atom-page-columns-nav] [data-atom-navlist-group-label], [data-atom-page-columns-nav] [data-atom-navlist-group-toggle]').all()) await expect(el).toBeHidden()
      await expect(page.getByTestId('nav-members')).toBeVisible()

      // it scrolls sideways, and the current item (the last) was scrolled into view, by the strip alone
      const state = await strip(page).evaluate((el) => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth, scrollLeft: el.scrollLeft }))

      expect(state.scrollWidth).toBeGreaterThan(state.clientWidth + 100)
      expect(state.scrollLeft).toBeGreaterThan(0)
      await expect(current(page)).toHaveCount(1)
      expect(await inside(current(page))).toBe(true)
      expect(await page.evaluate(() => [window.scrollX, window.scrollY])).toEqual([0, 0])
      expect(await noSideScroll(page)).toBe(true)

      // the bar has no offset: the card is the forms' width, the label inside its padding
      expect(Math.abs(bar.x - main.x)).toBeLessThanOrEqual(1)
      expect(Math.abs(bar.right - main.right)).toBeLessThanOrEqual(1)
      expect(Math.abs(start.x - (main.x + PAD + 1))).toBeLessThanOrEqual(1)
    })

    test('a short window: the pinned save bar does not cover the last item of a tall nav', async ({ page }) => {
      await open(page, { width: 1440, height: 360, tw })

      // the second group's items are counted too
      await page.locator('[data-atom-page-columns-nav] [data-atom-navlist-group-toggle]').click()
      await expect(page.getByTestId('nav-domains')).toBeVisible()

      // stuck under the top of the page (before the page has scrolled the nav is below its stuck place, and the page's scroll brings it up)
      await page.evaluate(() => window.scrollTo(0, 300))
      await page.waitForTimeout(50)

      const column = page.locator('[data-atom-page-columns-nav]')
      const state = await column.evaluate((el) => { el.scrollTop = el.scrollHeight; return { scrollHeight: el.scrollHeight, clientHeight: el.clientHeight, scrollTop: el.scrollTop } })

      expect(state.scrollHeight, 'the nav is taller than the window leaves, so it scrolls').toBeGreaterThan(state.clientHeight + 40)

      const last = await box(page.getByTestId('nav-domains'))
      const bar = await box(page.getByTestId('settings-bar'))

      expect(Math.abs(bar.bottom - (360 - 12)), 'the bar is pinned 0.75rem above the window edge').toBeLessThanOrEqual(1)
      expect(last.bottom, `last item bottom ${last.bottom}, bar top ${bar.y}`).toBeLessThanOrEqual(bar.y)
    })

    test('rtl, 1440px: the nav is on the inline-start (right) side, and the save bar spans both with its content where the forms start', async ({ page }) => {
      await open(page, { width: 1440, tw, query: '&dir=rtl' })

      expect(await page.evaluate(() => document.documentElement.dir)).toBe('rtl')

      const nav = await box(strip(page))
      const main = await box(page.locator('[data-atom-page-columns-main]'))
      const bar = await box(page.getByTestId('settings-bar'))
      const start = await box(page.getByTestId('settings-start'))
      const cancel = await box(page.getByTestId('settings-cancel'))
      const save = await box(page.getByTestId('settings-save'))

      expect(Math.abs(nav.width - NAV), `nav ${nav.width}`).toBeLessThanOrEqual(0.5)
      expect(nav.x, 'the nav is on the right of the forms').toBeGreaterThanOrEqual(main.right)
      expect(Math.abs(nav.x - main.right - GAP)).toBeLessThanOrEqual(1)
      expect(nav.y, 'the nav is on the forms\' top line').toBeLessThanOrEqual(main.y + 1)

      // mirrored: the bar ends where the nav ends (its right edge) and starts where the forms start (their left edge)
      expect(Math.abs(bar.right - nav.right), 'the bar reaches the nav\'s outer edge').toBeLessThanOrEqual(1)
      expect(Math.abs(bar.x - main.x), 'the bar ends at the forms\' far edge').toBeLessThanOrEqual(1)
      expect(Math.abs(start.right - (main.right - PAD - 1)), 'its label starts at the forms\' inline-start edge (right), not under the nav').toBeLessThanOrEqual(1)
      expect(Math.abs(cancel.x - (main.x + PAD + 1)), 'its buttons end at the forms\' inline-end edge (left)').toBeLessThanOrEqual(1)
      expect(save.x).toBeGreaterThanOrEqual(cancel.right)
      expect(await noSideScroll(page)).toBe(true)
    })

    test('rtl, 390px: the strip scrolls, the current item (last of the list, so at the far left) is revealed inside it, and the page does not scroll', async ({ page }) => {
      await open(page, { width: 390, tw, query: '&dir=rtl' })

      const nav = await box(page.locator('[data-atom-page-columns-nav]'))
      const main = await box(page.locator('[data-atom-page-columns-main]'))
      const bar = await box(page.getByTestId('settings-bar'))
      const start = await box(page.getByTestId('settings-start'))

      expect(Math.abs(main.y - nav.bottom - GAP)).toBeLessThanOrEqual(1)

      const state = await strip(page).evaluate((el) => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth, scrollLeft: el.scrollLeft }))

      expect(state.scrollWidth).toBeGreaterThan(state.clientWidth + 100)
      // RTL scrollLeft is signed (zero at the start edge, negative as it scrolls): only its size says the strip moved
      expect(Math.abs(state.scrollLeft)).toBeGreaterThan(0)
      await expect(current(page)).toHaveCount(1)
      await expect(current(page)).toHaveText('Domains')
      expect(await inside(current(page))).toBe(true)
      expect(await page.evaluate(() => [window.scrollX, window.scrollY])).toEqual([0, 0])
      expect(await noSideScroll(page)).toBe(true)

      // the plain bar: the forms' width, the label at its padding edge (the right one)
      expect(Math.abs(bar.x - main.x)).toBeLessThanOrEqual(1)
      expect(Math.abs(bar.right - main.right)).toBeLessThanOrEqual(1)
      expect(Math.abs(start.right - (main.right - PAD - 1))).toBeLessThanOrEqual(1)

      // scrolled back to where the row starts, the first item is at the right edge and the current one (the last, at the left) is out of the strip
      await strip(page).evaluate((el) => { el.scrollLeft = 0 })

      const room = await box(strip(page))

      expect(Math.abs((await box(items(page).first())).right - room.right), 'the row starts at the right').toBeLessThanOrEqual(8)
      expect(await inside(current(page))).toBe(false)
      await page.evaluate(() => document.dispatchEvent(new Event('livewire:navigated')))
      await expect.poll(() => inside(current(page))).toBe(true)
      expect(await page.evaluate(() => [window.scrollX, window.scrollY])).toEqual([0, 0])
    })

    test('a px-* or mx-* a host puts on the bar still wins, with the nav beside the forms and without', async ({ page }) => {
      for (const width of [1440, 390]) {
        await open(page, { width, tw })
        await page.getByTestId('settings-bar').evaluate((el) => el.classList.add('px-6'))

        const style = await page.getByTestId('settings-bar').evaluate((el) => { const c = getComputedStyle(el); return { left: c.paddingLeft, right: c.paddingRight } })

        expect(style, `${width}px`).toEqual({ left: '24px', right: '24px' })

        // the label stays where the forms' content starts (the bar's 1px border, then its own padding)
        const main = await box(page.locator('[data-atom-page-columns-main]'))
        const start = await box(page.getByTestId('settings-start'))
        const cancel = await box(page.getByTestId('settings-cancel'))

        expect(Math.abs(start.x - (main.x + 24 + 1)), `${width}px label`).toBeLessThanOrEqual(1)
        expect(Math.abs(cancel.right - (main.right - 24 - 1)), `${width}px buttons`).toBeLessThanOrEqual(1)
      }

      // a margin utility of the host's on the bar beats the pull, and the label's own margin does not know it lost (documented): the label is
      // 222px right of where it would be; `[--atom-page-nav-offset:0px]` on the same bar clears both
      await open(page, { width: 1440, tw })

      const bar = page.getByTestId('settings-bar')
      const where = async () => {
        const b = await box(bar)
        const s = await box(page.getByTestId('settings-start'))
        const m = await box(page.locator('[data-atom-page-columns-main]'))

        return { bar: b, label: s.x - b.x, main: m }
      }

      await bar.evaluate((el) => el.classList.add('ms-2'))
      expect(await bar.evaluate((el) => getComputedStyle(el).marginInlineStart)).toBe('8px')

      const lost = await where()

      expect(Math.abs(lost.bar.x - (lost.main.x + 8)), 'the host margin replaced the pull').toBeLessThanOrEqual(1)
      expect(Math.abs(lost.label - (PAD + 1 + NAV + GAP)), 'the label keeps its offset: the documented trap').toBeLessThanOrEqual(1)

      await bar.evaluate((el) => el.classList.add('[--atom-page-nav-offset:0px]'))

      const cleared = await where()

      expect(Math.abs(cleared.bar.x - (cleared.main.x + 8))).toBeLessThanOrEqual(1)
      expect(Math.abs(cleared.label - (PAD + 1)), 'one variable on the bar clears the pull and the label together').toBeLessThanOrEqual(1)

      // a margin utility on the start slot replaces the label's margin: the offset written into it keeps the label in place
      await open(page, { width: 1440, tw })
      await page.locator('[data-atom-form-actions-start]').first().evaluate((el) => el.classList.add('ms-[calc(var(--atom-page-nav-offset,0px)+1rem)]'))

      const slot = await where()

      expect(Math.abs(slot.label - (PAD + 1 + NAV + GAP + 16))).toBeLessThanOrEqual(1)
    })
  })
}

test.describe('settings page, the strip and a Livewire round trip', () => {
  test('a morph that moves the current item brings it into the strip, and the page stays where it was', async ({ page }) => {
    await open(page, { width: 390, query: '&section=profile' })

    const profile = page.getByTestId('nav-profile')

    await expect(profile).toHaveAttribute('aria-current', 'page')
    expect(await inside(profile)).toBe(true)
    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBe(0)

    await page.evaluate(() => window.scrollTo(0, 120))
    await page.evaluate(() => window.Livewire.all().find((c) => c.name === 'atom-e2e-settings-page').$wire.select('domains'))

    await expect(page.getByTestId('nav-domains')).toHaveAttribute('aria-current', 'page')
    await expect.poll(() => inside(page.getByTestId('nav-domains'))).toBe(true)
    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)
    expect(await page.evaluate(() => [window.scrollX, window.scrollY])).toEqual([0, 120])

    // and back: the first item is in view again
    await page.evaluate(() => window.Livewire.all().find((c) => c.name === 'atom-e2e-settings-page').$wire.select('profile'))
    await expect(profile).toHaveAttribute('aria-current', 'page')
    await expect.poll(() => inside(profile)).toBe(true)
  })

  test('a morph that leaves the current item alone does not scroll back a strip the reader moved', async ({ page }) => {
    await open(page, { width: 390 })

    await expect.poll(() => strip(page).evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)

    await strip(page).evaluate((el) => { el.dispatchEvent(new Event('pointerdown')); el.scrollLeft = 0 })
    await page.evaluate(() => window.Livewire.all().find((c) => c.name === 'atom-e2e-settings-page').$wire.$refresh())
    await page.waitForTimeout(300)

    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBe(0)
  })

  test('a gesture on other content of the slot is not the reader scrolling the strip: a resize still brings the current item into view', async ({ page }) => {
    await open(page, { width: 390 })

    await expect.poll(() => strip(page).evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)

    // a search field a host put above the navlist, used; and the strip scrolled back by the page, not the reader
    await page.locator('[data-atom-page-columns-nav]').evaluate((el) => {
      const field = document.createElement('input')

      field.dataset.testid = 'slot-search'
      el.insertBefore(field, el.firstChild)
    })
    await page.getByTestId('slot-search').click()
    await page.keyboard.type('abc')
    await strip(page).evaluate((el) => { el.scrollLeft = 0 })
    await page.setViewportSize({ width: 380, height: 800 })

    await expect.poll(() => inside(current(page))).toBe(true)
    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)
  })

  test('a morph that moves the current item brings it into view even after the reader scrolled the strip', async ({ page }) => {
    await open(page, { width: 390, query: '&section=profile' })

    // the reader's gesture on the strip (it stops the strip being scrolled back by a morph that changes nothing)
    await strip(page).evaluate((el) => el.dispatchEvent(new Event('pointerdown')))
    await page.evaluate(() => window.Livewire.all().find((c) => c.name === 'atom-e2e-settings-page').$wire.select('domains'))

    await expect(page.getByTestId('nav-domains')).toHaveAttribute('aria-current', 'page')
    await expect.poll(() => inside(page.getByTestId('nav-domains'))).toBe(true)
  })

  test('the collapsed group opens and closes beside the forms, and the strip needs no disclosure', async ({ page }) => {
    await open(page, { width: 1440 })

    await expect(page.getByTestId('nav-general')).toBeHidden()
    await page.locator('[data-atom-page-columns-nav] [data-atom-navlist-group-toggle]').click()
    await expect(page.getByTestId('nav-general')).toBeVisible()

    // narrow the window: the same group's items are in the strip whichever way it was left
    await page.setViewportSize({ width: 390, height: 800 })
    await expect(page.getByTestId('nav-general')).toBeVisible()
    await page.setViewportSize({ width: 1440, height: 800 })
    await expect(page.getByTestId('nav-general')).toBeVisible()
  })

  test('a round trip that submits the form keeps the nav, the bar and the offset', async ({ page }) => {
    await open(page, { width: 1440 })

    await page.getByTestId('settings-save').click()
    await expect(page.getByTestId('settings-start')).toHaveText('Saved 1 times')

    const nav = await box(strip(page))
    const bar = await box(page.getByTestId('settings-bar'))

    expect(Math.abs(bar.x - nav.x)).toBeLessThanOrEqual(1)
  })
})

// A real `wire:navigate`: the nav items are links (?links=1) to this same screen with another `section`, so Livewire fetches the page and swaps the body. The
// slot of the new page is a new element with its own factory (init() runs, the old one's destroy() ran), and the spec above that dispatches a synthetic
// `livewire:navigated` cannot tell a factory that listens for the real one from one that does not.
test.describe('settings page, a real wire:navigate between two settings pages', () => {
  test('the new page\'s current item is in its strip, the page is not scrolled sideways, and nothing is listening twice after three navigations', async ({ page }) => {
    await page.addInitScript(() => {
      window.__listeners = { document: { add: 0, remove: 0 }, slot: { add: {}, remove: {} } }

      for (const [name, bucket] of [['addEventListener', 'add'], ['removeEventListener', 'remove']]) {
        const original = EventTarget.prototype[name]

        EventTarget.prototype[name] = function (type, ...rest) {
          if (this === document && type === 'livewire:navigated') window.__listeners.document[bucket]++

          if (this instanceof Element && this.hasAttribute('data-atom-page-columns-nav')) {
            window.__listeners.slot[bucket][type] = (window.__listeners.slot[bucket][type] ?? 0) + 1
          }

          return original.call(this, type, ...rest)
        }
      }
    })
    await open(page, { width: 390, query: '&links=1&section=profile' })

    // a mark on the document: a full page load would lose it, a wire:navigate keeps it
    await page.evaluate(() => { window.__sameDocument = true })
    await expect(page.getByTestId('nav-profile')).toHaveAttribute('aria-current', 'page')
    await expect.poll(() => inside(page.getByTestId('nav-profile'))).toBe(true)
    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBe(0)

    const tags = ['wheel', 'touchstart', 'pointerdown', 'keydown', 'focusin']
    const live = () => page.evaluate((types) => {
      const l = window.__listeners

      return {
        document: l.document.add - l.document.remove,
        slot: Object.fromEntries(types.map((type) => [type, (l.slot.add[type] ?? 0) - (l.slot.remove[type] ?? 0)])),
        added: l.document.add,
      }
    }, tags)

    const first = await live()

    expect(first.document, 'the first page\'s factory listens once').toBeGreaterThanOrEqual(1)
    for (const type of tags) expect(first.slot[type], type).toBe(1)

    // profile -> domains (the last item: the strip has to scroll to show it), domains -> profile (back to the first), profile -> domains
    for (const [from, to] of [['profile', 'domains'], ['domains', 'profile'], ['profile', 'domains']]) {
      await page.evaluate((id) => { document.querySelector('[data-atom-page-columns-nav]').dataset.old = id }, from)
      await page.getByTestId(`nav-${to}`).click()
      await expect(page).toHaveURL(new RegExp(`section=${to}`))
      await expect(page.getByTestId(`nav-${to}`)).toHaveAttribute('aria-current', 'page')

      // the body was swapped for the new page's (a new slot element, not the old one carried over), and the document was not reloaded
      await expect.poll(() => page.locator('[data-atom-page-columns-nav]').evaluate((el) => el.dataset.old ?? 'new')).toBe('new')
      expect(await page.evaluate(() => window.__sameDocument === true), 'a wire:navigate, not a page load').toBe(true)

      await expect.poll(() => inside(page.getByTestId(`nav-${to}`)), `${from} -> ${to}`).toBe(true)

      const left = await strip(page).evaluate((el) => el.scrollLeft)

      if (to === 'domains') expect(left, 'the strip was scrolled to the last item').toBeGreaterThan(0)
      else expect(left, 'the first item needs no scrolling').toBe(0)

      expect(await page.evaluate(() => window.scrollX), 'the page is not scrolled sideways').toBe(0)
      expect(await noSideScroll(page)).toBe(true)
    }

    // each navigation made a new factory (listeners were added) and the old one's destroy() took its own away: the live count is where it began
    await expect.poll(async () => (await live()).document).toBe(first.document)
    await expect.poll(async () => (await live()).slot).toEqual(first.slot)
    expect((await live()).added, 'the factories did listen again, once per page').toBeGreaterThanOrEqual(first.added + 3)
  })
})

test.describe('settings page, what the offset and the strip rules do not reach', () => {
  test('the offset is the nav and the gap, and a host\'s own width or gap on the row moves it with them', async ({ page }) => {
    await open(page, { width: 1440 })

    const row = page.locator('[data-atom-page-columns]')

    expect(await offsetIn(page.locator('[data-atom-page-columns-main]'))).toBe(NAV + GAP)

    await row.evaluate((el) => { el.style.setProperty('--atom-page-nav-width', '240px'); el.style.setProperty('--atom-page-columns-gap', '30px') })

    const nav = await box(strip(page))
    const main = await box(page.locator('[data-atom-page-columns-main]'))
    const bar = await box(page.getByTestId('settings-bar'))
    const start = await box(page.getByTestId('settings-start'))

    expect(Math.abs(nav.width - 240)).toBeLessThanOrEqual(0.5)
    expect(Math.abs(main.x - nav.right - 30)).toBeLessThanOrEqual(1)
    expect(await offsetIn(page.locator('[data-atom-page-columns-main]'))).toBe(270)
    expect(Math.abs(bar.x - nav.x), 'the bar still starts at the nav').toBeLessThanOrEqual(1)
    expect(Math.abs(start.x - (main.x + PAD + 1)), 'its label still starts where the forms do').toBeLessThanOrEqual(1)
  })

  test('a dialog, a popover and a nested rail inside the main column get no offset, and a bar in a modal is the plain bar', async ({ page }) => {
    await open(page, { width: 1440 })

    const main = page.locator('[data-atom-page-columns-main]')

    expect(await offsetIn(main)).toBe(NAV + GAP)

    for (const tag of ['<dialog></dialog>', '<div popover></div>', '<div data-atom-page-columns-rail></div>', '<div data-atom-page-columns-nav></div>']) {
      const width = await main.evaluate((el, html) => {
        const holder = document.createElement('div')

        holder.innerHTML = html
        el.appendChild(holder)

        const probe = document.createElement('div')

        probe.style.cssText = 'width: var(--atom-page-nav-offset, 0px); height: 1px'
        holder.firstElementChild.appendChild(probe)

        // a closed dialog or popover has no box: open it, or the probe measures nothing whatever the offset is
        holder.firstElementChild.show?.()
        if (holder.firstElementChild.hasAttribute('popover')) holder.firstElementChild.showPopover()

        const w = probe.getBoundingClientRect().width

        holder.remove()

        return w
      }, tag)

      expect(width, tag).toBe(0)
    }

    // the real thing: a save bar in an atom modal in the main column
    await page.evaluate(() => window.atom.modal('settings-modal').show())

    const bar = page.getByTestId('modal-bar')

    await expect(bar).toBeVisible()

    expect(await bar.evaluate((el) => getComputedStyle(el).marginInlineStart), 'no pull back under a nav').toBe('0px')
    // (the modal grows into place, so measure once it has stopped)
    await expect.poll(async () => {
      const b = await box(bar)
      const s = await box(page.getByTestId('modal-start'))

      return Math.round(Math.abs(s.x - (b.x + PAD + 1)))
    }).toBeLessThanOrEqual(1)
  })

  test('a container of the host\'s own around the navlist does not turn the column into a strip', async ({ page }) => {
    await open(page, { width: 1440 })

    await strip(page).evaluate((list) => {
      const wrapper = document.createElement('div')

      wrapper.style.cssText = 'container-type: inline-size'
      list.parentNode.insertBefore(wrapper, list)
      wrapper.appendChild(list)
    })

    const direction = await strip(page).evaluate((el) => getComputedStyle(el).flexDirection)
    const labels = await page.locator('[data-atom-page-columns-nav] [data-atom-navlist-group-label]').first().isVisible()

    expect(direction, 'still a column, 200px wide in a container that is narrower than the switch').toBe('column')
    expect(labels).toBe(true)
  })
})

test.describe('settings page, a navlist that is not the one init() saw', () => {
  test('a navlist printed by a later round trip is brought into view like one printed at the start', async ({ page }) => {
    await open(page, { width: 390, query: '&nav=late' })

    await expect(page.getByTestId('nav-pending')).toBeVisible()
    await expect(strip(page)).toHaveCount(0)

    await wire(page, { method: 'showNav' })

    await expect(page.getByTestId('nav-domains')).toHaveAttribute('aria-current', 'page')
    await expect.poll(() => inside(page.getByTestId('nav-domains'))).toBe(true)
    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)
  })

  test('a morph that replaces the navlist element: the new one shows its current item, and the reader\'s gesture on it is kept', async ({ page }) => {
    await open(page, { width: 390 })

    await expect.poll(() => strip(page).evaluate((el) => el.scrollLeft)).toBeGreaterThan(0)
    await strip(page).evaluate((el) => { el.dataset.old = 'yes' })

    await wire(page, { method: 'select', args: ['profile'] })
    await expect(page.getByTestId('nav-profile')).toHaveAttribute('aria-current', 'page')
    await wire(page, { method: 'replaceNav' })

    // a new element: it did not carry the mark
    await expect.poll(() => strip(page).evaluate((el) => el.dataset.old ?? 'no')).toBe('no')
    await expect.poll(() => inside(page.getByTestId('nav-profile'))).toBe(true)

    // move to the last item: the new strip shows it (a factory that kept the old list, which is gone, would not)
    await wire(page, { method: 'select', args: ['domains'] })
    await expect(page.getByTestId('nav-domains')).toHaveAttribute('aria-current', 'page')
    await expect.poll(() => inside(page.getByTestId('nav-domains'))).toBe(true)

    // the reader scrolls the new strip: a morph that changes nothing leaves it there
    await strip(page).evaluate((el) => { el.dispatchEvent(new Event('pointerdown')); el.scrollLeft = 0 })
    await wire(page, { method: '$refresh' })
    await page.waitForTimeout(300)

    expect(await strip(page).evaluate((el) => el.scrollLeft)).toBe(0)
  })

  test('one listener per gesture type on the slot, all of them removed when the slot goes', async ({ page }) => {
    await page.addInitScript(() => {
      window.__listeners = { add: {}, remove: {} }

      for (const [name, bucket] of [['addEventListener', 'add'], ['removeEventListener', 'remove']]) {
        const original = EventTarget.prototype[name]

        EventTarget.prototype[name] = function (type, ...rest) {
          if (this instanceof Element && this.hasAttribute('data-atom-page-columns-nav')) {
            window.__listeners[bucket][type] = (window.__listeners[bucket][type] ?? 0) + 1
          }

          return original.call(this, type, ...rest)
        }
      }
    })
    await open(page, { width: 390 })

    // three round trips (morphs) and the slot is still the same element with the same listeners
    for (const section of ['profile', 'security', 'domains']) {
      await wire(page, { method: 'select', args: [section] })
      await expect(page.getByTestId(`nav-${section}`)).toHaveAttribute('aria-current', 'page')
    }

    const types = ['wheel', 'touchstart', 'pointerdown', 'keydown', 'focusin']
    const added = await page.evaluate(() => window.__listeners.add)

    for (const type of types) expect(added[type], `added ${type}`).toBe(1)

    await page.locator('[data-atom-page-columns-nav]').evaluate((el) => el.remove())
    await expect.poll(() => page.evaluate(() => window.__listeners.remove.pointerdown ?? 0)).toBe(1)

    const removed = await page.evaluate(() => window.__listeners.remove)

    for (const type of types) expect(removed[type], `removed ${type}`).toBe(1)
  })
})
