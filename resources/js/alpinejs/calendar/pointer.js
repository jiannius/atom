import { addDays, diffDays } from './dates.js'
import { addMinutes, dayCell, hasMoved, indexAtX, LONG_PRESS, minuteAt, minutesBetween, momentAt, movedRange, resizedRange, scrollSpeed, selectionRange } from './gestures.js'
import { dayEntries, span } from './layout.js'

// The pointer half of <atom:calendar>'s engine, as methods the factory in ../calendar.js takes in: selecting to create, and moving and resizing an
// item. One gesture at a time is `this.gesture`, and the three kinds share its whole life: the listeners on the document (so a pointer that
// leaves the calendar is still followed), the threshold or the long press that starts it, the guards for a release nobody heard, Escape, the
// scroll of the time grid under a pointer at its edge, and the payload that waits until it is over. What differs is what each kind works out
// from where the pointer is (gestureRange), what it draws (show) and what it does when the pointer is let go (finish).

// A finger moves a little on its own while it presses; past this it is scrolling, and the gesture that was waiting for a long press is given up
const TOUCH_SLOP = 10

// What a press that starts a selection must not be on: an item, a "+N more" and a block of the time grid take their own click
const NOT_EMPTY_SPACE = '[data-atom-calendar-slot], [data-atom-calendar-more], [data-atom-calendar-block]'

// A press inside one of these is the host's own: a form it opens beside the calendar, a day list, a dialog. It does not take down the
// selection that form is for (see lingerSelection), unless the calendar is inside it.
const KEEPS_SELECTION = '[popover], dialog, form'

// How long after a drag lets go the click that follows it is still the drag's, in ms
const CLICK_WINDOW = 100

export default {
    // What presses start a gesture. A press with a key held is not one: Ctrl with the button is the context-menu click of a Mac, which sends a
    // contextmenu and no pointerup, so it would leave a gesture that nothing ends. Shift is kept for a range by keys, Alt and Meta for the
    // browser's and the system's own.
    pressable (event) {
        if (!this.payload || this.gesture || event.button !== 0 || event.isPrimary === false) return false

        return !(event.ctrlKey || event.metaKey || event.altKey || event.shiftKey)
    },

    // Selecting, to create. A press on empty space (a month day, the all-day row, a slot of the time grid) selects the day or the slot under it, and a
    // drag selects a range from where it was pressed to where it is let go: over days (the month and the all-day row, across weeks too) or over slots
    // (the time grid, on into the next column, one range of time). A click is the same gesture that has not gone far, so there is one path, from
    // pointerdown to pointerup, on pointer events alone: a mouse or a pen drags at once, a touch only after a long press (a finger that moves before
    // that is scrolling, and is left to), and a tap is a click.
    // The calendar creates nothing: it tells the host with `calendar-select` (see commitSelect).
    selectStart (event, area) {
        if (!this.config.selectable || !this.pressable(event)) return

        if (event.target.closest?.(NOT_EMPTY_SPACE)) return

        if (area === 'grid' && !event.target.closest?.('[data-atom-calendar-column]')) return

        let anchor = this.cellAt(area, event.clientX, event.clientY)

        if (!anchor) return

        this.beginGesture(event, { kind: 'select', area, el: event.currentTarget, anchor, current: anchor })
    },

    // Moving an item, or resizing it by its end (the handle on a bar's end or a block's bottom edge), from a press on it. The item is drawn where the
    // pointer would put it as soon as the press has gone far enough to be a drag (a mouse or a pen) or been held long enough (a touch); a press that
    // does neither is a click, which the browser delivers to the item as it always did (see itemClick). Whether the item may be dragged or resized
    // is the payload's, which has the component's props and the item's own flags in it.
    itemPress (chip, event) {
        if (!this.pressable(event)) return

        let el = event.currentTarget
        let area = el.closest('[data-atom-calendar-week]') ? 'month' : (el.closest('[data-atom-calendar-allday-area]') ? 'allday' : (el.closest('[data-atom-calendar-column]') ? 'grid' : null))

        // an item in the day list is not on a grid to be put somewhere on
        if (!area) return

        let item = this.items.find((candidate) => String(candidate.id) === String(chip.id))

        if (!item) return

        let resize = Boolean(event.target.closest?.('[data-atom-calendar-handle]'))

        if (resize ? !this.canResize(chip) : !this.canDrag(chip)) return

        let grab = this.cellAt(area, event.clientX, event.clientY)

        if (!grab) return

        this.beginGesture(event, { kind: resize ? 'resize' : 'move', area, el, item: { ...item }, grab, current: grab })
    },

    // Whether an item may be picked up, and whether it has an end to be pulled. A row of the day list is neither, and a bar or a block that
    // carries on past the part of it that is drawn here (`after`) has its end elsewhere.
    canDrag (chip) {
        return !chip.static && chip.draggable !== false
    },

    canResize (chip) {
        return !chip.static && chip.resizable !== false && chip.kind !== 'dot' && !chip.after
    },

    // Start following the pointer: on the document, so a pointer that leaves the calendar (or the window) is still followed and can be let go anywhere
    beginGesture (event, spec) {
        let abort = new AbortController()
        let { signal } = abort
        let touch = event.pointerType === 'touch'
        let point = { x: event.clientX, y: event.clientY }

        // What is read back from `this.gesture` is Alpine's reactive proxy of the object put there, not the object: a timer or a frame that is handed
        // the one and asks about the other (`this.gesture === gesture`) is never equal. So the gesture used here is the one read back, and what asks
        // whether a gesture is still the current one asks its abort signal, which is the gesture's own and is the same from either side.
        this.gesture = { ...spec, id: event.pointerId, touch, origin: point, point, active: false, timer: null, frame: null, abort }

        let gesture = this.gesture

        document.addEventListener('pointermove', (move) => this.gestureMove(move), { signal })
        document.addEventListener('pointerup', (up) => this.gestureEnd(up), { signal })
        document.addEventListener('pointercancel', (cancel) => { if (cancel.pointerId === gesture.id) this.cancelGesture(true) }, { signal })

        // A release that nothing heard must not leave the gesture following the pointer: the window lost its focus or its page (a click in a
        // context menu, a switch of window or tab) or a menu opened. A touch's long press sends a contextmenu of its own, which is prevented below.
        window.addEventListener('blur', () => this.cancelGesture(), { signal })
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') this.cancelGesture() }, { signal })

        if (!touch) document.addEventListener('contextmenu', () => this.cancelGesture(), { signal })

        // Escape gives the gesture up, and it is the gesture's: not the modal's the calendar may be in
        document.addEventListener('keydown', (key) => {
            if (key.key !== 'Escape') return

            key.preventDefault()
            key.stopPropagation()

            // the one end that leaves the button down: its release is still to come
            this.cancelGesture(false)
        }, { signal, capture: true })

        // dragging over the days must not select their text, nor start the browser's own drag of a link
        document.addEventListener('selectstart', (select) => select.preventDefault(), { signal })
        document.addEventListener('dragstart', (drag) => drag.preventDefault(), { signal })

        if (touch) {
            document.addEventListener('contextmenu', (menu) => menu.preventDefault(), { signal })

            // Once the long press has come the finger is the gesture's, and the page must not scroll under it. Listened to on the calendar and not the
            // document, where a listener is passive by default and cannot prevent anything.
            this.rootEl.addEventListener('touchmove', (move) => {
                if (gesture.active && move.cancelable) move.preventDefault()
            }, { signal, passive: false })

            gesture.timer = setTimeout(() => {
                this.activate(gesture)
                this.show(gesture)
            }, LONG_PRESS)
        }
    },

    gestureMove (event) {
        let gesture = this.gesture

        if (!gesture || event.pointerId !== gesture.id) return

        // A mouse or a pen that moves with no button down was let go where nothing heard it: the gesture is given up, not followed
        if (!gesture.touch && event.buttons === 0) return this.cancelGesture()

        gesture.point = { x: event.clientX, y: event.clientY }

        if (!gesture.active) {
            // a finger that moves before the long press is scrolling, which is not a gesture
            if (gesture.touch) {
                if (hasMoved(gesture.origin, gesture.point, TOUCH_SLOP)) this.endGesture()

                return
            }

            if (!hasMoved(gesture.origin, gesture.point)) return

            this.activate(gesture)
        }

        this.track(gesture)
    },

    // The press has become a drag. What shows it is on the calendar's body, which a render never touches: the cursor it all takes; the pressed
    // element is the one that gives up the browser's own handling of a touch (`touch-action`), and nothing else is.
    activate (gesture) {
        gesture.active = true

        let body = this.rootEl.querySelector('[data-atom-calendar-body]')

        if (gesture.kind !== 'select') body?.setAttribute('data-atom-calendar-gesture', gesture.kind === 'move' ? 'move' : (gesture.area === 'grid' ? 'resize-y' : 'resize-x'))

        if (gesture.el) {
            gesture.touchAction = gesture.el.style.touchAction
            gesture.el.style.touchAction = 'none'
        }

        if (gesture.area === 'grid') this.autoScroll(gesture)
    },

    // The place the pointer is over, now, is where the gesture stands
    track (gesture) {
        let cell = this.cellAt(gesture.area, gesture.point.x, gesture.point.y)

        if (cell) {
            gesture.current = cell
            this.show(gesture)
        }
    },

    // The time grid scrolls under a pointer at its top or bottom edge, a little more the nearer the edge, for as long as it stays there: the hours that
    // are not on screen could not be reached otherwise. The top is the bottom of the header that stays on top. A frame that has scrolled it works out
    // what the pointer is over again, as the pointer itself has not moved.
    autoScroll (gesture) {
        // the scroller is looked up once, and again only if it has left the page
        let grid = null

        let step = () => {
            if (gesture.abort.signal.aborted) return

            gesture.frame = requestAnimationFrame(step)

            if (!grid?.isConnected) grid = this.rootEl.querySelector('[data-atom-calendar-grid]')

            if (!grid || grid.scrollHeight <= grid.clientHeight) return

            let box = grid.getBoundingClientRect()
            let top = box.top + (grid.querySelector('[data-atom-calendar-grid-top]')?.offsetHeight ?? 0)
            let speed = scrollSpeed(gesture.point.y, top, box.bottom)

            if (!speed) return

            let before = grid.scrollTop

            grid.scrollTop += speed

            if (grid.scrollTop !== before) this.track(gesture)
        }

        gesture.frame = requestAnimationFrame(step)
    },

    // The range a gesture covers now, from where it was pressed to where it is: a selection's, or what the item it moves or resizes would cover then.
    // The pointer is a day (the month, the all-day row) or a slot (the time grid), so an item moves and grows by whole days or whole slots, and
    // one that did not start on a slot keeps the part of a slot it had.
    gestureRange (gesture) {
        let slotDuration = this.config.slotDuration ?? 30

        if (gesture.kind === 'select') return selectionRange(gesture.anchor, gesture.current, { slotDuration })

        let grid = gesture.area === 'grid'
        let shift = grid ? { minutes: minutesBetween(gesture.grab, gesture.current) } : { days: diffDays(gesture.grab, gesture.current) }

        if (gesture.kind === 'move') return movedRange(gesture.item, shift)

        let { item } = gesture

        return resizedRange(item, grid
            ? addMinutes(item.end ?? addMinutes(item.start, slotDuration), shift.minutes)
            : addDays(span(item).last, shift.days), { slotDuration })
    },

    // Draw what the gesture covers, when it is not what is up. An item moved or resized is drawn as its ghost, and the original stays, lifted.
    show (gesture) {
        let range = gesture.range = this.gestureRange(gesture)

        if (gesture.kind === 'select') return this.showSelect(range)

        let current = this.preview

        if (current?.kind === gesture.kind && String(current.id) === String(gesture.item.id) && current.start === range.start && current.end === range.end) return

        // a gesture made while a selection waits for the host is the one on screen: the placeholder of the selection is not taken down when its promise settles
        this.pendingToken = null
        this.preview = { kind: gesture.kind, id: gesture.item.id, ...range }
    },

    // Let go: where the pointer is now ends the gesture, a click or a drag alike
    gestureEnd (event) {
        let gesture = this.gesture

        if (!gesture || event.pointerId !== gesture.id) return

        if (gesture.active) {
            gesture.point = { x: event.clientX, y: event.clientY }
            gesture.current = this.cellAt(gesture.area, event.clientX, event.clientY) ?? gesture.current
        }

        this.finish(gesture)
    },

    // What the gesture comes to. A selection is made, a click as well as a drag (a tap on a day with items, where a month day is narrow, opens its list
    // instead); an item that was dragged is moved or resized, and one that was only pressed is left to be clicked.
    finish (gesture) {
        let { kind, area, active, anchor } = gesture
        let range = kind === 'select' || active ? this.gestureRange(gesture) : null

        // an item that has the focus is drawn again where it is put, as a new node, and the focus would fall to the page with the old one
        let focused = kind !== 'select' && active && Boolean(gesture.el?.isConnected) && document.activeElement === gesture.el

        this.endGesture(true, true)

        if (kind !== 'select') {
            if (!active) return null

            this.commitChange(gesture, range)

            if (focused) this.keepItemFocus(gesture.item.id)

            return
        }

        // In the narrow month a day shows no titles (see atom.css), so a tap on a day that has items opens its list, which does. A drag, and a
        // press that was held long enough to start one, still select, and so a busy day can still be created on.
        if (!active && area === 'month' && this.isNarrow() && dayEntries(this.items, anchor).length) {
            return this.openDay(anchor, this.rootEl.querySelector('[data-atom-calendar-day][data-date="' + anchor + '"]'))
        }

        this.commitSelect(range)
    },

    // Whether the month is laid out narrow. Not measured here: the stylesheet is asked, through a probe that its own container query shows (the
    // same rule that hides the titles), so the two cannot disagree on the width or on what it is the width of.
    isNarrow () {
        let probe = this.rootEl.querySelector('[data-atom-calendar-narrow]')

        return Boolean(probe) && getComputedStyle(probe).display !== 'none'
    },

    // Draw the range a selection covers, when it is not what is up
    showSelect (range) {
        if (this.preview?.kind === 'select' && this.preview.start === range.start && this.preview.end === range.end) return

        // a selection made while another waits for the host is the one on screen: the placeholder of the other is not taken down when its promise settles
        this.pendingToken = null
        this.preview = { kind: 'select', ...range }
    },

    // Stop following the pointer: the gesture is over, whatever it came to. The payload that came meanwhile is taken now, `apply` being false
    // only where the calendar is being torn down. `released` is whether the pointer is up: a gesture given up with the button still down has a release to come.
    endGesture (apply = true, released = false) {
        let gesture = this.gesture

        if (!gesture) return

        clearTimeout(gesture.timer)
        cancelAnimationFrame(gesture.frame)
        gesture.abort.abort()
        this.gesture = null

        this.rootEl?.querySelector('[data-atom-calendar-body]')?.removeAttribute('data-atom-calendar-gesture')

        if (gesture.el && gesture.touchAction !== undefined) gesture.el.style.touchAction = gesture.touchAction

        // A drag that is let go over the item it began on is followed by that item's click, which would open it or follow its link. It is the drag's, not the viewer's.
        if (gesture.active && gesture.kind !== 'select') this.eatClick(gesture, released)

        let held = this.payloadHeld

        this.payloadHeld = false

        if (held && apply) this.read()
    },

    // The click that follows a drag, on the item it began on: it is not let through. It follows the release, which has not come yet only when the drag
    // was given up (Escape) with the button still down. Any other end has had its release, or lost it where nothing heard it, and a click that
    // comes later is the viewer's: so it is a short window, not a wait for a release that may never come. The next press ends the wait too, since
    // the release it was for is not coming.
    eatClick (gesture, released) {
        let abort = new AbortController()
        let { signal } = abort
        let done = () => setTimeout(() => abort.abort(), CLICK_WINDOW)

        document.addEventListener('click', (click) => {
            if (gesture.el?.contains(click.target)) {
                click.stopPropagation()
                click.preventDefault()
            }

            abort.abort()
        }, { capture: true, signal })

        document.addEventListener('pointerdown', () => abort.abort(), { capture: true, signal })

        if (released) done()
        else {
            document.addEventListener('pointerup', done, { capture: true, signal, once: true })
            document.addEventListener('pointercancel', done, { capture: true, signal, once: true })
        }
    },

    // Escape, a release nobody heard, the browser taking a touch: the gesture is not made, and the host is told nothing. `released` is false only for
    // Escape, which leaves the button down.
    cancelGesture (released = true) {
        let gesture = this.gesture
        let showing = gesture?.active

        this.endGesture(true, released)

        if (showing && this.preview?.kind === gesture.kind) this.preview = null
    },

    // The payload came while a gesture is on and waits for it to end. It does not wait when it takes away the item that is being moved or resized:
    // there is nothing to move then, and the host is not told of a move of something it no longer has.
    checkGestureItem () {
        let item = this.gesture?.item

        if (!item) return

        let payload = this.readJson(this.rootEl.getAttribute('data-atom-calendar-payload'))

        if (payload && Array.isArray(payload.items) && !payload.items.some((candidate) => String(candidate.id) === String(item.id))) this.cancelGesture()
    },

    // The item that was moved or resized had the focus, and its chip is drawn again where it was put (a new node, in another week or another column),
    // and again when the host's payload comes and when a held move is settled: the focus is put back on the item's chip each time, by the item's id,
    // for as long as it is nobody's (see init(), where `overrides` is watched). It is let go of when it is taken elsewhere, by a key or by a press.
    keepItemFocus (id) {
        this.focusItem = String(id)
        this.$nextTick(() => this.focusChip())
    },

    focusChip () {
        if (this.focusItem === null) return

        let active = document.activeElement

        if (active && active !== document.body) {
            if (active.closest?.('[data-atom-calendar-chip]')?.getAttribute('data-atom-calendar-item') !== this.focusItem) this.focusItem = null

            return
        }

        // the first one that is drawn, in the grid and not in the day list
        let chip = [...this.rootEl.querySelectorAll('[data-atom-calendar-chip]')].find((el) => {
            return el.getAttribute('data-atom-calendar-item') === this.focusItem && el.getClientRects().length > 0 && !el.closest('[popover]')
        })

        chip?.focus({ preventScroll: true })
    },

    // Whether a range is where the item already is
    isUnchanged (gesture, range) {
        let { item } = gesture
        let end = item.end ?? (item.allDay ? item.start : (gesture.kind === 'resize' ? addMinutes(item.start, this.config.slotDuration ?? 30) : null))

        return range.start === item.start && range.end === end
    },

    // Tell the host that an item was moved or resized, with the range it covers now in the viewer's wall clock, as `calendar-select` does: `YYYY-MM-DDTHH:mm`
    // for a time (the end is the first minute after), `YYYY-MM-DD` for days (the end is the last day, included), either straight to Calendar::fromLocal().
    // A move says whether the item is all day too. The event can be cancelled, which puts the item back; and the host holds the item where it was put
    // with `waitUntil(promise)`, called while it handles the event: it stays there while the promise is pending, whatever payload comes, and goes back
    // if the promise is rejected. Resolved, it stays until the payload that has the host's answer in it comes (a host that answers without a render
    // has no such payload, and the item stays where it was put until the next one). Without a promise it stays until the next payload.
    commitChange (gesture, range) {
        let { item, kind } = gesture

        this.preview = null

        if (!range || this.isUnchanged(gesture, range)) return

        let promises = []
        let detail = { id: item.id, start: range.start, end: range.end }

        if (kind === 'move') detail.allDay = range.allDay

        let event = new CustomEvent(kind === 'move' ? 'calendar-item-move' : 'calendar-item-resize', {
            bubbles: true,
            cancelable: true,
            detail: { ...detail, waitUntil: (promise) => { promises.push(Promise.resolve(promise)) } },
        })

        this.rootEl.dispatchEvent(event)

        // the host says no: the item is where it was
        if (event.defaultPrevented) return

        let key = String(item.id)
        let token = Symbol()

        this.overrides = { ...this.overrides, [key]: { range: { start: range.start, end: range.end }, token, held: promises.length > 0, stale: false } }

        // what is given after the event has been handled is too late: the promises are read here, once
        if (!promises.length) return

        Promise.allSettled(promises).then((results) => {
            let entry = this.overrides[key]

            // a later move of the same item is the one on screen now
            if (entry?.token !== token) return

            let { [key]: gone, ...rest } = this.overrides

            this.overrides = results.some((result) => result.status === 'rejected') || entry.stale ? rest : { ...this.overrides, [key]: { ...entry, held: false } }
        })
    },

    // Tell the host. `calendar-select` bubbles from the calendar with { start, end, allDay, waitUntil } in the viewer's wall clock, as the payload's
    // items are: `YYYY-MM-DDTHH:mm` for a time (the end is the first minute after the range), `YYYY-MM-DD` for days (the end is the last day, included).
    // Either goes straight to the server's Calendar::fromLocal(). The host holds the selection with `waitUntil(promise)`, called while it handles the
    // event: a placeholder (a box with a spinner and a title) stays where the selection was until the promise has settled, resolved or rejected, and
    // goes then, the new item having come with the payload. A selection that nothing holds stays until the next payload, a new gesture, Escape or a
    // press elsewhere on the page (see lingerSelection), so a host that opens a form for it has the range in view while the form is up.
    commitSelect (range) {
        let promises = []

        this.pendingToken = null
        this.preview = { kind: 'select', ...range }

        this.rootEl.dispatchEvent(new CustomEvent('calendar-select', {
            bubbles: true,
            // the host holds the selection with waitUntil: there is nothing for a handler to cancel, and `.prevent` is a no-op
            cancelable: false,
            detail: { ...range, waitUntil: (promise) => { promises.push(Promise.resolve(promise)) } },
        }))

        // what is given after the event has been handled is too late: the promises are read here, once
        if (!promises.length) return this.lingerSelection()

        let token = this.pendingToken = Symbol()

        this.preview = { kind: 'pending', ...range, title: this.labels.ui?.creating ?? '' }

        Promise.allSettled(promises).then(() => {
            if (this.pendingToken !== token) return

            this.pendingToken = null
            this.preview = null
        })
    },

    // What a selection that nothing holds waits for, besides the next payload: a press on the page (a click on it ends the selection, as a click outside
    // a field does) and Escape. A press in a popover, a dialog or a form does not: that is the host's form for the selection, a non-modal one beside the
    // calendar, and pressing in it must leave the range in view. Unless the calendar is inside it, as it is in a modal: that press is on the page.
    lingerSelection () {
        this.lingerAbort?.abort()

        let abort = this.lingerAbort = new AbortController()
        let { signal } = abort

        document.addEventListener('pointerdown', (event) => {
            let keeper = event.target.closest?.(KEEPS_SELECTION)

            if (!keeper || keeper.contains(this.rootEl)) this.clearSelection()
        }, { signal, capture: true })
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') this.clearSelection() }, { signal, capture: true })
    },

    // Take down a selection that nothing holds; one being made, and one the host holds, are left
    clearSelection () {
        this.lingerAbort?.abort()
        this.lingerAbort = null

        if (!this.gesture && this.preview?.kind === 'select') this.preview = null
    },

    // The day or the slot under a point, read from where things are drawn now (a payload that arrives mid-gesture, or a scroll, moves them): a date
    // (`Y-m-d`) in the month view and the all-day row, the start of a slot (`Y-m-dTH:i`) in the time grid. A point outside is on the nearest.
    cellAt (area, x, y) {
        let all = (selector) => [...this.rootEl.querySelectorAll(selector)]

        if (area === 'month') {
            let cell = dayCell(all('[data-atom-calendar-week]').map((week) => week.getBoundingClientRect()), x, y)

            return cell ? (this.payload.window.days[cell.row * 7 + cell.col]?.date ?? null) : null
        }

        if (area === 'allday') {
            let days = all('[data-atom-calendar-allday-bg] > [data-date]')

            return days[indexAtX(days.map((day) => day.getBoundingClientRect()), x)]?.dataset.date ?? null
        }

        let columns = all('[data-atom-calendar-column]')
        let at = indexAtX(columns.map((column) => column.getBoundingClientRect()), x)

        if (at === -1) return null

        return momentAt(columns[at].dataset.date, minuteAt(y, columns[at].getBoundingClientRect(), this.config.slotDuration ?? 30))
    },
}
