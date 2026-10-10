import floatingui from '../helpers/floatingui.js'
import safeUrl from '../helpers/safe-url.js'
import { edgeColors } from './calendar/colors.js'
import { addDays, addMonths, dateOf, fromLocalDate, minuteOf, monthOf, nowAt, parseDate, toLocalDate } from './calendar/dates.js'
import { format } from './calendar/format.js'
import { DAY_MINUTES, isLifted, previewShapes, timeGrid } from './calendar/grid.js'
import { dayEntries, isBar, monthLayout } from './calendar/layout.js'

// <atom:calendar>. The server computes the window, the labels and the items and hands them over as one
// JSON payload on the root's data-atom-calendar-payload attribute; this factory only lays it out. It holds
// nothing that is per render: `x-data="calendar"` is a constant, so a Livewire morph never re-evaluates it,
// and a new payload is picked up by a MutationObserver on that one attribute. Text is printed with x-text
// only, and every colour class comes from the palette the Blade file prints (class strings stay in Blade
// so the host's Tailwind sees them).

// The views the body can draw. The payload's config may ask for fewer, never for more.
const IMPLEMENTED_VIEWS = ['month', 'week', 'day']

// How often the browser looks at its clock for the now line. It moves when the minute does.
const CLOCK_INTERVAL = 10000

// The shortest a block is drawn, half an hour of the grid; atom.css sets the same as its min-height (and grid.js's
// MIN_DRAWN_MINUTES is that half hour), and the block is moved up to fit above the day's end.
const MIN_BLOCK = 'calc(var(--atom-calendar-hour) / 2)'

// How far under the header the scroll-time line is put, in px
const SCROLL_MARGIN = 8

const pad = (n) => String(n).padStart(2, '0')

// 3, 4, 6 or 8 digits (#rgb, #rgba, #rrggbb, #rrggbbaa), as the server's Color::isHex() and colors.js read them: a 5 or 7 digit one is no colour
const HEX = /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i

// a title's year, when the locale writes it last
const YEAR_AT_END = /\s*Y$/

export default () => ({
    payload: null,
    palette: {},
    model: null,
    view: 'month',
    anchor: '',
    loading: false,
    // where the last click that asked the server for another window is heading, until the server has answered
    pending: null,
    requests: 0,
    jump: null,
    popoverDate: null,
    // the wall-clock minute the now line stands at, `YYYY-MM-DDTHH:mm`: the server's clock, carried on by the browser's
    nowMoment: '',
    clockSeed: null,
    // the viewer's date today: the payload's, then the one the ticking clock reads. It changes once a day, so what is drawn
    // from it (the today marker) is laid out again then and not with the clock.
    todayDate: '',
    scrollPending: false,
    // What a gesture in progress draws, as { kind: 'select' | 'pending' | 'move' | 'resize', start, end, id?, title? } in wall-clock
    // strings, or null. The gestures that set it (selecting, dragging and resizing) are not wired yet: this is the state they
    // will set, and the look fixture sets it by hand to show each one. See previewBlocks() in calendar/grid.js.
    preview: null,
    hours: Array.from({ length: 24 }, (_, hour) => hour),

    get labels () {
        return this.payload?.labels ?? {}
    },

    get config () {
        return this.payload?.config ?? {}
    },

    get views () {
        return (this.config.views ?? []).filter((view) => IMPLEMENTED_VIEWS.includes(view))
    },

    // What the toolbar names: the month the server built the window around, or the week or day on screen, each written the
    // way the server says the locale writes it. A year at the end of a title is its lighter half.
    get title () {
        if (!this.payload) return { month: '', year: '', text: '' }

        let labels = this.labels
        let text = (month, year) => ({ month, year, text: [month, year].filter(Boolean).join(' ') })

        // one date in one display format, its trailing year split off
        let one = (date, pattern) => {
            let year = YEAR_AT_END.test(pattern)

            return text(format(date, year ? pattern.replace(YEAR_AT_END, '') : pattern, labels), year ? format(date, 'Y', labels) : '')
        }

        if (this.view === 'month') return one(this.payload.anchor, labels.month_title ?? 'F Y')

        let days = this.gridDays

        if (!days.length) return { month: '', year: '', text: '' }

        let first = days[0].date
        let last = days[days.length - 1].date

        if (this.view === 'day') return one(first, labels.day_title ?? 'j F Y')

        // a week: a format for each end ("5 – 11 October 2026", "28 Sep – 4 Oct 2026", "28 Dec 2026 – 3 Jan 2027")
        let shape = monthOf(first) === monthOf(last) ? 'same_month' : (first.slice(0, 4) === last.slice(0, 4) ? 'cross_month' : 'cross_year')
        let [from, to] = labels.week_title?.[shape] ?? ['j M Y', 'j M Y']
        let year = YEAR_AT_END.test(to)

        return text(
            format(first, from, labels) + ' – ' + format(last, year ? to.replace(YEAR_AT_END, '') : to, labels),
            year ? format(last, 'Y', labels) : '',
        )
    },

    // The days the week or day view shows: the week of the anchor, or the anchor alone. The window is whole weeks, so
    // a week is always inside it.
    get gridDays () {
        let days = this.payload?.window?.days ?? []
        let at = days.findIndex((day) => day.date === this.anchor)

        if (at === -1) at = 0

        if (this.view === 'day') return days.slice(at, at + 1)

        let from = at - (at % 7)

        return days.slice(from, from + 7)
    },

    get grid () {
        if (!this.payload || this.view === 'month') return { columns: [], allDay: { chips: [], more: [] } }

        return timeGrid(this.gridDays, this.payload.items, {
            today: this.todayDate,
            max: this.config.max ?? null,
            slotDuration: this.config.slotDuration ?? 30,
        })
    },

    // What a gesture in progress draws in each column of the time grid, by date. Apart from `grid` on purpose: the blocks are laid out
    // from the payload alone, so a preview that changes (a drag changes it on every move) leaves them as they are.
    get previews () {
        if (!this.payload || this.view === 'month') return {}

        return previewShapes(this.grid.columns, this.payload.items, this.preview, { slotDuration: this.config.slotDuration ?? 30 })
    },

    // The gutter's labels: the time format without its minutes ("9 AM"), or a 24-hour one as it is ("09:00").
    get hourFormat () {
        let pattern = String(this.config.timeFormat ?? 'g:i A')

        return /[gh]/.test(pattern) ? pattern.replace(/[:.]?i/, '') : pattern
    },

    get weeks () {
        if (!this.payload) return []

        return monthLayout(this.payload.window.days, this.payload.items, {
            max: this.config.max ?? null,
            today: this.todayDate,
            preview: this.preview,
        })
    },

    get popoverEntries () {
        if (!this.popoverDate || !this.payload) return []

        return dayEntries(this.payload.items, this.popoverDate).map((item) => ({
            ...item,
            key: String(item.id),
            kind: isBar(item) ? 'bar' : 'dot',
            before: false,
            after: false,
        }))
    },

    get popoverLabel () {
        return this.popoverDate ? this.longDate(this.popoverDate) : ''
    },

    init () {
        // Alpine can re-enter on a node that is already set up: drop the previous run first.
        this.$root._atomCalendarCleanup?.()

        let controller = new AbortController()
        let { signal } = controller

        this.model = this.$root.dataset.atomCalendarModel || null
        this.palette = this.readJson(this.$root.dataset.atomCalendarPalette) ?? {}
        this.read()

        let observer = new MutationObserver(() => this.read())
        observer.observe(this.$root, { attributes: true, attributeFilter: ['data-atom-calendar-payload'] })

        // The date picker in the title hands back the browser-local instant of the day that was picked.
        this.$watch('jump', (value) => {
            let date = fromLocalDate(value)

            if (date && date !== this.anchor) this.navigate(date)
        })

        // The day list lives in the top layer: it must not outlive a wire:navigate page swap.
        document.addEventListener('livewire:navigating', () => this.closeDay(false), { signal })

        // The now line moves with the minute. The clock is read off an attribute the observer above does not watch, so a
        // render that only moves the clock lays nothing out again; from there the browser's own elapsed time carries it.
        let clock = setInterval(() => this.tick(), CLOCK_INTERVAL)

        // The time grid opens at `scroll-time` once it can be measured: it is hidden (no size) in the month view, and
        // in a closed modal, until it is shown.
        let grid = this.$root.querySelector('[data-atom-calendar-grid]')
        let resize = typeof ResizeObserver === 'function' && grid ? new ResizeObserver(() => this.scrollGrid()) : null

        resize?.observe(grid)

        this.$root._atomCalendarCleanup = () => {
            observer.disconnect()
            resize?.disconnect()
            clearInterval(clock)
            controller.abort()
            this.closeDay(false)
            delete this.$root._atomCalendarCleanup
        }
    },

    destroy () {
        this.$root._atomCalendarCleanup?.()
    },

    readJson (text) {
        try {
            let value = JSON.parse(text)

            return value && typeof value === 'object' ? value : null
        } catch {
            return null
        }
    },

    // Take the payload on the root. A payload that cannot be read keeps what is already on screen.
    read () {
        let payload = this.readJson(this.$root.getAttribute('data-atom-calendar-payload'))

        if (!payload || !Array.isArray(payload.window?.days) || !Array.isArray(payload.items) || !payload.labels || !payload.config) {
            console.warn('[atom:calendar] the payload could not be read, keeping what is shown')

            return
        }

        let first = this.payload === null

        this.payload = payload
        this.anchor = payload.anchor
        this.todayDate = payload.today
        this.seedClock()

        if (first || !this.views.includes(this.view)) {
            this.view = this.views.includes(payload.config.view) ? payload.config.view : (this.views[0] ?? 'month')
            this.scrollPending = this.view !== 'month'
        }

        this.jump = toLocalDate(payload.anchor)

        // the day list follows the data: an item that went away leaves it, and an empty day closes it
        if (this.popoverDate && !dayEntries(payload.items, this.popoverDate).length) this.closeDay(false)
    },

    // Move to a date (`Y-m-d`). Staying inside the window needs no round trip; leaving it asks the server
    // for the window around the new date by setting the bound property, and the new payload arrives on the
    // root. Without a bound property (static mode) there is nowhere to go.
    async navigate (date) {
        let target = parseDate(date) ? dateOf(date) : null

        if (!target || !this.model || !this.payload) return

        this.closeDay(false)

        let { start, end } = this.payload.window

        // with a window on its way the screen is not where the next step starts: it always asks the server
        let inWindow = !this.pending && (this.view === 'month'
            ? monthOf(target) === monthOf(this.payload.anchor)
            : target >= start && target <= end)

        if (inWindow) {
            this.anchor = target
            // the picker in the title shows the day on screen too
            this.jump = toLocalDate(target)
            this.$wire.$set(this.model, target, false)

            return
        }

        this.pending = target
        this.requests++
        this.loading = true

        try {
            await this.$wire.$set(this.model, target)
        } catch (error) {
            console.error('[atom:calendar]', error)
        } finally {
            if (--this.requests === 0) {
                this.pending = null
                this.loading = false
            }
        }
    },

    // Step from where the last click is heading, so two quick clicks on Next go two months (or weeks, or days), not
    // one twice. A month steps by month, a week by seven days and a day by one.
    step (direction) {
        let from = this.pending ?? this.anchor

        return this.navigate(this.view === 'month'
            ? addMonths(from, direction)
            : addDays(from, direction * (this.view === 'week' ? 7 : 1)))
    },

    prev () {
        return this.step(-1)
    },

    next () {
        return this.step(1)
    },

    today () {
        return this.navigate(this.todayDate)
    },

    setView (view) {
        if (!this.views.includes(view) || view === this.view) return

        this.closeDay(false)

        // the grid is hidden in the month view and has no scroll position there: it opens at `scroll-time` again
        if (this.view === 'month') this.scrollPending = true

        this.view = view

        // the month view shows the window of the server's anchor: come back to the month the anchor is in
        if (view === 'month' && monthOf(this.anchor) !== monthOf(this.payload.anchor)) {
            this.navigate(this.anchor)
        }
    },

    // The day list: a popover with every item of one day, from "+N more" or a click on the day.
    openDay (date, trigger) {
        if (!dayEntries(this.payload.items, date).length) return

        let panel = this.$refs.popover

        if (this.popoverDate === date && panel.matches(':popover-open')) {
            return this.closeDay()
        }

        // a different day is a different anchor: put the old position loop away first
        this.floatCleanup?.()

        this.popoverDate = date
        this.popoverTrigger = trigger

        if (!panel.matches(':popover-open')) panel.showPopover()

        // anchored to the day's own cell, which stays put when the list is opened from a chip inside it; in the time
        // grid a day is a column as tall as the day, so the "+N more" it was opened from is the anchor
        let anchor = (this.view === 'month' ? this.$root.querySelector('[data-atom-calendar-day][data-date="' + date + '"]') : null) ?? trigger

        this.floatCleanup = floatingui(anchor, panel, { placement: 'bottom-start' })

        this.dismissOutside?.abort()
        this.dismissOutside = new AbortController()

        let { signal } = this.dismissOutside

        document.addEventListener('pointerdown', (event) => {
            if (!panel.contains(event.target) && !this.popoverTrigger?.contains(event.target)) this.closeDay(false)
        }, { signal, capture: true })

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                // not the modal's either: a dialog closes on Escape by itself unless it is prevented
                event.preventDefault()
                event.stopPropagation()
                this.closeDay()
            }
        }, { signal, capture: true })

        panel.focus({ preventScroll: true })
    },

    closeDay (restoreFocus = true) {
        let panel = this.$refs.popover
        let trigger = this.popoverTrigger

        this.dismissOutside?.abort()
        this.dismissOutside = null
        this.floatCleanup?.()
        this.floatCleanup = null
        this.popoverDate = null
        this.popoverTrigger = null

        if (panel?.matches(':popover-open')) panel.hidePopover()

        if (restoreFocus && trigger?.isConnected) trigger.focus({ preventScroll: true })
    },

    dayClick (day, event) {
        // selecting a range to create an item is a different gesture, not wired yet
        if (this.config.selectable) return

        if (day.count > 0) this.openDay(day.date, event.currentTarget)
    },

    seedClock () {
        this.clockSeed = { moment: this.$root.getAttribute('data-atom-calendar-now') ?? '', at: Date.now() }
        this.tick()
    },

    // Where the browser's clock says the server's was: the seed plus the time since it was read.
    tick () {
        let moment = nowAt(this.clockSeed?.moment, Date.now() - (this.clockSeed?.at ?? 0))

        if (moment !== this.nowMoment) this.nowMoment = moment

        // the clock passing midnight is a new today, though no render came to say so
        if (moment && dateOf(moment) !== this.todayDate) this.todayDate = dateOf(moment)
    },

    // The now line in a day's column: its place in the day and the time it stands for, or null when it is not that
    // day, the indicator is off or there is no clock.
    nowLine (column) {
        if (!this.config.nowIndicator || !this.nowMoment || dateOf(this.nowMoment) !== column.date) return null

        return { top: (minuteOf(this.nowMoment) / DAY_MINUTES * 100) + '%', time: format(this.nowMoment, this.config.timeFormat, this.labels) }
    },

    // Open the time grid at `scroll-time`, with that time just under the header that stays on top.
    scrollGrid () {
        let grid = this.$root.querySelector('[data-atom-calendar-grid]')
        let top = grid?.querySelector('[data-atom-calendar-grid-top]')
        let body = grid?.querySelector('[data-atom-calendar-grid-body]')

        if (!this.scrollPending || !grid?.clientHeight || !top || !body) return

        this.scrollPending = false

        let [hour, minute] = String(this.config.scrollTime ?? '06:00').split(':').map(Number)

        // a little below the header, so the hour's label (centred on its line) is not cut in half by it
        grid.scrollTop = body.offsetTop + ((hour * 60 + minute) / DAY_MINUTES) * body.offsetHeight - top.offsetHeight - SCROLL_MARGIN
    },

    // A link goes where it says, through the same scheme check the server made; anything else is a button
    // that tells the host which item was chosen.
    link (chip) {
        return typeof chip.href === 'string' ? safeUrl(chip.href) : null
    },

    itemClick (chip, event) {
        if (this.link(chip)) return

        event.preventDefault()
        this.$dispatch('calendar-item-click', { id: chip.id })
    },

    itemKey (chip, event) {
        if (this.link(chip)) return

        event.preventDefault()
        this.itemClick(chip, event)
    },

    // The original of an item being moved or resized is drawn faint while its ghost is up
    isLifted (chip) {
        return isLifted(this.preview, chip.id)
    },

    // Preset names map to the classes the Blade file printed; a hex colour has none and is painted
    // from --atom-calendar-hue by atom.css instead.
    isHex (chip) {
        return typeof chip.color === 'string' && HEX.test(chip.color)
    },

    colorClass (chip, part) {
        if (this.isHex(chip)) return ''

        // an item with no colour (or one the palette does not know) is neutral: zinc, not blue, so a host that
        // restyles blue does not recolour the items that never asked for it
        return (this.palette[chip.color] ?? this.palette.zinc ?? {})[part] ?? ''
    },

    // A hex colour: the colour itself for the tint, and the edge and the dot moved only as far as they need to show against the light
    // ground and the dark one (see calendar/colors.js); atom.css picks one by theme.
    hue (chip) {
        if (!this.isHex(chip)) return {}

        let edge = edgeColors(chip.color)

        return edge
            ? { '--atom-calendar-hue': chip.color, '--atom-calendar-edge': edge.light, '--atom-calendar-edge-dark': edge.dark }
            : { '--atom-calendar-hue': chip.color }
    },

    // A timed block's place in its column: its top and height in the day and its lane across the column. It is never
    // drawn shorter than MIN_BLOCK, and is moved up to stay above the end of the day.
    blockStyle (block) {
        return {
            top: 'min(' + block.top + '%, calc(100% - ' + MIN_BLOCK + '))',
            height: block.height + '%',
            left: block.left + '%',
            width: block.width + '%',
        }
    },

    // The end of a range as the time of day, for the label a resize shows beside its edge
    endLabel (shape) {
        return shape.end ? format(shape.end, this.config.timeFormat, this.labels) : ''
    },

    // A selection's or a ghost's place over a month week's days, as shares of the week's width
    spanStyle (shape) {
        return { left: shape.left + '%', width: shape.width + '%' }
    },

    hourLabel (hour) {
        return hour === 0 ? '' : format('2026-01-01T' + pad(hour) + ':00', this.hourFormat, this.labels)
    },

    // "9:30 AM – 10:00 AM", or the start alone for an item with no end
    timeRange (chip) {
        let start = format(chip.start, this.config.timeFormat, this.labels)

        return chip.end ? start + ' – ' + format(chip.end, this.config.timeFormat, this.labels) : start
    },

    slotStyle (item) {
        return { gridColumn: item.col + ' / span ' + (item.span ?? 1), gridRow: String(item.row) }
    },

    // The icon the server rendered a template for, cloned into an element.
    setIcon (el, name) {
        let template = name ? this.$root.querySelector('template[data-atom-calendar-icon="' + CSS.escape(name) + '"]') : null

        el.replaceChildren(...(template ? [template.content.cloneNode(true)] : []))
    },

    timeLabel (chip) {
        return format(chip.start, this.config.timeFormat, this.labels)
    },

    longDate (date) {
        return format(date, 'l', this.labels) + ', ' + format(date, this.config.dateFormat, this.labels)
    },

    dayLabel (day) {
        let count = day.count > 0 ? ', ' + this.countLabel(day.count) : ''

        return this.longDate(day.date) + count
    },

    countLabel (count) {
        return ((count === 1 ? this.labels.ui?.item : this.labels.ui?.items) ?? '').replace(':count', () => String(count))
    },

    moreLabel (count) {
        return (this.labels.ui?.more ?? '').replace(':count', () => String(count))
    },

    weekdayName (date) {
        return format(date, 'D', this.labels)
    },
})
