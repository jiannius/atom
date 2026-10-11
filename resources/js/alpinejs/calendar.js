import floatingui from '../helpers/floatingui.js'
import safeUrl from '../helpers/safe-url.js'
import { edgeColors } from './calendar/colors.js'
import { addDays, addMonths, dateOf, fromLocalDate, minuteOf, monthOf, nowAt, parseDate, toLocalDate } from './calendar/dates.js'
import { format } from './calendar/format.js'
import { allDayGhost, allDayShape, DAY_MINUTES, isLifted, previewShapes, timeGrid } from './calendar/grid.js'
import { selectionRange } from './calendar/gestures.js'
import { dayEntries, isBar, monthLayout, span } from './calendar/layout.js'
import pointer from './calendar/pointer.js'

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

// What a calendar with no `name` and no bound property builds its ids from: the order the page started them in. The `n` keeps
// them apart from the ids a name builds, which end in a hash (a name of "1" is `atom-calendar-1-<hash>`).
let instances = 0

export default () => ({
    ...pointer,

    payload: null,
    palette: {},
    model: null,
    // The root element, kept: Alpine's `$root` is resolved from the element that was evaluating last, and is gone (undefined) when
    // that one has been taken out of the page, as a header that is pressed is when it is replaced. What runs after a render uses this.
    rootEl: null,
    // what the ids inside the calendar are built from (see index.blade.php)
    uid: '',
    // The day of the month view that is the one tab stop of its grid, and where the arrow keys go from. It follows the focus, and a
    // key that leaves the window sets it to where the focus is heading; `keyPending` is that, until the window has come.
    focusDate: null,
    keyPending: false,
    keyAbort: null,
    view: 'month',
    anchor: '',
    loading: false,
    // where the last click that asked the server for another window is heading, until the server has answered
    pending: null,
    requests: 0,
    jump: null,
    popoverDate: null,
    // what opened the day list, 'day' (the day's own cell) or 'more' (a "+N more"): only that one says it is expanded
    popoverSource: '',
    // what the live region says: the title, written only when its text changes, so a payload that moves nothing the title says is not read out again
    liveTitle: '',
    // the wall-clock minute the now line stands at, `YYYY-MM-DDTHH:mm`: the server's clock, carried on by the browser's
    nowMoment: '',
    clockSeed: null,
    // the viewer's date today: the payload's, then the one the ticking clock reads. It changes once a day, so what is drawn
    // from it (the today marker) is laid out again then and not with the clock.
    todayDate: '',
    scrollPending: false,
    // What a gesture in progress draws, as { kind: 'select' | 'pending' | 'move' | 'resize', start, end, allDay?, id?, title? } in wall-clock
    // strings, or null. Selecting sets it ('select' while the pointer is down and after it, until the next payload; 'pending' while the host
    // holds the selection with waitUntil; 'move' and 'resize' while an item is being dragged or pulled by its end, the item being `id` and the range
    // where it would then be). The look fixture sets each state by hand to show it.
    // See previewBlocks() in calendar/grid.js.
    preview: null,
    // The gesture the pointer is making, or null: { kind: 'select' | 'move' | 'resize', area, id, touch, origin, point, active, timer, frame, abort, ... }.
    // See calendar/pointer.js.
    gesture: null,
    // Where the items that were moved or resized are drawn until the payload says: { [id]: { range: { start, end }, token, held, stale } }. See commitChange().
    overrides: {},
    // The id of the item whose chip has the focus, kept on it while it is drawn again (see keepItemFocus()), or null
    focusItem: null,
    // Held while a select waits for the host (see commitSelect): the placeholder is taken down when its promise settles, unless another gesture has put something else up
    pendingToken: null,
    // What takes a selection down that nothing is holding: see lingerSelection()
    lingerAbort: null,
    // the payload attribute as it was last read, so a render that leaves it as it was is not a new payload
    payloadText: '',
    // a payload came while a gesture was on: it waits for the gesture to end (see read() and endSelect())
    payloadHeld: false,
    hours: Array.from({ length: 24 }, (_, hour) => hour),

    get labels () {
        return this.payload?.labels ?? {}
    },

    get config () {
        return this.payload?.config ?? {}
    },

    // The payload's items, with the ones that were moved or resized where they were put: until the payload says otherwise (see commitChange)
    get items () {
        let items = this.payload?.items ?? []
        let { overrides } = this

        if (!Object.keys(overrides).length) return items

        return items.map((item) => (overrides[String(item.id)] ? { ...item, ...overrides[String(item.id)].range } : item))
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

        return timeGrid(this.gridDays, this.items, {
            today: this.todayDate,
            max: this.config.max ?? null,
            slotDuration: this.config.slotDuration ?? 30,
        })
    },

    // What a gesture in progress draws in each column of the time grid, by date. Apart from `grid` on purpose: the blocks are laid out
    // from the payload alone, so a preview that changes (a drag changes it on every move) leaves them as they are.
    get previews () {
        if (!this.payload || this.view === 'month') return {}

        return previewShapes(this.grid.columns, this.items, this.preview, { slotDuration: this.config.slotDuration ?? 30 })
    },

    // The gutter's labels: the time format without its minutes ("9 AM"), or a 24-hour one as it is ("09:00").
    get hourFormat () {
        let pattern = String(this.config.timeFormat ?? 'g:i A')

        return /[gh]/.test(pattern) ? pattern.replace(/[:.]?i/, '') : pattern
    },

    // What a selection, or an item waiting for the host, covers in the all-day row
    get allDayPreview () {
        if (!this.payload || this.view === 'month') return null

        return allDayShape(this.gridDays, this.preview)
    },

    // What an all-day item being moved or resized covers in the all-day row
    get allDayItemGhost () {
        if (!this.payload || this.view === 'month') return null

        return allDayGhost(this.gridDays, this.items, this.preview)
    },

    get weeks () {
        if (!this.payload) return []

        return monthLayout(this.payload.window.days, this.items, {
            max: this.config.max ?? null,
            today: this.todayDate,
            preview: this.preview,
        })
    },

    get popoverEntries () {
        if (!this.popoverDate || !this.payload) return []

        return dayEntries(this.items, this.popoverDate).map((item) => ({
            ...item,
            key: String(item.id),
            kind: isBar(item) ? 'bar' : 'dot',
            before: false,
            after: false,
            // a row of the list is not on a grid to be put somewhere on
            static: true,
        }))
    },

    // The one day of the month view the Tab key stops on: the one that has the focus, or was last moved to, while it is in the window;
    // else today (when the month shown is today's), else the day the calendar is anchored to. Never none, so the grid is always reachable.
    get tabDate () {
        let days = this.payload?.window?.days ?? []
        let has = (date) => Boolean(date) && days.some((day) => day.date === date)
        let anchor = this.payload?.anchor

        if (has(this.focusDate)) return this.focusDate
        if (has(this.todayDate) && monthOf(this.todayDate) === monthOf(anchor)) return this.todayDate

        return has(anchor) ? anchor : (days[0]?.date ?? '')
    },

    // What the second live region says: that a selection is with the host, for as long as its placeholder (which is hidden from assistive tech) is up
    get announcement () {
        return this.preview?.kind === 'pending' ? (this.labels.ui?.creating ?? '') : ''
    },

    get popoverLabel () {
        return this.popoverDate ? this.longDate(this.popoverDate) : ''
    },

    init () {
        // Alpine can re-enter on a node that is already set up: drop the previous run first.
        this.$root._atomCalendarCleanup?.()

        let controller = new AbortController()
        let { signal } = controller

        this.rootEl = this.$root
        this.model = this.$root.dataset.atomCalendarModel || null
        this.uid = this.$root.dataset.atomCalendarUid || 'atom-calendar-n' + (++instances)
        this.palette = this.readJson(this.$root.dataset.atomCalendarPalette) ?? {}
        this.read()
        this.liveTitle = this.title.text
        this.$watch('title.text', (text) => { this.liveTitle = text })

        let observer = new MutationObserver(() => this.read())
        observer.observe(this.$root, { attributes: true, attributeFilter: ['data-atom-calendar-payload'] })

        // The date picker in the title hands back the browser-local instant of the day that was picked.
        this.$watch('jump', (value) => {
            let date = fromLocalDate(value)

            if (date && date !== this.anchor) this.navigate(date)
        })

        // The focus that is kept on the chip of an item that was moved (see keepItemFocus()) follows it when the items are drawn again: a payload that is
        // read and a held move that is settled both change `overrides`. It ends when it is taken elsewhere (see focusChip()) or by a press on the page.
        this.$watch('overrides', () => {
            if (this.focusItem !== null) this.$nextTick(() => this.focusChip())
        })
        document.addEventListener('pointerdown', () => { this.focusItem = null }, { signal, capture: true })

        // The day list lives in the top layer: it must not outlive a wire:navigate page swap. Nor does a gesture, which is on the document.
        document.addEventListener('livewire:navigating', () => {
            this.cancelGesture()
            this.closeDay(false)
        }, { signal })

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
            this.keyAbort?.abort()
            this.endGesture(false)
            this.lingerAbort?.abort()
            this.pendingToken = null
            this.overrides = {}
            this.focusItem = null
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

    // Take the payload on the root. A payload that cannot be read keeps what is already on screen. While a gesture is on nothing is taken: the
    // days, the rows and the boxes the pointer is measured against stay as they are under it, and the latest payload is read when the gesture ends.
    read () {
        if (this.gesture) {
            this.payloadHeld = true

            // unless it takes the item that is being moved away: then there is nothing to wait for
            this.checkGestureItem()

            return
        }

        let text = this.$root.getAttribute('data-atom-calendar-payload')
        let payload = this.readJson(text)

        if (!payload || !Array.isArray(payload.window?.days) || !Array.isArray(payload.items) || !payload.labels || !payload.config) {
            console.warn('[atom:calendar] the payload could not be read, keeping what is shown')

            return
        }

        let first = this.payload === null

        // A new payload is the answer to what was selected, so a selection that nothing holds is done with. One being made, and one the host holds
        // with waitUntil, are not the render's to take down.
        if (!first && text !== this.payloadText) {
            this.clearSelection()

            // The payload is the answer to what was moved or resized, so an item that was put somewhere is where the payload says it is. Unless the host
            // holds it (waitUntil): that one stays where it was put, and is marked as one the payload has been and gone past (see commitChange).
            this.overrides = Object.fromEntries(Object.entries(this.overrides).filter(([, entry]) => entry.held).map(([id, entry]) => [id, { ...entry, stale: true }]))
        }

        this.payloadText = text

        // The focus is on a day of the month when the window changes (a key that left the old one, or a render): the days of a new window
        // are new nodes, so it is put back on the day it was on once they are drawn. A key that is still on its way owns the focus only
        // while it is nobody's (the page's, or a day's): a Tab or a click elsewhere in the meantime took it, and it is not taken back.
        let restoreFocus = !first && this.view === 'month' && (this.dayHasFocus() || (this.keyPending && this.focusIsFree()))

        // a new month by the toolbar or the picker is a new place to start from, and not by a key that is still on its way there
        if (!first && !this.keyPending && monthOf(payload.anchor) !== monthOf(this.payload.anchor)) this.focusDate = null

        this.payload = payload
        this.anchor = payload.anchor
        this.todayDate = payload.today
        this.seedClock()

        if (first || !this.views.includes(this.view)) {
            this.view = this.views.includes(payload.config.view) ? payload.config.view : (this.views[0] ?? 'month')
            this.scrollPending = this.view !== 'month'
        }

        this.jump = toLocalDate(payload.anchor)

        // the day list follows the data: an item that went away leaves it, and an empty day closes it. If the focus was in it,
        // it is taken to where the list came from, so it does not fall to the page.
        if (this.popoverDate && !dayEntries(payload.items, this.popoverDate).length) {
            let trigger = this.popoverTrigger
            let inside = this.$refs.popover?.contains(document.activeElement)

            this.closeDay(false)

            if (inside) this.$nextTick(() => this.focusAfterList(trigger))
            else if (restoreFocus) this.$nextTick(() => this.focusDay())

            return
        }

        if (restoreFocus) this.$nextTick(() => this.focusDay())
    },

    // Is the focus nobody's: on the page itself (the node it was on is gone), or on a day of this grid?
    focusIsFree () {
        let active = document.activeElement

        return !active || active === document.body || this.dayHasFocus()
    },

    // Where the focus goes when the day list closes under it: the button or day it opened from, or if that is gone the day that is the
    // month's tab stop, or the title.
    focusAfterList (trigger) {
        if (trigger?.isConnected) return trigger.focus({ preventScroll: true })

        if (this.view === 'month') return this.focusDay()

        this.rootEl.querySelector('button[data-atom-calendar-title]')?.focus({ preventScroll: true })
    },

    // Is the focus on a day of the month grid?
    dayHasFocus () {
        let active = document.activeElement

        return Boolean(active) && this.rootEl.contains(active) && active.matches('[data-atom-calendar-day]')
    },

    // Put the focus on the day that is the tab stop of the month grid.
    focusDay () {
        let cell = this.rootEl.querySelector('[data-atom-calendar-day][data-date="' + this.tabDate + '"]')

        if (cell && document.activeElement !== cell) cell.focus()
    },

    // The keys of the month grid, from the day that has the focus. Arrows go a day or a week, Home and End to the ends of the week row,
    // PageUp and PageDown a month; Enter and Space open the day's list. A day outside the window asks the server for another one
    // (a calendar with no bound property has nowhere to go), and the focus follows once it is drawn.
    dayKey (day, event) {
        if (event.target !== event.currentTarget || event.ctrlKey || event.metaKey || event.altKey || this.view !== 'month' || !this.payload) return

        // Shift with an arrow or a page key is for selecting a range, which is not wired yet: it is left to the browser
        if (event.shiftKey && /^(Arrow|Page)/.test(event.key)) return

        // two quick presses go on from where the first one is heading, as the buttons of the toolbar do
        let from = this.keyPending && this.focusDate ? this.focusDate : day.date
        let days = this.payload.window.days
        let row = Math.floor(days.findIndex((candidate) => candidate.date === day.date) / 7) * 7
        let byMonth = false
        let target

        switch (event.key) {
            case 'ArrowLeft': target = addDays(from, -1); break
            case 'ArrowRight': target = addDays(from, 1); break
            case 'ArrowUp': target = addDays(from, -7); break
            case 'ArrowDown': target = addDays(from, 7); break
            case 'Home': target = days[row]?.date; break
            case 'End': target = days[row + 6]?.date; break
            case 'PageUp': target = addMonths(from, -1); byMonth = true; break
            case 'PageDown': target = addMonths(from, 1); byMonth = true; break
            case 'Enter':
            case ' ':
                event.preventDefault()

                // A day with items opens its list, as it does on a calendar that is not selectable. On one that is, an empty day is selected, as a click on
                // it selects it: a day with nothing in it has nothing for a list to open.
                if (this.config.selectable && !dayEntries(this.items, day.date).length) {
                    if (!this.gesture && !event.repeat) this.commitSelect(selectionRange(day.date, day.date))

                    return
                }

                this.openDay(day.date, event.currentTarget)

                return
            default: return
        }

        event.preventDefault()

        if (target) this.moveFocus(target, byMonth)
    },

    // Take the focus to a day: at once if it is in the grid (a month does not change by walking onto a day of the next one), else by asking for
    // the window around it. A month step always lands on the new month.
    async moveFocus (target, byMonth = false) {
        let shown = byMonth ? monthOf(target) === monthOf(this.payload.anchor) : this.payload.window.days.some((day) => day.date === target)

        if (shown && !this.pending) {
            this.focusDate = target
            this.focusDay()

            return
        }

        if (!this.model) return

        // the last key to leave the window owns the flag: an answer to an earlier one does not clear it
        let token = this.keyPending = Symbol()

        this.focusDate = target

        // the focus is the key's until the window comes, unless the viewer takes it somewhere else first (a Tab, or a click, out of the grid)
        this.keyAbort?.abort()

        let abort = this.keyAbort = new AbortController()
        let leave = (event) => {
            if (!this.rootEl.querySelector('[role="grid"]')?.contains(event.target)) this.keyPending = false
        }

        document.addEventListener('focusin', leave, { signal: abort.signal, capture: true })
        document.addEventListener('pointerdown', leave, { signal: abort.signal, capture: true })

        try {
            await this.navigate(target)
        } finally {
            if (this.keyPending === token) this.keyPending = false

            if (this.keyAbort === abort) {
                abort.abort()
                this.keyAbort = null
            }
        }
    },

    // Move to a date (`Y-m-d`). Staying inside the window needs no round trip; leaving it asks the server
    // for the window around the new date by setting the bound property, and the new payload arrives on the
    // root. Without a bound property (static mode) there is nowhere to go.
    async navigate (date) {
        let target = parseDate(date) ? dateOf(date) : null

        if (!target || !this.model || !this.payload) return

        this.closeDay(false)
        this.clearSelection()

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

    // A day's header in the week view: that day, by the day. The window holds the whole week, so it asks for nothing.
    showDay (date) {
        if (this.view === 'day' || !parseDate(date)) return

        // the header that is pressed is gone in the day view (it is the day's title there, nothing to press): the focus goes to the title
        let head = document.activeElement?.closest?.('[data-atom-calendar-column-head]')
        let focused = Boolean(head) && this.rootEl.contains(head)

        this.closeDay(false)
        this.anchor = date
        this.jump = toLocalDate(date)

        if (this.model) this.navigate(date)

        this.setView('day')

        if (focused) this.$nextTick(() => this.rootEl.querySelector('[data-atom-calendar-column-head]')?.focus({ preventScroll: true }))
    },

    setView (view) {
        if (!this.views.includes(view) || view === this.view) return

        this.closeDay(false)
        this.clearSelection()

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
        if (!dayEntries(this.items, date).length) return

        let panel = this.$refs.popover

        if (this.popoverDate === date && panel.matches(':popover-open')) {
            return this.closeDay()
        }

        // a different day is a different anchor: put the old position loop away first
        this.floatCleanup?.()

        this.popoverDate = date
        this.popoverTrigger = trigger
        this.popoverSource = trigger?.matches?.('[data-atom-calendar-day]') ? 'day' : 'more'

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
            // only when the focus is in the list or on what opened it: an Escape pressed anywhere else on the page is the page's
            let here = panel.contains(event.target) || Boolean(this.popoverTrigger?.contains(event.target))

            if (event.key === 'Escape' && here) {
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
        this.popoverSource = ''

        if (panel?.matches(':popover-open')) panel.hidePopover()

        if (restoreFocus && trigger?.isConnected) trigger.focus({ preventScroll: true })
    },

    // Tab at either end of the day list leaves it for the trigger it opened from, as it would on a page: the list is after the
    // grid in the document, so the next stop would be the page's, a long way from the day.
    popoverTab (event) {
        let panel = this.$refs.popover
        let items = [...panel.querySelectorAll('button, a[href], [tabindex="0"]')].filter((el) => !el.disabled && el.checkVisibility?.() !== false)
        let active = document.activeElement

        if (event.shiftKey ? (active === panel || active === items[0]) : active === items.at(-1)) {
            event.preventDefault()
            this.closeDay()
        }
    },

    dayClick (day, event) {
        // on a selectable calendar a press on a day is a selection (selectStart), which opens its list only where a tap does (see selectEnd)
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

    // The whole title as a tooltip, only while the one shown is cut short: the name of the item says it in full (and a `title` beside a name is read as well)
    tooltip (el) {
        let text = el.querySelector('[data-atom-calendar-chip-title]')

        if (text && text.scrollWidth > text.clientWidth) el.setAttribute('title', text.textContent)
        else el.removeAttribute('title')
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

    // What an item is called, wherever it is drawn (a block, a bar, a chip, a row of the day list): its title, when, and what its colour
    // or overdue stands for, which the chip says in part (a narrow one drops its time) or not at all. A hex colour has no name to give.
    itemLabel (chip) {
        let ui = this.labels.ui ?? {}
        let color = chip.danger || this.isHex(chip) ? null : ui.colors?.[chip.color]

        return [chip.title, this.itemWhen(chip), chip.danger ? ui.overdue : color].filter(Boolean).join(', ')
    },

    // When an item is: its day and time; or, for one that covers more than a day, from its first day to its last, both ends said whole
    itemWhen (chip) {
        let ui = this.labels.ui ?? {}
        let { first, last } = span(chip)
        let range = (from, to) => (ui.range ?? ':start to :end').replace(/:(start|end)/g, (_, which) => (which === 'start' ? from : to))

        if (chip.allDay) return first === last ? [this.longDate(first), ui.all_day].filter(Boolean).join(', ') : range(this.longDate(first), this.longDate(last))

        if (first !== last && chip.end) {
            return range(
                this.longDate(first) + ', ' + this.timeLabel(chip),
                this.longDate(dateOf(chip.end)) + ', ' + format(chip.end, this.config.timeFormat, this.labels),
            )
        }

        return this.longDate(first) + ', ' + this.timeRange(chip)
    },

    longDate (date) {
        return format(date, 'l', this.labels) + ', ' + format(date, this.config.dateFormat, this.labels)
    },

    dayLabel (day, count = day.count) {
        return this.longDate(day.date) + (count > 0 ? ', ' + this.countLabel(count) : '')
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
