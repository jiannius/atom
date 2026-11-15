import { test, expect } from '@playwright/test'
import { open, contrastOf } from './controls-helpers.js'

// The card's heading row (`heading`, `actions`, `menu`) and <atom:panel> (fixture: tests/Fixtures/CardPanelFixture.php, page
// resources/views/e2e/card-panel.blade.php): the ⋯ opens from the keyboard and survives a Livewire morph, the row is as tall with a menu as
// without, the panel's title lines up with the first column, the panel clips so the table's pager pins to the screen, a wide table scrolls
// inside it and never the page, and every muted text reads on its own surface in both themes. On the consumer's Tailwind, floor and latest;
// `?tw=none` is no stylesheet at all, for what atom.css alone guarantees.

const card = (page) => page.getByTestId('card')
const panel = (page) => page.getByTestId('panel')
const more = (box) => box.locator('[data-atom-heading-row-actions]').getByRole('button', { name: /^More actions/ })
const menu = (box) => box.locator('[data-atom-heading-row-actions] [data-atom-menu][popover]')
const pager = (page) => page.locator('nav[aria-label]').filter({ has: page.locator('[aria-current="page"]') }).first()
const box = (locator) => locator.evaluate((el) => { const r = el.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, height: r.height, width: r.width } })

const show = async (page, { tw = 'latest', dark = false, extra = '' } = {}) => {
  await open(page, { path: 'card-panel', tw, dark, extra })
  await page.waitForFunction(() => window.Livewire && window.Alpine)
  await expect(card(page)).toBeVisible()
}

for (const tw of ['floor', 'latest']) {
  test.describe(`card heading and panel, ${tw} consumer build`, () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 800 })
    })

    test('the card row: a small muted title at the start, the link and the ⋯ at the end, 12px above the body', async ({ page }) => {
      await show(page, { tw })

      const title = card(page).locator('h2[data-atom-heading-row-title]')
      await expect(title).toHaveText('Customer')
      const xs = await title.evaluate((el) => {
        const probe = Object.assign(document.createElement('span'), { className: 'text-xs' })
        document.body.append(probe)
        const size = getComputedStyle(probe).fontSize
        probe.remove()
        return { size, own: getComputedStyle(el).fontSize }
      })
      // the type scale's smallest step (12px, 13px in the compact density), not a literal
      expect(xs.own).toBe(xs.size)

      const t = await box(title)
      const link = await box(card(page).getByRole('link', { name: 'View all' }))
      const trigger = await box(more(card(page)))
      const c = await box(card(page))
      const body = await box(card(page).getByTestId('card-body'))

      // title at the start of the card's padding, the actions after it, the ⋯ last, at the end
      expect(t.left - c.left).toBeCloseTo(17, 0)
      expect(link.left).toBeGreaterThan(t.right)
      expect(trigger.left).toBeGreaterThan(link.right)
      expect(c.right - trigger.right).toBeLessThan(17 + 8)
      expect(link.height).toBeLessThan(20)

      // the same 12px as a link and the title: both on one line, 12px of space to the body
      expect(await card(page).getByRole('link', { name: 'View all' }).evaluate((el) => getComputedStyle(el).fontSize)).toBe(xs.size)
      expect(Math.abs((t.top + t.bottom) / 2 - (link.top + link.bottom) / 2)).toBeLessThan(2)
      const row = await box(card(page).locator('[data-atom-heading-row]'))
      expect(body.top - row.bottom).toBeCloseTo(12, 0)
    })

    // a host that lays the card's children out itself keeps its own spacing: the row's 12px yields to a gap or a space-y, it does not stack on it
    for (const [host, gap] of [['grid', 12], ['flex', 12], ['space3', 12], ['space6', 24]]) {
      test(`a card the host lays out with ${host === 'grid' ? 'grid gap-3' : host === 'flex' ? 'flex flex-col gap-3' : host === 'space3' ? 'space-y-3' : 'space-y-6'} has the host's ${gap}px between its title and its body`, async ({ page }) => {
        await show(page, { tw, extra: `&host=${host}` })

        const row = await box(card(page).locator('[data-atom-heading-row]'))
        const body = await box(card(page).getByTestId('card-body'))

        expect(body.top - row.bottom).toBeCloseTo(gap, 0)
      })
    }

    test('the ⋯ is named for the box it belongs to', async ({ page }) => {
      await show(page, { tw })

      await expect(card(page).getByRole('button', { name: 'More actions for Customer' })).toBeVisible()
      await expect(panel(page).getByRole('button', { name: 'More actions for Invoices' })).toBeVisible()
    })

    test('a row with a ⋯ is no taller than one without, in the card and in the panel', async ({ page }) => {
      await show(page, { tw })
      const withCard = (await box(card(page).locator('[data-atom-heading-row]'))).height
      const withPanel = (await box(panel(page).locator('[data-atom-heading-row]'))).height

      await show(page, { tw, extra: '&menus=0' })
      await expect(more(card(page))).toHaveCount(0)
      const withoutCard = (await box(card(page).locator('[data-atom-heading-row]'))).height
      const withoutPanel = (await box(panel(page).locator('[data-atom-heading-row]'))).height

      expect(withCard).toBeCloseTo(withoutCard, 0)
      expect(withPanel).toBeCloseTo(withoutPanel, 0)
    })

    for (const which of ['card', 'panel']) {
      const target = (page) => (which === 'card' ? card(page) : panel(page))

      test(`the ${which}'s ⋯ opens from the keyboard, is not clipped, and Escape closes it`, async ({ page }) => {
        await show(page, { tw })

        await more(target(page)).focus()
        await page.keyboard.press('Enter')
        await expect(menu(target(page))).toBeVisible()
        await expect(menu(target(page)).getByRole('menuitem', { name: 'Export' })).toBeVisible()

        // the popover sits in the top layer: it hangs out of the clipping box instead of being cut by it
        const m = await box(menu(target(page)))
        const t = await box(target(page))
        expect(m.width).toBeGreaterThan(150)
        expect(m.bottom).toBeGreaterThan(t.top)

        await page.keyboard.press('Escape')
        await expect(menu(target(page))).toBeHidden()
      })

      test(`an open ${which} menu survives a Livewire morph`, async ({ page }) => {
        await show(page, { tw })

        await more(target(page)).click()
        await expect(menu(target(page))).toBeVisible()

        await page.evaluate(() => window.Livewire.first().call('bump'))
        await expect(page.getByTestId('bump')).toHaveText('Bump 1')

        await expect(menu(target(page))).toBeVisible()
        await expect(more(target(page))).toHaveAttribute('aria-expanded', 'true')

        // and the item still works after it
        await menu(target(page)).getByRole('menuitem', { name: `Bump from the ${which}` }).click()
        await expect(page.getByTestId('bump')).toHaveText('Bump 2')
      })
    }

    test('the panel: its title lines up with the first column, its count and footer sit at the same edge', async ({ page }) => {
      await show(page, { tw })

      const title = await box(panel(page).locator('h2[data-atom-heading-row-title]'))
      const firstCell = await box(panel(page).locator('[data-atom-table-rows] td').first())
      const footer = await box(panel(page).locator('[data-atom-panel-footer] span').first())

      expect(await panel(page).locator('h2[data-atom-heading-row-title]').evaluate((el) => getComputedStyle(el).fontSize)).toBe('14px')
      expect(Math.abs(title.left - (firstCell.left + 12))).toBeLessThan(1.5)
      expect(Math.abs(footer.left - title.left)).toBeLessThan(1.5)

      // the count reads "128", beside the title, not inside the heading
      await expect(panel(page).locator('[data-atom-heading-row-count]')).toHaveText('128')
      await expect(panel(page).locator('h2')).toHaveText('Invoices')
    })

    test('the panel clips (never hides), so the table\'s pager pins to the bottom of the screen and rests at the end', async ({ page }) => {
      await show(page, { tw })
      await expect(pager(page)).toBeVisible()

      const overflow = await panel(page).evaluate((el) => getComputedStyle(el).overflow)
      expect(overflow).toBe('clip')

      await page.evaluate(() => window.scrollTo(0, 900))
      const b = await box(pager(page))
      const viewport = page.viewportSize().height
      expect(b.bottom).toBeGreaterThan(viewport - 1)
      expect(b.bottom).toBeLessThanOrEqual(viewport + 0.5)
      await expect(page.getByRole('button', { name: 'Go to page 2' })).toBeInViewport()

      // at the end it sits in place, under the last row, above the panel's footer
      await page.evaluate(() => window.scrollTo(0, 100000))
      const p = await box(pager(page))
      const lastRow = await box(page.locator('[data-atom-table-rows] tr').last())
      const footer = await box(panel(page).locator('[data-atom-panel-footer]'))
      expect(p.top).toBeGreaterThanOrEqual(lastRow.bottom - 1)
      expect(footer.top).toBeGreaterThanOrEqual(p.bottom - 1)
    })

    test('a wide table scrolls inside the panel, never the page', async ({ page }) => {
      await page.setViewportSize({ width: 800, height: 800 })
      await show(page, { tw, extra: '&wide=1' })

      const scroll = panel(page).locator('[data-atom-table-scroll]')
      const sizes = await scroll.evaluate((el) => ({ scroll: el.scrollWidth, client: el.clientWidth }))
      expect(sizes.scroll).toBeGreaterThan(sizes.client + 200)

      const doc = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }))
      expect(doc.scroll).toBeLessThanOrEqual(doc.client)

      const p = await box(panel(page))
      expect(p.right).toBeLessThanOrEqual(800)
    })

    for (const dark of [false, true]) {
      test(`${dark ? 'dark' : 'light'}: the card title, the count, the footer and the actions read on their own surface`, async ({ page }) => {
        await show(page, { tw, dark })

        const read = (locator) => locator.evaluate(contrastOf, { what: 'text' })
        const checks = {
          'card title': card(page).locator('[data-atom-heading-row-title]'),
          'card link': card(page).getByRole('link', { name: 'View all' }),
          'panel title': panel(page).locator('[data-atom-heading-row-title]'),
          'panel count': panel(page).locator('[data-atom-heading-row-count]'),
          'panel link': panel(page).getByRole('link', { name: 'View all' }),
          'panel footer': panel(page).locator('[data-atom-panel-footer] span').first(),
        }

        for (const [name, locator] of Object.entries(checks)) {
          const found = await read(locator)
          expect(found.ratio, `${name}: ${found.colour} on ${found.back}`).toBeGreaterThanOrEqual(4.5)
        }
      })
    }
  })
}

test('with no stylesheet at all the panel still clips (the rule is in atom.css)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 800 })
  await page.goto('/atom/e2e/card-panel?tw=none')
  await page.waitForFunction(() => window.Livewire && window.Alpine)

  expect(await panel(page).evaluate((el) => getComputedStyle(el).overflow)).toBe('clip')
  // the pager's own `sticky` is a utility, so only the box's clip is the rig's to prove: nothing here is a scroll container
  expect(await panel(page).evaluate((el) => getComputedStyle(el).overflowX)).toBe('clip')
  expect(await panel(page).evaluate((el) => el.scrollHeight === el.clientHeight || getComputedStyle(el).overflowY === 'clip')).toBe(true)
})
