// Helpers for tests/e2e/calendar-look.spec.js: the look fixture in a browser with Tailwind, and a contrast measurement of what is on screen.
//
// The rig ships no Tailwind, so the calendar's colours only exist once Tailwind's browser build is injected. It is loaded from a CDN at a
// pinned version (no dependency of the package). A page that cannot reach it cannot be checked: under CI (`CI` set) that is a failure, so
// a build with no network never passes for want of looking; on a machine it is a skip, said loudly so it is not mistaken for a pass.

export const TAILWIND = 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.11'

/** The reason a test gives when it skips for want of Tailwind. */
export const SKIP_REASON = 'SKIPPED, NOT PASSED: Tailwind could not be loaded from the CDN, so the look and contrast checks did not run'

/**
 * Open the look fixture and give it Tailwind, with a class-based dark variant as atom's opt-in dark mode is. Returns false when
 * Tailwind cannot be loaded (offline) and `CI` is not set, so a test can skip with SKIP_REASON; with `CI` set it throws instead.
 */
export async function openLook (page, { view = 'month', query = '', theme = 'light', tailwind = true } = {}) {
    await page.goto(`/atom/e2e/calendar-look?view=${view}${query ? '&' + query : ''}`)
    await page.waitForFunction(() => window.Livewire !== undefined && window.Alpine !== undefined && window.atomLook !== undefined)

    if (!tailwind) return true

    try {
        await page.evaluate(() => {
            const style = document.createElement('style')

            style.setAttribute('type', 'text/tailwindcss')
            style.textContent = '@custom-variant dark (&:where(.dark, .dark *)); @theme { --font-sans: "Geist", ui-sans-serif, system-ui, sans-serif; }'
            document.head.append(style)
        })
        await page.addScriptTag({ url: TAILWIND })
    } catch (error) {
        const message = `Tailwind could not be loaded from ${TAILWIND} (${String(error.message).split('\n')[0]}): the look and contrast checks cannot run.`

        if (process.env.CI) throw new Error(message)

        console.warn(`\n!!! calendar-look: ${message} Their tests are SKIPPED, not passed. Set CI=1 to make this a failure.\n`)

        return false
    }

    // Tailwind's browser build compiles on a mutation observer: wait until a class it generates has been applied
    await page.waitForFunction(() => getComputedStyle(document.querySelector('[data-atom-calendar]')).borderTopWidth === '1px', null, { timeout: 15000 })

    if (theme === 'dark') await page.evaluate(() => document.documentElement.classList.add('dark'))

    await page.evaluate(() => document.fonts.ready)
    await page.waitForTimeout(200)

    return true
}

/**
 * Measure the contrast of everything the calendar draws that is in view, in the browser, against each element's OWN background: what
 * is painted under a point of the element, layer by layer, translucent tints included (so the cell a day number sits over counts, though
 * it is not an ancestor of the number), and an element's opacity is applied to its colour.
 *
 * Returns { text, graphics, tried, seen, labels, colors, present }: `text` is one row per distinct (colour, background) pair of text on
 * screen, `graphics` the non-text cues (accent edges, dots, the now line, the selection's edge, the today circle, the icons of the toolbar's
 * buttons). A row is { name, ratio, fg, bg, example, count }. Only what is in view and not covered or clipped can be measured: `tried` and
 * `seen` list, by a stable id, what was looked at and what was measured (`labels` says what each id is), `colors` the item colours (a preset's
 * name or a hex) that were measured and `present` the ones that are on the page at all. measureSweep() scrolls and adds the calls up.
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

        // a stable id for a node, the same in every call on this page, so a sweep can tell what it never managed to measure
        window.__atomMeasureIds ??= new WeakMap()
        window.__atomMeasureNext ??= 0

        const idOf = (node, prefix) => {
            if (!window.__atomMeasureIds.has(node)) window.__atomMeasureIds.set(node, ++window.__atomMeasureNext)

            return prefix + window.__atomMeasureIds.get(node)
        }

        // the colour an item is drawn in, as its preset's name (two presets with the same classes are one) or its hex; null for no item
        const engine = window.Alpine.$data(document.querySelector('[data-atom-calendar]'))
        const signatures = []

        for (const [name, classes] of Object.entries(engine.palette ?? {})) {
            const sets = ['tint', 'accent', 'dot'].map((part) => String(classes[part] ?? '').split(/\s+/).filter(Boolean))

            if (!signatures.some((known) => known.sets.every((set, i) => set.join(' ') === sets[i].join(' ')))) signatures.push({ name, sets })
        }

        const colorOf = (el) => {
            const item = el.closest('[data-atom-calendar-chip], [data-atom-calendar-ghost-body], [data-atom-calendar-dot]')

            if (!item) return null

            if (item.hasAttribute('data-atom-calendar-hex')) return item.style.getPropertyValue('--atom-calendar-hue').trim().toLowerCase()

            const own = new Set(item.classList)
            const found = signatures.find(({ sets }) => sets.some((set) => set.length && set.every((token) => own.has(token))))

            return found ? found.name : null
        }

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
        const tried = { text: [], graphics: [] }
        const seen = { text: [], graphics: [] }
        const labels = {}
        const colors = new Set()
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)

        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const value = node.textContent.trim()
            const el = node.parentElement

            if (!value || !el || el.closest('template, [data-atom-calendar-sr], [hidden]') || !el.checkVisibility()) continue

            // a lifted item is the original of one being dragged, drawn at half strength on purpose
            if (el.closest('[data-lifted]')) continue

            const id = idOf(node, 't')

            tried.text.push(id)
            labels[id] = value.slice(0, 24)

            const range = document.createRange()

            range.selectNodeContents(node)

            // a title that wraps is several line boxes: the first of them that is on screen and not clipped is the one measured
            let background = null

            for (const rect of range.getClientRects()) {
                const x = rect.left + rect.width / 2
                const y = rect.top + rect.height / 2

                background = rect.width && inView(x, y) ? groundAt(el, x, y) : null

                if (background) break
            }

            // out of view, clipped, or covered by something else: not what a person sees
            if (!background) continue

            seen.text.push(id)
            colors.add(colorOf(el))

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
            const id = idOf(el, 'g:' + name + ':')

            tried.graphics.push(id)
            labels[id] = name + ' ' + example

            const grounds = points.map(([x, y]) => (inView(x, y) ? groundAt(el, x, y, includeSelf) : null)).filter(Boolean)

            if (!grounds.length) return

            seen.graphics.push(id)
            colors.add(colorOf(el))

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

        // the glyph of a button in the toolbar (previous, next, the day list's close) is a cue in the colour of the button's text
        for (const el of visible('[data-atom-button] svg')) {
            add('button icon on its ground', rgba(getComputedStyle(el).color), el, [middle(el.getBoundingClientRect())], false)
        }

        unblock.remove()

        const rows = (map) => [...map.values()].sort((a, b) => a.ratio - b.ratio)

        // every colour on the page, measured or not
        const present = new Set([...root.querySelectorAll('[data-atom-calendar-chip], [data-atom-calendar-ghost-body], [data-atom-calendar-dot]')]
            .filter((el) => !el.closest('template') && el.checkVisibility() && !el.closest('[data-lifted]'))
            .map(colorOf))

        colors.delete(null)
        present.delete(null)

        return { text: rows(text), graphics: rows(graphics), tried, seen, labels, colors: [...colors], present: [...present] }
    })
}

// the scroll positions that, with `overlap` of a screen in common, take a view `client` wide over a content `scroll` wide
const stops = (scroll, client, overlap = 0.4) => {
    if (scroll <= client + 1) return [0]

    const step = Math.max(1, Math.floor(client * (1 - overlap)))
    const list = []

    for (let at = 0; at < scroll - client; at += step) list.push(at)

    list.push(scroll - client)

    return list
}

/**
 * measureContrast() over the whole of what the calendar can show: the page scrolled through, and the time grid, which is a scroller of
 * its own, scrolled down and (when it is wider than the screen, as a week is at 390) across, so that the afternoon, the late items and
 * the columns off to the side are on screen at some step and get measured. The scroll positions are put back after.
 *
 * Returns what measureContrast() does, the rows merged (the worst ratio of a row wins), and `skipped` the count of things that were tried
 * and never once measured (`skippedExamples` names them, by kind): at every scroll position they were clipped, covered or off screen.
 */
export async function measureSweep (page) {
    const saved = await page.evaluate(() => {
        const grid = document.querySelector('[data-atom-calendar-grid]')
        const scroller = document.scrollingElement

        return { page: [scroller.scrollLeft, scroller.scrollTop], grid: grid ? [grid.scrollLeft, grid.scrollTop] : null }
    })
    const size = await page.evaluate(() => {
        const grid = document.querySelector('[data-atom-calendar-grid]')
        const scroller = document.scrollingElement
        const shown = grid && grid.checkVisibility() && grid.clientHeight > 0

        return {
            page: { width: scroller.scrollWidth, height: scroller.scrollHeight, clientWidth: innerWidth, clientHeight: innerHeight },
            grid: shown ? { width: grid.scrollWidth, height: grid.scrollHeight, clientWidth: grid.clientWidth, clientHeight: grid.clientHeight } : null,
        }
    })

    const merged = { labels: {}, text: new Map(), graphics: new Map(), tried: { text: new Set(), graphics: new Set() }, seen: { text: new Set(), graphics: new Set() }, colors: new Set(), present: new Set(), steps: 0 }
    const take = (result) => {
        for (const kind of ['text', 'graphics']) {
            for (const row of result[kind]) {
                const key = row.name + '|' + row.fg + '|' + row.bg
                const known = merged[kind].get(key)

                if (!known || row.ratio < known.ratio) merged[kind].set(key, { ...row, count: (known?.count ?? 0) + row.count })
            }

            result.tried[kind].forEach((id) => merged.tried[kind].add(id))
            result.seen[kind].forEach((id) => merged.seen[kind].add(id))
        }

        Object.assign(merged.labels, result.labels)
        result.colors.forEach((color) => merged.colors.add(color))
        result.present.forEach((color) => merged.present.add(color))
        merged.steps++
    }

    for (const y of stops(size.page.height, size.page.clientHeight)) {
        for (const x of stops(size.page.width, size.page.clientWidth)) {
            await page.evaluate(([left, top]) => document.scrollingElement.scrollTo(left, top), [x, y])

            for (const gy of stops(size.grid?.height ?? 0, size.grid?.clientHeight ?? 0)) {
                for (const gx of stops(size.grid?.width ?? 0, size.grid?.clientWidth ?? 0)) {
                    if (size.grid) await page.evaluate(([left, top]) => document.querySelector('[data-atom-calendar-grid]').scrollTo(left, top), [gx, gy])

                    await page.waitForTimeout(40)
                    take(await measureContrast(page))
                }
            }
        }
    }

    await page.evaluate(({ page: at, grid }) => {
        document.scrollingElement.scrollTo(at[0], at[1])
        if (grid) document.querySelector('[data-atom-calendar-grid]').scrollTo(grid[0], grid[1])
    }, saved)

    const rows = (map) => [...map.values()].sort((a, b) => a.ratio - b.ratio)
    const never = (kind) => [...merged.tried[kind]].filter((id) => !merged.seen[kind].has(id))

    return {
        text: rows(merged.text),
        graphics: rows(merged.graphics),
        skipped: { text: never('text').length, graphics: never('graphics').length },
        skippedExamples: { text: never('text').map((id) => merged.labels[id]), graphics: never('graphics').map((id) => merged.labels[id]) },
        tried: { text: merged.tried.text.size, graphics: merged.tried.graphics.size },
        colors: [...merged.colors].sort(),
        present: [...merged.present].sort(),
        steps: merged.steps,
    }
}
