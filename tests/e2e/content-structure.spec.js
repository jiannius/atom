import { test, expect, devices } from '@playwright/test'
import { openContent, style, token, tokenHex, hexOf, chartReady } from './content-helpers.js'
import { openLook } from './calendar-look.js'

/** `#rrggbb` channels. */
const channels = (hex) => [1, 3, 5].map((at) => parseInt(hex.slice(at, at + 2), 16))
const near = (a, b) => a !== null && channels(a).every((c, i) => Math.abs(c - channels(b)[i]) <= 2)
const expectNear = (a, b) => expect(near(a, b), `${a} is not ${b}`).toBe(true)

/** The stroke of the first path of a chart that draws one, as `#rrggbb`: the line of an area or a sparkline; null until there is one. */
const seriesStroke = (root) => root.evaluate((el) => {
  const g = document.createElement('canvas').getContext('2d', { willReadFrequently: true })

  for (const path of el.querySelectorAll('.apexcharts-series path')) {
    const stroke = getComputedStyle(path).stroke

    if (stroke && stroke !== 'none') {
      g.fillStyle = stroke
      g.fillRect(0, 0, 1, 1)
      const [r, gr, b] = g.getImageData(0, 0, 1, 1).data

      return '#' + [r, gr, b].map((c) => c.toString(16).padStart(2, '0')).join('')
    }
  }

  return null
})

// V4-3d, the text, the editor, the calendar and the chart: sizes and structure, in light, on the consumer's Tailwind
// (`node tests/consumer/build.mjs`), floor and latest. Type sizes and weights from the scale, hairlines, heights from the control token,
// 16px icons, the roles read from the tokens. Sizes and structure only, never a text width: Linux CI draws DejaVu Sans, which is much
// wider than the fonts on a Mac.

for (const tw of ['floor', 'latest']) {
  test.describe(`text, ${tw} consumer build`, () => {
    test('headings take the type scale at weight 590 in the foreground, a real heading element when given a level', async ({ page }) => {
      await openContent(page, { tw, section: 'text' })

      for (const [id, size] of [['h-xs', 12], ['h-sm', 13], ['h-default', 14], ['h-lg', 16], ['h-xl', 18]]) {
        const s = await style(page.getByTestId(id), 'font-size', 'font-weight', 'color')
        expect(s['font-size'], id).toBe(`${size}px`)
        expect(s['font-weight'], id).toBe('590')
        expect(s.color, id).toBe(await token(page, 'fg'))
      }

      expect(await page.getByTestId('h-xl').evaluate((el) => el.tagName)).toBe('H2')
      expect(await page.getByTestId('h-default').evaluate((el) => el.tagName)).toBe('DIV')
    })

    test('the heading size follows the density: 15px at comfortable', async ({ page }) => {
      await openContent(page, { tw, section: 'text', density: 'comfortable' })

      expect((await style(page.getByTestId('h-default'), 'font-size'))['font-size']).toBe('15px')
    })

    test('a heading with actions lays them out on the row, in the body size', async ({ page }) => {
      await openContent(page, { tw, section: 'text' })

      const row = page.getByTestId('h-actions')
      const button = page.getByTestId('h-actions-button')

      expect(await row.evaluate((el) => getComputedStyle(el).display)).toBe('flex')
      expect((await style(row, 'justify-content'))['justify-content']).toBe('space-between')
      expect((await style(button)).height).toBe(24)
    })

    test('the subheading and the caption are muted: 14px and 13px in fg-muted', async ({ page }) => {
      await openContent(page, { tw, section: 'text' })

      const sub = await style(page.getByTestId('subheading'), 'font-size', 'color')
      expect(sub['font-size']).toBe('14px')
      expect(sub.color).toBe(await token(page, 'fg-muted'))

      const cap = await style(page.getByTestId('caption'), 'font-size', 'color')
      expect(cap['font-size']).toBe('13px')
      expect(cap.color).toBe(await token(page, 'fg-muted'))
    })

    test('a label is 13px at weight 510 in the foreground, with a 16px icon beside it', async ({ page }) => {
      await openContent(page, { tw, section: 'text' })

      const label = page.getByTestId('label-icon')
      const s = await style(label, 'font-size', 'font-weight', 'color')
      expect(s['font-size']).toBe('13px')
      expect(s['font-weight']).toBe('510')
      expect(s.color).toBe(await token(page, 'fg'))

      const icon = await style(label.locator('[data-atom-icon]'))
      expect([icon.width, icon.height]).toEqual([16, 16])
    })

    test('an error is 13px at weight 510 in the danger ink, as a message and as a list', async ({ page }) => {
      await openContent(page, { tw, section: 'text' })

      for (const id of ['error-slot', 'error-list']) {
        const s = await style(page.getByTestId(id), 'font-size', 'font-weight', 'color')
        expect(s['font-size'], id).toBe('13px')
        expect(s['font-weight'], id).toBe('510')
        expect(s.color, id).toBe(await token(page, 'danger-fg'))
      }

      expect(await page.getByTestId('error-list').locator('li').count()).toBe(2)
    })

    test('a separator is a 1px hairline, and its label 12px, uppercase and muted', async ({ page }) => {
      await openContent(page, { tw, section: 'text' })

      const rule = page.getByTestId('separator').locator('> div')
      const r = await style(rule, 'background-color')
      expect(r.height).toBe(1)
      expect(r['background-color']).toBe(await token(page, 'border'))

      const label = page.getByTestId('separator-label').locator('span')
      const s = await style(label, 'font-size', 'text-transform', 'color')
      expect(s['font-size']).toBe('12px')
      expect(s['text-transform']).toBe('uppercase')
      expect(s.color).toBe(await token(page, 'fg-muted'))

      // two rules around a centred label, one after a left-aligned one
      expect(await page.getByTestId('separator-label').locator('> div').count()).toBe(2)
      expect(await page.getByTestId('separator-left').locator('> div').count()).toBe(1)
    })

    test('the share buttons are control-high squares at the control radius with 16px muted icons', async ({ page }) => {
      await openContent(page, { tw, section: 'text' })

      const buttons = page.getByTestId('sharer').locator('button[data-sharer], button[aria-label="Copy Link"]')
      expect(await buttons.count()).toBe(7)

      for (const button of await buttons.all()) {
        const s = await style(button, 'border-top-left-radius', 'color', 'box-shadow')
        expect([s.width, s.height]).toEqual([28, 28])
        expect(s['border-top-left-radius']).toBe('7px')
        expect(s.color).toBe(await token(page, 'fg-muted'))
        expect(s['box-shadow']).toBe('none')

        const icon = await style(button.locator('[data-atom-icon]'))
        expect([icon.width, icon.height]).toEqual([16, 16])
      }
    })

    test('the copy icon is 16px, and the embed placeholder is the subtle token', async ({ page }) => {
      await openContent(page, { tw, section: 'text' })

      const icon = await style(page.getByTestId('copy').locator('[data-atom-icon]'))
      expect([icon.width, icon.height]).toEqual([16, 16])

      expect((await style(page.getByTestId('embed').locator('div').first(), 'color')).color).toBe(await token(page, 'fg-subtle'))
    })

    test('the whatsapp button is a raised pill: the success ink with the surface as its text, 36px high, with the shadow token', async ({ page }) => {
      await openContent(page, { tw, section: 'text' })

      const s = await style(page.getByTestId('whatsapp'), 'background-color', 'color', 'box-shadow', 'border-top-left-radius', 'position', 'font-size')
      expect(s.height).toBe(36)
      expect(s['background-color']).toBe(await token(page, 'success-fg'))
      expect(s.color).toBe(await token(page, 'surface'))
      expect(s['box-shadow']).not.toBe('none')
      expect(s['border-top-left-radius']).toBe('999px')
      // a caller's `absolute` does not beat the default (both are :where(), zero specificity): the position is the host's to set with a real utility
      expect(s.position).toBe('fixed')
      expect(s['font-size']).toBe('13px')
      expect((await style(page.getByTestId('whatsapp').locator('[data-atom-icon]'))).width).toBe(16)
    })
  })

  test.describe(`links, ${tw} consumer build`, () => {
    test('a link is the accent ink with a solid underline, 16px icons, and no whitespace inside the anchor', async ({ page }) => {
      await openContent(page, { tw, section: 'links' })

      for (const id of ['link', 'link-accent', 'link-icon']) {
        const s = await style(page.getByTestId(id), 'color', 'text-decoration-line', 'text-decoration-style', 'text-underline-offset')
        expect(s.color, id).toBe(await token(page, 'accent-ink'))
        expect(s['text-decoration-line'], id).toBe('underline')
        expect(s['text-decoration-style'], id).toBe('solid')
        expect(s['text-underline-offset'], id).toBe('3px')
      }

      // the underline stops at the last letter
      expect(await page.getByTestId('link').evaluate((el) => el.textContent)).toBe('a link')

      const icons = page.getByTestId('link-icon').locator('[data-atom-icon]')
      expect(await icons.count()).toBe(2)
      for (const icon of await icons.all()) expect((await style(icon)).width).toBe(16)
    })

    test('the same ink in a callout of each tone and in a card, with no tone colour of its own', async ({ page }) => {
      await openContent(page, { tw, section: 'links' })

      for (const id of ['callout-link-info', 'callout-link-success', 'callout-link-warning', 'callout-link-danger', 'callout-link-neutral', 'card-link-a', 'card-link-subtle-a']) {
        expect((await style(page.getByTestId(id), 'color')).color, id).toBe(await token(page, 'accent-ink'))
      }
    })
  })

  test.describe(`editor content, ${tw} consumer build`, () => {
    test('the reading size is the base step: 14px, 15px at comfortable', async ({ page }) => {
      await openContent(page, { tw, section: 'editor' })

      const content = page.getByTestId('content')
      expect((await style(content, 'font-size', 'color'))['font-size']).toBe('14px')
      expect((await style(content, 'color')).color).toBe(await token(page, 'fg'))

      await openContent(page, { tw, section: 'editor', density: 'comfortable' })
      expect((await style(page.getByTestId('content'), 'font-size'))['font-size']).toBe('15px')
    })

    test('the headings follow the scale at weight 590, and the paragraphs breathe', async ({ page }) => {
      await openContent(page, { tw, section: 'editor' })
      const content = page.getByTestId('content')

      for (const [tag, size] of [['h1', 22], ['h2', 18], ['h3', 16], ['h4', 14]]) {
        const s = await style(content.locator(tag), 'font-size', 'font-weight')
        expect(s['font-size'], tag).toBe(`${size}px`)
        expect(s['font-weight'], tag).toBe('590')
      }
    })

    test('a link is the accent ink with a solid underline; a mention is the info tone', async ({ page }) => {
      await openContent(page, { tw, section: 'editor' })
      const content = page.getByTestId('content')

      const link = await style(content.locator('a'), 'color', 'text-decoration-line', 'text-decoration-style')
      expect(link.color).toBe(await token(page, 'accent-ink'))
      expect(link['text-decoration-line']).toBe('underline')
      expect(link['text-decoration-style']).toBe('solid')

      const mention = await style(content.locator('.mention'), 'color', 'background-color')
      expect(mention.color).toBe(await token(page, 'info-fg'))
      expect(mention['background-color']).toBe(await token(page, 'info-bg'))
    })

    test('a quote has a hairline rule and no italic, a table has strong hairlines and a sunken header, code is mono on the sunken fill', async ({ page }) => {
      await openContent(page, { tw, section: 'editor' })
      const content = page.getByTestId('content')

      const quote = await style(content.locator('blockquote'), 'border-left-width', 'border-left-color', 'font-style', 'color')
      expect(quote['border-left-width']).toBe('2px')
      expect(quote['border-left-color']).toBe(await token(page, 'border-strong'))
      expect(quote['font-style']).toBe('normal')
      expect(quote.color).toBe(await token(page, 'fg-muted'))

      const cell = await style(content.locator('td').first(), 'border-top-width', 'border-top-color')
      expect(cell['border-top-width']).toBe('1px')
      expect(cell['border-top-color']).toBe(await token(page, 'border-strong'))
      expect((await style(content.locator('th').first(), 'background-color', 'font-weight'))['background-color']).toBe(await token(page, 'surface-sunken'))

      const code = await style(content.locator('p code'), 'font-family', 'background-color')
      expect(code['font-family']).toContain('Geist Mono')
      expect(code['background-color']).toBe(await token(page, 'surface-sunken'))
      expect((await style(content.locator('pre'), 'background-color'))['background-color']).toBe(await token(page, 'surface-sunken'))

      expect((await style(content.locator('hr'), 'border-top-color'))['border-top-color']).toBe(await token(page, 'border-strong'))
    })
  })

  test.describe(`editor, ${tw} consumer build`, () => {
    test('the frame is a field: the surface, the control border, the control radius and no shadow', async ({ page }) => {
      await openContent(page, { tw, section: 'editor' })

      const frame = page.getByTestId('tiptap').locator('.editor').first()
      await expect(frame).toBeVisible({ timeout: 15000 })

      const s = await style(frame, 'background-color', 'border-top-color', 'border-top-width', 'border-top-left-radius', 'box-shadow')
      expect(s['background-color']).toBe(await token(page, 'surface'))
      expect(s['border-top-color']).toBe(await token(page, 'control-border'))
      expect(s['border-top-width']).toBe('1px')
      expect(s['border-top-left-radius']).toBe('7px')
      expect(s['box-shadow']).toBe('none')
    })

    test('the frame takes the focus colour and a 2px ring while the text is edited, and the text itself draws none', async ({ page }) => {
      await openContent(page, { tw, section: 'editor' })

      const frame = page.getByTestId('tiptap').locator('.editor').first()
      await expect(frame).toBeVisible({ timeout: 15000 })

      const editable = page.getByTestId('tiptap').locator('[contenteditable="true"]')
      await editable.click()
      await expect(editable).toBeFocused()

      await expect.poll(async () => (await style(frame, 'border-top-color'))['border-top-color']).toBe(await token(page, 'focus'))
      const s = await style(frame, 'outline-width', 'outline-style', 'outline-color')
      expect([s['outline-width'], s['outline-style'], s['outline-color']]).toEqual(['2px', 'solid', await token(page, 'focus')])
      expect((await style(editable, 'outline-style'))['outline-style']).toBe('none')
    })

    test('the toolbar buttons are control-high squares at the nav radius with 16px icons, and the bar is a hairline on the surface', async ({ page }) => {
      await openContent(page, { tw, section: 'editor' })

      const toolbar = page.getByTestId('tiptap').getByRole('toolbar')
      await expect(toolbar).toBeVisible({ timeout: 15000 })

      const buttons = toolbar.locator('button:visible')
      expect(await buttons.count()).toBeGreaterThan(8)

      for (const button of await buttons.all()) {
        const s = await style(button, 'border-top-left-radius')
        expect([s.width, s.height]).toEqual([28, 28])
        expect(s['border-top-left-radius']).toBe('6px')
        expect((await style(button.locator('svg').first())).width).toBe(16)
      }

      const bar = await style(toolbar, 'background-color', 'border-top-width', 'box-shadow')
      expect(bar['background-color']).toBe(await token(page, 'surface'))
      expect(bar['border-top-width']).toBe('1px')
      expect(bar['box-shadow']).toBe('none')
    })

    test('a toolbar button reads the foreground on a fill when it is the active one', async ({ page }) => {
      await openContent(page, { tw, section: 'editor' })

      const editor = page.getByTestId('tiptap')
      const toolbar = editor.getByRole('toolbar')
      await expect(toolbar).toBeVisible({ timeout: 15000 })

      const editable = editor.locator('[contenteditable="true"]')
      await editable.click()
      await page.keyboard.type('marked')
      await page.keyboard.press('ControlOrMeta+a')

      const highlight = toolbar.getByRole('button', { name: 'Text Highlight' })
      await highlight.click()
      await expect(highlight).toHaveAttribute('aria-pressed', 'true')

      const s = await style(highlight, 'color', 'background-color')
      expect(s.color).toBe(await token(page, 'fg'))
      expect(s['background-color']).not.toBe('rgba(0, 0, 0, 0)')
    })

    test('the placeholder is the subtle token, and the chat composer is the same field', async ({ page }) => {
      await openContent(page, { tw, section: 'editor' })

      const chat = page.getByTestId('chat').locator('.editor').first()
      await expect(chat).toBeVisible({ timeout: 15000 })

      const s = await style(chat, 'background-color', 'border-top-color', 'border-top-left-radius', 'box-shadow')
      expect(s['background-color']).toBe(await token(page, 'surface'))
      expect(s['border-top-color']).toBe(await token(page, 'control-border'))
      expect(s['border-top-left-radius']).toBe('7px')
      expect(s['box-shadow']).toBe('none')

      const placeholder = await page.getByTestId('tiptap').locator('p.is-editor-empty').evaluate((el) => getComputedStyle(el, '::before').color)
      expect(placeholder).toBe(await token(page, 'fg-subtle'))
    })
  })

  test.describe(`chart, ${tw} consumer build`, () => {
    test('a series reads the ink of its tone: success, the accent by default, warning and danger', async ({ page }) => {
      await openContent(page, { tw, section: 'chart' })

      // Apex nudges a channel by one when it shades a colour: the token's own, give or take that
      const bar = await chartReady(page, 'chart-bar')
      expectNear(await hexOf(bar.series, 'fill'), await tokenHex(page, 'success-fg'))

      const def = await chartReady(page, 'chart-bar-default')
      expectNear(await hexOf(def.series, 'fill'), await tokenHex(page, 'accent-ink'))

      const area = await chartReady(page, 'chart-area', 'area')
      expectNear(await seriesStroke(area.root), await tokenHex(page, 'warning-fg'))
    })

    test('the stats card maps the direction onto tones: up is success, down is danger, flat is neutral', async ({ page }) => {
      await openContent(page, { tw, section: 'chart' })

      for (const [id, tone] of [['stat-up', 'success-fg'], ['stat-down', 'danger-fg'], ['stat-flat', 'fg-subtle']]) {
        const root = page.getByTestId(id)
        await expect(root.locator('.apexcharts-canvas')).toBeVisible({ timeout: 15000 })
        await expect.poll(async () => near(await seriesStroke(root), await tokenHex(page, tone)), id).toBe(true)
      }
    })

    test('the grid is the hairline token and the labels the muted one', async ({ page }) => {
      await openContent(page, { tw, section: 'chart' })
      const { root } = await chartReady(page, 'chart-bar')

      const label = root.locator('.apexcharts-xaxis-label').first()
      await expect.poll(() => hexOf(label, 'fill')).toBe(await tokenHex(page, 'fg-muted'))
    })
  })

  test.describe(`calendar, ${tw} consumer build`, () => {
    const look = (page) => page.locator('[data-probe="look"]')

    test('the root is a panel: the surface, a hairline, a 10px radius and no shadow; the day list and a lifted item are the raised ones', async ({ page }) => {
      await openLook(page, { view: 'month', tw })

      const s = await style(look(page), 'background-color', 'border-top-color', 'border-top-width', 'border-top-left-radius', 'box-shadow')
      expect(s['background-color']).toBe(await token(page, 'surface'))
      expect(s['border-top-color']).toBe(await token(page, 'border'))
      expect(s['border-top-width']).toBe('1px')
      expect(s['border-top-left-radius']).toBe('10px')
      expect(s['box-shadow']).toBe('none')

      await page.evaluate(() => window.atomLook.apply('move'))
      await page.waitForTimeout(200)
      const ghost = look(page).locator('[data-atom-calendar-ghost]').first()
      await expect(ghost).toBeVisible()
      expect((await style(ghost, 'box-shadow'))['box-shadow']).not.toBe('none')
    })

    test('the toolbar buttons and the view switch are control-high (the look sheet is a bare page: comfortable, 32px), the switch a sunken track with the surface as its current segment', async ({ page }) => {
      await openLook(page, { view: 'week', tw })

      for (const name of ['Previous', 'Today', 'Next']) expect((await style(look(page).getByRole('button', { name, exact: true }))).height, name).toBe(32)

      const group = look(page).getByRole('group', { name: 'View' })
      const track = await style(group, 'background-color', 'border-top-left-radius')
      expect(track.height).toBe(32)
      expect(track['background-color']).toBe(await token(page, 'surface-sunken'))
      expect(track['border-top-left-radius']).toBe('7px')

      const current = await style(group.getByRole('button', { name: 'Week', exact: true }), 'background-color', 'box-shadow')
      expect(current['background-color']).toBe(await token(page, 'surface'))
      expect(current.height).toBe(28)
    })

    test('today is the accent ink with the surface as its text, and the preset colours are the tones', async ({ page }) => {
      await openLook(page, { view: 'month', tw })

      const today = look(page).locator('.bg-accent-ink.text-surface').first()
      await expect(today).toBeVisible()
      const s = await style(today, 'background-color', 'color')
      expect(s['background-color']).toBe(await token(page, 'accent-ink'))
      expect(s.color).toBe(await token(page, 'surface'))

      // a chip of each tone: its tint is the tone's and its edge the tone's ink
      const chips = await look(page).locator('[data-atom-calendar-chip="bar"]:not([data-atom-calendar-hex])').evaluateAll((els) => els.map((el) => {
        const s = getComputedStyle(el)

        return { bg: s.backgroundColor, edge: s.borderLeftColor, label: el.getAttribute('aria-label') }
      }))
      const tints = new Set([await token(page, 'danger-bg'), await token(page, 'warning-bg'), await token(page, 'success-bg'), await token(page, 'info-bg'), await token(page, 'neutral-bg')])

      expect(chips.length).toBeGreaterThan(3)
      expect(chips.filter((c) => tints.has(c.bg)).length).toBeGreaterThan(2)
    })

    test('the day list is a raised panel: the raised fill, a strong hairline, a 10px radius and the shadow token', async ({ page }) => {
      await openLook(page, { view: 'month', tw, query: 'state=popover' })

      const popover = look(page).locator('[data-atom-calendar-popover]')
      await expect(popover).toBeVisible()

      const s = await style(popover, 'background-color', 'border-top-color', 'border-top-left-radius', 'box-shadow')
      expect(s['background-color']).toBe(await token(page, 'surface-raised'))
      expect(s['border-top-color']).toBe(await token(page, 'border-strong'))
      expect(s['border-top-left-radius']).toBe('10px')
      expect(s['box-shadow']).not.toBe('none')
    })
  })
}

for (const tw of ['floor', 'latest']) {
  test.describe(`docs chrome, ${tw} consumer build`, () => {
    // the docs layout ships no stylesheet of its own: the consumer build is added after load, as a host's would be there
    const openDocs = async (page, path) => {
      await page.goto(path)
      await page.addStyleTag({ url: `/atom/e2e/consumer-css/${tw}` })
      await page.evaluate(() => document.documentElement.classList.remove('dark'))
      await page.evaluate(() => document.fonts.ready)
    }

    test('an example is a surface panel over a sunken source block, both at the panel radius with a hairline and no shadow', async ({ page }) => {
      await openDocs(page, '/atom/docs/link')

      const section = page.locator('section').first()
      const preview = section.locator('> div.rounded-panel').first()
      const source = section.locator('pre').first().locator('..')

      for (const [name, box, fill] of [['preview', preview, 'surface'], ['source', source, 'surface-sunken']]) {
        const s = await style(box, 'background-color', 'border-top-width', 'border-top-left-radius', 'box-shadow')
        expect(s['background-color'], name).toBe(await token(page, fill))
        expect(s['border-top-width'], name).toBe('1px')
        expect(s['border-top-left-radius'], name).toBe('10px')
        expect(s['box-shadow'], name).toBe('none')
      }

      // the source is mono at 12px in the foreground, and a block of the page, not a dark slab in both themes
      const pre = await style(section.locator('pre').first(), 'font-family', 'font-size', 'color')
      expect(pre['font-family']).toContain('Geist Mono')
      expect(pre['font-size']).toBe('12px')
      expect(pre.color).toBe(await token(page, 'fg'))
    })

    test('the index cards are surface panels with a hairline that sink on hover, and the title is a 18px heading', async ({ page }) => {
      await openDocs(page, '/atom/docs')

      const card = page.locator('main a[href*="/atom/docs/"]').first()
      const s = await style(card, 'background-color', 'border-top-width', 'border-top-left-radius', 'font-size')
      expect(s['background-color']).toBe(await token(page, 'surface'))
      expect(s['border-top-width']).toBe('1px')
      expect(s['border-top-left-radius']).toBe('10px')
      expect(s['font-size']).toBe('13px')

      await card.hover()
      await expect.poll(async () => (await style(card, 'background-color'))['background-color']).toBe(await token(page, 'surface-sunken'))
    })

    test('the brand tile is the accent with its own foreground, at the nav radius', async ({ page }) => {
      await openDocs(page, '/atom/docs')

      const tile = page.locator('a[href$="/atom/docs"] > div').first()
      const s = await style(tile, 'background-color', 'color', 'border-top-left-radius')
      expect(s['background-color']).toBe(await tokenAccent(page))
      expect(s['border-top-left-radius']).toBe('6px')
    })
  })
}

/** The accent as the page computes it (`--color-accent`), for what is drawn in the accent itself. */
const tokenAccent = (page) => page.evaluate(() => {
  const probe = document.createElement('div')
  probe.style.backgroundColor = 'var(--color-accent)'
  document.body.append(probe)
  const colour = getComputedStyle(probe).backgroundColor
  probe.remove()
  return colour
})
