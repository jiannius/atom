// Helpers for tests/e2e/calendar-look.spec.js: the look fixture in a browser with Tailwind, and a contrast measurement of what is on screen.
//
// The rig ships no Tailwind, so the calendar's colours only exist once Tailwind's browser build is injected. It is loaded from a CDN at a
// pinned version (no dependency of the package), and a page that cannot reach it skips what needs it.

export const TAILWIND = 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.11'

/**
 * Open the look fixture and give it Tailwind, with a class-based dark variant as atom's opt-in dark mode is. Returns false when
 * Tailwind cannot be loaded (offline), so a test can skip.
 */
export async function openLook (page, { view = 'month', query = '', theme = 'light', tailwind = true, route = null } = {}) {
    if (route) await page.route('**/@tailwindcss/browser@4**', route)

    await page.goto(`/atom/e2e/calendar-look?view=${view}${query ? '&' + query : ''}`)
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined && window.atomLook !== undefined)

    if (!tailwind) return true

    try {
        await page.evaluate(() => {
            const style = document.createElement('style')

            style.setAttribute('type', 'text/tailwindcss')
            style.textContent = '@custom-variant dark (&:where(.dark, .dark *));'
            document.head.append(style)
        })
        await page.addScriptTag({ url: TAILWIND })
    } catch {
        return false
    }

    // Tailwind's browser build compiles on a mutation observer: wait until a class it generates has been applied
    await page.waitForFunction(() => getComputedStyle(document.querySelector('[data-atom-calendar]')).borderTopWidth === '1px', null, { timeout: 15000 })

    if (theme === 'dark') await page.evaluate(() => document.documentElement.classList.add('dark'))

    await page.waitForTimeout(200)

    return true
}

/**
 * Measure the contrast of everything the calendar draws that is in view, in the browser, against each element's OWN background: what
 * is painted under a point of the element, layer by layer, translucent tints included (so the cell a day number sits over counts, though
 * it is not an ancestor of the number), and an element's opacity is applied to its colour.
 *
 * Returns { text, graphics }: `text` is one row per distinct (colour, background) pair of text on screen, `graphics` the non-text cues (accent
 * edges, dots, the now line, the selection's edge, the today circle). A row is { name, ratio, fg, bg, example, count }.
 */
export async function measureContrast (page) {
    return await page.evaluate(() => {
        // every element can be hit while measuring, so the layers that let a click through (the chips over the cells) are in the stack
        const unblock = document.createElement('style')

        unblock.textContent = '[data-atom-calendar] * { pointer-events: auto !important }'
        document.head.append(unblock)

        const canvas = document.createElement('canvas')

        canvas.width = canvas.height = 1

        const context = canvas.getContext('2d', { willReadFrequently: true })

        // any CSS colour (oklch, color-mix, rgb) as [r, g, b, a], through the browser's own conversion
        const rgba = (value) => {
            context.clearRect(0, 0, 1, 1)
            context.fillStyle = '#000'
            context.fillStyle = value
            context.fillRect(0, 0, 1, 1)

            const [r, g, b, a] = context.getImageData(0, 0, 1, 1).data

            return [r, g, b, a / 255]
        }

        const over = (top, bottom) => {
            const a = top[3] + bottom[3] * (1 - top[3])

            return a === 0 ? [0, 0, 0, 0] : [0, 1, 2].map((i) => (top[i] * top[3] + bottom[i] * bottom[3] * (1 - top[3])) / a).concat(a)
        }

        const luminance = ([r, g, b]) => {
            const channel = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4 }

            return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
        }

        const ratio = (a, b) => {
            const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)

            return (hi + 0.05) / (lo + 0.05)
        }

        // what is painted at a point from `el` down: its own background and everything under it, composited onto the page
        const groundAt = (el, x, y, includeSelf = true) => {
            const stack = document.elementsFromPoint(x, y)
            const at = stack.indexOf(el)

            if (at === -1) return null

            return stack.slice(includeSelf ? at : at + 1).reduceRight((below, node) => over(rgba(getComputedStyle(node).backgroundColor), below), [255, 255, 255, 1])
        }

        const opacityOf = (el) => {
            let opacity = 1

            for (let node = el; node; node = node.parentElement) opacity *= Number(getComputedStyle(node).opacity)

            return opacity
        }

        const hex = (c) => '#' + c.slice(0, 3).map((v) => Math.round(v).toString(16).padStart(2, '0')).join('')

        const inView = (x, y) => x >= 0 && y >= 0 && x < innerWidth && y < innerHeight

        // a name for a row: the nearest calendar part the element is in, and the colour classes it carries
        const nameOf = (el) => {
            const part = el.closest('[data-atom-calendar-chip],[data-atom-calendar-preview],[data-atom-calendar-ghost],[data-atom-calendar-popover],[data-atom-calendar-allday],[data-atom-calendar-column-head],[data-atom-calendar-weekdays],[data-atom-calendar-number],[data-atom-calendar-gutter],[data-atom-calendar-more],[data-atom-calendar-title],[data-atom-calendar-selection]')
            const kind = part
                ? ([...part.attributes].map((a) => a.name).find((n) => n.startsWith('data-atom-calendar-') && n !== 'data-atom-calendar-hex') ?? 'calendar').replace('data-atom-calendar-', '')
                    + (part.getAttribute('data-atom-calendar-chip') ? ':' + part.getAttribute('data-atom-calendar-chip') : '')
                    + (part.getAttribute('data-kind') ? ':' + part.getAttribute('data-kind') : '')
                : 'toolbar'
            const colours = [...el.classList].filter((c) => /^(dark:)?text-/.test(c) && !/^(dark:)?text-(xs|sm|base|lg|xl|2xl)$/.test(c)).join(' ')

            return kind + ' | ' + (colours || '(inherited)')
        }

        const root = document.querySelector('[data-atom-calendar]')
        const text = new Map()
        const graphics = new Map()
        const skipped = { text: 0, graphics: 0 }
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)

        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const value = node.textContent.trim()
            const el = node.parentElement

            if (!value || !el || el.closest('template, [data-atom-calendar-sr], [hidden]') || !el.checkVisibility()) continue

            // a lifted item is the original of one being dragged, drawn at half strength on purpose
            if (el.closest('[data-lifted]')) continue

            const range = document.createRange()

            range.selectNodeContents(node)

            const rect = range.getBoundingClientRect()
            const x = rect.left + rect.width / 2
            const y = rect.top + rect.height / 2
            const background = rect.width && inView(x, y) ? groundAt(el, x, y) : null

            // out of view, clipped, or covered by something else: not what a person sees
            if (!background) { skipped.text++; continue }

            const style = getComputedStyle(el)
            const color = rgba(style.color)
            const foreground = over([color[0], color[1], color[2], color[3] * opacityOf(el)], background)
            // the stripes of a pending item are the text colour at 14% over the ground: measure against the darker of the two
            const striped = el.closest('[data-atom-calendar-pending], [data-atom-calendar-selection][data-kind="pending"]')
            const behind = striped ? over([color[0], color[1], color[2], 0.14], background) : background
            const name = nameOf(el)
            const key = name + '|' + hex(foreground) + '|' + hex(behind)
            const found = text.get(key) ?? { name, ratio: Math.min(ratio(foreground, behind), ratio(foreground, background)), fg: hex(foreground), bg: hex(behind), example: value.slice(0, 24), count: 0 }

            found.count++
            text.set(key, found)
        }

        // a non-text cue against what is painted under it, at a few points along it, the worst taken
        const add = (name, shape, el, points, includeSelf, example = '') => {
            const grounds = points.map(([x, y]) => (inView(x, y) ? groundAt(el, x, y, includeSelf) : null)).filter(Boolean)

            if (!grounds.length) { skipped.graphics++; return }

            const worst = grounds.map((ground) => ({ ground, ratio: ratio(shape, ground) })).sort((a, b) => a.ratio - b.ratio)[0]
            const key = name + '|' + hex(shape) + '|' + hex(worst.ground)
            const found = graphics.get(key) ?? { name, ratio: worst.ratio, fg: hex(shape), bg: hex(worst.ground), example, count: 0 }

            found.count++
            graphics.set(key, found)
        }

        const visible = (selector) => [...root.querySelectorAll(selector)].filter((el) => !el.closest('template') && el.checkVisibility() && !el.closest('[data-lifted]'))
        const middle = (rect) => [rect.left + rect.width / 2, rect.top + rect.height / 2]
        const along = (rect) => [0.1, 0.5, 0.9].map((share) => [rect.left + rect.width * share, rect.top + rect.height / 2])

        // an accent edge has to show against the ground of the cell and against its own tint
        for (const el of visible('[data-atom-calendar-chip="bar"], [data-atom-calendar-chip="block"], [data-atom-calendar-ghost-body]')) {
            const style = getComputedStyle(el)

            if (style.borderLeftWidth === '0px') continue

            const edge = rgba(style.borderLeftColor)
            const rect = el.getBoundingClientRect()
            const kind = el.getAttribute('data-atom-calendar-chip') ?? 'ghost'
            const label = el.textContent.trim().replace(/\s+/g, ' ').slice(0, 20)
            // two pixels in: inside the border, over the chip's own box
            const point = [[rect.left + 1, rect.top + rect.height / 2]]

            // the tint is what is under the edge from the chip down; the ground is what is under the chip
            add('accent edge: ' + kind + ' on its tint', edge, el, point, true, label)
            add('accent edge: ' + kind + ' on the ground', edge, el, point, false, label)
        }

        for (const el of visible('[data-atom-calendar-dot]')) add('colour dot on the ground', rgba(getComputedStyle(el).backgroundColor), el, [middle(el.getBoundingClientRect())], false)

        for (const el of visible('[data-atom-calendar-now-line]')) {
            add('now line on its column', rgba(getComputedStyle(el).backgroundColor), el, along(el.getBoundingClientRect()), false)
            // the dot is wider than the line: measure it at the top, where only the column is behind it
            const dot = el.firstElementChild.getBoundingClientRect()

            add('now dot on its column', rgba(getComputedStyle(el.firstElementChild).backgroundColor), el.firstElementChild, [[dot.left + dot.width / 2, dot.top + 1]], false)
        }

        for (const el of visible('[data-atom-calendar-number] > span, [data-atom-calendar-column-head] > span:last-child, [data-atom-calendar-popover] [class*="rounded-full"]')) {
            const circle = rgba(getComputedStyle(el).backgroundColor)

            if (circle[3] > 0.5) add('today circle on the ground', circle, el, [[el.getBoundingClientRect().left + 1, el.getBoundingClientRect().top + el.getBoundingClientRect().height / 2]], false, el.textContent.trim())
        }

        // the edge of a selection or an item waiting for the host, and the grip of a resize
        for (const el of visible('[data-atom-calendar-selection], [data-atom-calendar-preview] [class*="ring-inset"]')) {
            const shadow = getComputedStyle(el).boxShadow.match(/(rgba?\([^)]*\)|oklch\([^)]*\)|oklab\([^)]*\)|color\([^)]*\)|lab\([^)]*\)|#[0-9a-f]+)[^,]*inset/i)
            const rect = el.getBoundingClientRect()

            if (shadow) add('selection edge on the ground', rgba(shadow[1]), el, [[rect.left + 2, rect.top + rect.height / 2]], false, el.getAttribute('data-kind') ?? '')
        }

        for (const el of visible('[data-atom-calendar-preview] span[aria-hidden="true"][class*="bg-zinc"], [data-atom-calendar-ghost] span[aria-hidden="true"][class*="bg-zinc"]')) {
            add('resize grip on its tint', rgba(getComputedStyle(el).backgroundColor), el, [middle(el.getBoundingClientRect())], false)
        }

        unblock.remove()

        const rows = (map) => [...map.values()].sort((a, b) => a.ratio - b.ratio)

        return { text: rows(text), graphics: rows(graphics), skipped }
    })
}
