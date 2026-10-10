import floatingui from '../helpers/floatingui.js'
import safeUrl from '../helpers/safe-url.js'
import { addMonths, dateOf, fromLocalDate, monthOf, parseDate, toLocalDate } from './calendar/dates.js'
import { format } from './calendar/format.js'
import { dayEntries, isBar, monthLayout } from './calendar/layout.js'

// <atom:calendar>. The server computes the window, the labels and the items and hands them over as one
// JSON payload on the root's data-atom-calendar-payload attribute; this factory only lays it out. It holds
// nothing that is per render: `x-data="calendar"` is a constant, so a Livewire morph never re-evaluates it,
// and a new payload is picked up by a MutationObserver on that one attribute. Text is printed with x-text
// only, and every colour class comes from the palette the Blade file prints (class strings stay in Blade
// so the host's Tailwind sees them).

// The views the body can draw. The payload's config may ask for more; week and day come with the time grid.
const IMPLEMENTED_VIEWS = ['month']

const HEX = /^#[0-9a-f]{3,8}$/i

export default () => ({
    payload: null,
    palette: {},
    model: null,
    view: 'month',
    anchor: '',
    loading: false,
    jump: null,
    popoverDate: null,

    get labels () {
        return this.payload?.labels ?? {}
    },

    get config () {
        return this.payload?.config ?? {}
    },

    get views () {
        return (this.config.views ?? []).filter((view) => IMPLEMENTED_VIEWS.includes(view))
    },

    // The month view always shows the window the server built around its anchor, and names that month.
    get title () {
        let date = this.payload?.anchor

        if (!date) return { month: '', year: '', text: '' }

        let month = format(date, 'F', this.labels)
        let year = format(date, 'Y', this.labels)

        return { month, year, text: month + ' ' + year }
    },

    get weeks () {
        if (!this.payload) return []

        return monthLayout(this.payload.window.days, this.payload.items, {
            max: this.config.max ?? null,
            today: this.payload.today,
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

        this.$root._atomCalendarCleanup = () => {
            observer.disconnect()
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

        if (first || !this.views.includes(this.view)) {
            this.view = this.views.includes(payload.config.view) ? payload.config.view : (this.views[0] ?? 'month')
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
        let inWindow = this.view === 'month'
            ? monthOf(target) === monthOf(this.payload.anchor)
            : target >= start && target <= end

        if (inWindow) {
            this.anchor = target
            this.$wire.$set(this.model, target, false)

            return
        }

        this.loading = true

        try {
            await this.$wire.$set(this.model, target)
        } catch (error) {
            console.error('[atom:calendar]', error)
        } finally {
            this.loading = false
        }
    },

    prev () {
        return this.navigate(addMonths(this.anchor, -1))
    },

    next () {
        return this.navigate(addMonths(this.anchor, 1))
    },

    today () {
        return this.navigate(this.payload.today)
    },

    setView (view) {
        if (!this.views.includes(view) || view === this.view) return

        this.closeDay(false)
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

        // anchored to the day's own cell, which stays put when the list is opened from a chip inside it
        let anchor = this.$root.querySelector('[data-atom-calendar-day][data-date="' + date + '"]') ?? trigger

        this.floatCleanup = floatingui(anchor, panel, { placement: 'bottom-start' })

        this.dismissOutside?.abort()
        this.dismissOutside = new AbortController()

        let { signal } = this.dismissOutside

        document.addEventListener('pointerdown', (event) => {
            if (!panel.contains(event.target) && !this.popoverTrigger?.contains(event.target)) this.closeDay(false)
        }, { signal, capture: true })

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
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

    // Preset names map to the classes the Blade file printed; a hex colour has none and is painted
    // from --atom-calendar-hue by atom.css instead.
    isHex (chip) {
        return typeof chip.color === 'string' && HEX.test(chip.color)
    },

    colorClass (chip, part) {
        if (this.isHex(chip)) return ''

        return (this.palette[chip.color] ?? this.palette.blue ?? {})[part] ?? ''
    },

    hue (chip) {
        return this.isHex(chip) ? { '--atom-calendar-hue': chip.color } : {}
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
