import { test, expect } from '@playwright/test'

// <atom:action> in a card's and a panel's heading row (tests/Fixtures/ActionsCardPanelFixture.php, resources/views/e2e/actions-card-panel.blade.php): the row
// collects its `actions` and `menu` slots, draws the first action as a link (an `href`) or a ghost button, and the rest in the ⋯ named for the box. The page
// is on the consumer's Tailwind (latest). Placement is read from data attributes.

const dialog = (page) => page.locator('dialog[x-data*="atom-confirm"]')
const log = async (page) => JSON.parse(await page.getByTestId('log').textContent())
const card = (page) => page.getByTestId('card')
const panel = (page) => page.getByTestId('panel')
const group = (box) => box.locator('[data-atom-heading-row-actions]')
const trigger = (box) => group(box).locator('[data-atom-dropdown] > button')
const menu = (box) => group(box).locator('[data-atom-menu]')
const settled = (page) => page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 400)))
const isOpen = (box) => menu(box).evaluate((el) => el.matches(':popover-open'))

const open = async (page) => {
  await page.goto('/atom/e2e/actions-card-panel')
  await page.waitForFunction(() => window.Livewire && window.Alpine && window.atom)
}

const openMenu = async (page, box) => {
  await trigger(box).click()
  await expect.poll(() => isOpen(box)).toBe(true)
}

// the group's children, by data-atom-action and name: `View all:visible`, `menu`
const placement = (box) => group(box).evaluate((el) => [...el.children].map((child) => child.hasAttribute('data-atom-action')
  ? `${(child.querySelector('[data-atom-button-label]') ?? child).textContent.replace(/\s+/g, ' ').trim()}:${child.getAttribute('data-atom-action')}`
  : child.hasAttribute('data-atom-dropdown') ? 'menu' : child.nodeName.toLowerCase()))

const rows = (box) => menu(box).evaluate((el) => [...el.children].map((child) => child.getAttribute('role') === 'separator' ? '|' : child.textContent.replace(/\s+/g, ' ').trim()))

test.describe('placement', () => {
  test('a card draws one link, then the ⋯; the rest in the menu after a separator, toned last', async ({ page }) => {
    await open(page)

    expect(await placement(card(page))).toEqual(['View all:visible', 'menu'])
    await openMenu(page, card(page))
    expect(await rows(card(page))).toEqual(['Refresh', 'Print', '|', 'Archive all', 'Clear parts'])
  })

  test('a card\'s link is 12px and a ghost button is xs; a panel\'s button is sm and its engine rows sit after the host\'s menu', async ({ page }) => {
    await open(page)
    const probe = (name) => page.evaluate((cls) => {
      const el = Object.assign(document.createElement('span'), { className: cls })
      document.body.append(el)
      const size = getComputedStyle(el).fontSize
      el.remove()
      return size
    }, name)

    const link = card(page).locator('a[data-atom-action="visible"]')
    await expect(link).toHaveText('View all')
    expect(await link.evaluate((el) => getComputedStyle(el).fontSize)).toBe(await probe('text-xs'))

    expect(await placement(panel(page))).toEqual(['Import:visible', 'menu'])
    const button = panel(page).locator('button[data-atom-action="visible"]')
    expect(await button.evaluate((el) => el.classList.contains('h-control-sm'))).toBe(true)
    await openMenu(page, panel(page))
    expect(await rows(panel(page))).toEqual(['Sync', 'Export', '|', 'Purge lines'])
  })

  test('the ⋯ is named for its box, so two on a page are told apart', async ({ page }) => {
    await open(page)

    await expect(trigger(card(page))).toHaveAccessibleName('More actions for Parts used')
    await expect(trigger(panel(page))).toHaveAccessibleName('More actions for Lines')
    await expect(page.getByRole('button', { name: 'More actions for Lines' })).toHaveCount(1)
  })

  test('every action is drawn once, and one in the body draws nothing', async ({ page }) => {
    await open(page)

    for (const name of ['card-view', 'card-refresh', 'card-print', 'card-archive', 'card-clear', 'panel-import', 'panel-sync', 'panel-purge', 'panel-export']) {
      await expect(page.getByTestId(name)).toHaveCount(1)
    }

    await expect(page.getByTestId('stray')).toHaveCount(0)
    await expect(page.getByTestId('card-body')).toContainText('3 parts, RM 420.00')
    // the body is not a slot a surface reads, so the stray action's marker stays there as an invisible comment, and no other one reaches the page
    expect((await page.content()).match(/atom:action:/g)).toHaveLength(1)
    expect(await page.getByTestId('card-body').innerHTML()).toContain('atom:action:')
  })

  test('375px and 1440px draw the same actions', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await open(page)
    const wide = [await placement(card(page)), await placement(panel(page))]

    await page.setViewportSize({ width: 375, height: 800 })
    await expect.poll(async () => [await placement(card(page)), await placement(panel(page))]).toEqual(wide)
  })
})

test.describe('the keyboard', () => {
  test('Tab visits the visible action, then the ⋯, in a card and in a panel', async ({ page }) => {
    await open(page)
    await card(page).locator('a[data-atom-action="visible"]').focus()

    await page.keyboard.press('Tab')
    await expect(trigger(card(page))).toBeFocused()

    await panel(page).getByTestId('panel-import').focus()
    await page.keyboard.press('Tab')
    await expect(trigger(panel(page))).toBeFocused()
  })

  test('Enter and Space open the ⋯ and focus its first row, Down walks the rows, and Escape closes it', async ({ page }) => {
    await open(page)

    for (const key of ['Enter', 'Space']) {
      await trigger(card(page)).focus()
      await page.keyboard.press(key)
      await expect.poll(() => isOpen(card(page))).toBe(true)

      await expect(page.getByTestId('card-refresh')).toBeFocused()
      await page.keyboard.press('ArrowDown')
      await expect(page.getByTestId('card-print')).toBeFocused()

      await page.keyboard.press('Escape')
      await expect.poll(() => isOpen(card(page))).toBe(false)
    }
  })

  test('Enter on the visible button of a panel makes exactly one call', async ({ page }) => {
    await open(page)
    await panel(page).getByTestId('panel-import').focus()
    await page.keyboard.press('Enter')

    await expect.poll(() => log(page)).toEqual(['panel-import'])
    await settled(page)
    expect(await log(page)).toEqual(['panel-import'])
  })
})

test.describe('a toned action asks first', () => {
  test('a warning row asks in warning; Cancel makes no call and the focus goes back to the ⋯', async ({ page }) => {
    await open(page)
    await openMenu(page, card(page))
    await page.getByTestId('card-archive').click()

    await expect(dialog(page)).toHaveAttribute('data-open', '')
    await expect(dialog(page)).toContainText('Archive all?')
    await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-warning-fg/)
    await dialog(page).getByRole('button', { name: 'Cancel' }).click()
    await settled(page)

    expect(await log(page)).toEqual([])
    await expect(trigger(card(page))).toBeFocused()
  })

  test('Accept makes exactly one call, with a danger row in a panel in danger', async ({ page }) => {
    await open(page)
    await openMenu(page, panel(page))
    await page.getByTestId('panel-purge').click()

    await expect(dialog(page).locator('button[type="submit"]')).toHaveClass(/bg-danger-fg/)
    await dialog(page).locator('button[type="submit"]').click()

    await expect.poll(() => log(page)).toEqual(['panel-purge'])
    await settled(page)
    expect(await log(page)).toEqual(['panel-purge'])
  })

  test('a delete row with no handler of its own calls delete() after Accept', async ({ page }) => {
    await open(page)
    await openMenu(page, card(page))
    await page.getByTestId('card-clear').click()
    await dialog(page).locator('button[type="submit"]').click()

    await expect.poll(() => log(page)).toEqual(['delete'])
  })

  test('a plain row makes one call and asks nothing', async ({ page }) => {
    await open(page)
    await openMenu(page, card(page))
    await page.getByTestId('card-refresh').click()

    await expect.poll(() => log(page)).toEqual(['card-refresh'])
    await expect(dialog(page)).not.toHaveAttribute('data-open', '')
  })
})

test.describe('a morph', () => {
  test('that adds an action while the card\'s ⋯ is open moves the visible one into it, keeps it open and fires no call', async ({ page }) => {
    await open(page)
    await openMenu(page, card(page))
    expect(await rows(card(page))).toEqual(['Refresh', 'Print', '|', 'Archive all', 'Clear parts'])

    await page.evaluate(() => window.Livewire.first().call('addExtra'))
    await expect.poll(() => placement(card(page))).toEqual(['New part:visible', 'menu'])

    expect(await isOpen(card(page))).toBe(true)
    await expect(trigger(card(page))).toHaveAttribute('aria-expanded', 'true')
    expect(await rows(card(page))).toEqual(['View all', 'Refresh', 'Print', '|', 'Archive all', 'Clear parts'])
    expect(await log(page)).toEqual([])
  })

  test('a re-render with the panel\'s ⋯ open leaves it open and the order the same', async ({ page }) => {
    await open(page)
    await openMenu(page, panel(page))

    await page.evaluate(() => window.Livewire.first().call('bump'))
    await expect(page.getByTestId('renders')).toHaveText('1')

    expect(await isOpen(panel(page))).toBe(true)
    expect(await rows(panel(page))).toEqual(['Sync', 'Export', '|', 'Purge lines'])
    expect(await placement(panel(page))).toEqual(['Import:visible', 'menu'])
    expect(await log(page)).toEqual([])
  })

  test('keeps the focus on the visible button', async ({ page }) => {
    await open(page)
    await panel(page).getByTestId('panel-import').focus()

    await page.evaluate(() => window.Livewire.first().call('bump'))
    await expect(page.getByTestId('renders')).toHaveText('1')

    await expect(panel(page).getByTestId('panel-import')).toBeFocused()
    expect(await log(page)).toEqual([])
  })
})
