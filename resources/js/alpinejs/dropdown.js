import onMorphed from '../helpers/morphed'

export default (config) => {
    return {
        cleanup: null,
        openBeforePress: null,
        locked: config.locked,
        placement: config.placement,

        // helpers/confirm-gate.js reads this getter (`Alpine.$data(root).trigger`, again once its dialog is gone) to put the focus back on a menu's trigger
        // after Cancel or Escape: keep it a getter that finds the trigger in the DOM each time it is asked, never a field set at init.
        get trigger() {
            // Every lookup must skip the popover. With a trigger that is not a
            // <button> (a link), the first menu item would be picked, and a click
            // on it would count as a trigger click and never close the menu. The
            // same goes for a data-atom-dropdown-trigger inside the menu: a
            // date-picker or colour input in a "More filters" menu marks its own
            // trigger, which is not this dropdown's.
            let popover = this.popover
            let outside = (el) => !popover?.contains(el)

            return [...this.$root.querySelectorAll('[data-atom-dropdown-trigger]')].find(outside)
                || [...this.$root.querySelectorAll('button')].find(outside)
                || [...this.$root.children].find(outside)
        },

        // A control that holds this dropdown marks itself `data-atom-disabled`, and a disabled one opens nothing, whatever trigger the host slotted.
        // Read from the DOM when asked (never x-data): the server rewrites the marker on a morph.
        get disabled() {
            return !!this.$root.closest('[data-atom-disabled]')
        },

        get popover() {
            return this.$root.querySelector(':scope >[popover]')
                || this.$root.querySelector('[data-atom-dropdown-popover]')
                || this.$root.querySelector('[data-atom-menu]')
        },

        init() {
            this.syncState()

            // Everything is bound on $root rather than on the trigger / popover
            // nodes themselves: a Livewire morph replaces those nodes whenever
            // their markup changes (the select's per-render uid is enough to do
            // it), which orphans a listener bound to the old node. The popover
            // then still opened but `open` was never dispatched again, so a
            // listbox could never fetch its options after any re-render.
            // Re-binding is idempotent — the controller is kept on the element,
            // not on `this`, so a morph that re-evaluates x-data (a new data
            // object) still drops the previous instance's listeners.
            this.$root._atomDropdownListeners?.abort()
            let { signal } = this.$root._atomDropdownListeners = new AbortController()

            // A popover=auto closes itself on pointerdown (light dismiss), before
            // the click lands, so by then the menu always reads as closed and a
            // second click on the trigger would reopen what it just closed. Ask
            // in the capture phase, ahead of the native dismiss.
            this.$root.addEventListener('pointerdown', (e) => {
                this.openBeforePress = this.trigger?.contains(e.target)
                    ? !!this.popover?.matches(':popover-open')
                    : null

                // a press on the menu's padding takes the focus off its item: a morph must not pull it back
                if (this.popover?.contains(e.target) && !e.target.closest('[data-atom-menu-item]')) this.$root._atomMenuHeld = null
            }, { capture: true, signal })

            this.$root.addEventListener('click', (e) => {
                if (this.trigger?.contains(e.target)) {
                    // A mouse or touch click has a pointerdown (detail > 0) and
                    // the answer recorded there. A keyboard click (Enter, Space)
                    // or a programmatic one has neither, so the live state is
                    // still true. A text field as the trigger keeps its menu
                    // open: a click there places the caret, not toggles.
                    let wasOpen = e.detail > 0 && this.openBeforePress !== null
                        ? this.openBeforePress
                        : !!this.popover?.matches(':popover-open')

                    this.openBeforePress = null

                    // Only a field that is part of the trigger: an editable region the whole dropdown
                    // sits inside would match from an ancestor and keep every menu open.
                    let field = e.target.closest?.('input, textarea, select, [contenteditable]')
                    let inTrigger = !!field && this.trigger.contains(field)

                    if (wasOpen && !inTrigger) this.hide()
                    else {
                        let opened = !this.popover?.matches(':popover-open')

                        this.show()

                        // A keyboard press on the trigger (Enter, Space) has no pointer behind it (detail 0): the menu opens on its first item.
                        // Not when the trigger is a field the person is typing in.
                        if (opened && e.detail === 0 && !inTrigger && this._atomFocusItem('first')) this.$root._atomOpenedByKey = true
                    }
                }
                // Close on a click inside the menu, unless `locked`. Bubble
                // phase, so `x-on:click.stop` on a child of the popover still
                // keeps the menu open (form controls rely on that).
                else if (!this.locked && this.popover?.contains(e.target)) this.hide()
            }, { signal })

            // A held Enter opened the menu and its repeats now land on the first item, which would run it: swallow them until the key is up. The first
            // press (not a repeat) or any key up ends it.
            this.$root.addEventListener('keydown', (e) => {
                if (!e.repeat) this.$root._atomOpenedByKey = false
                else if (this.$root._atomOpenedByKey && (e.key === 'Enter' || e.key === ' ') && this.popover?.contains(e.target)) {
                    e.preventDefault()
                    e.stopPropagation()
                }
            }, { capture: true, signal })
            this.$root.addEventListener('keyup', () => { this.$root._atomOpenedByKey = false }, { capture: true, signal })

            // Up, Down, Home and End in a menu (see _atomMenuKey()). On the root, like the rest, so a morph that replaces the popover or the trigger keeps it.
            this.$root.addEventListener('keydown', (e) => this._atomMenuKey(e), { signal })

            // The item that holds the focus, for a morph to give it back (see _atomRestoreFocus()). On the root, not on the popover: a morph can replace it.
            this.$root.addEventListener('focusin', (e) => {
                let item = e.target instanceof Element && this.popover?.contains(e.target) ? e.target.closest('[data-atom-menu-item]') : null

                this.$root._atomMenuHeld = item ? { item, index: this._atomMenuItems(this.popover).indexOf(item) } : null
            }, { signal })

            // The focus went to something else on purpose (a Tab to the next control, a click on a field). A blur with nowhere to go is not that: it is
            // what a morph that moves the focused node does.
            this.$root.addEventListener('focusout', (e) => {
                if (e.relatedTarget && !this.popover?.contains(e.relatedTarget)) this.$root._atomMenuHeld = null
            }, { signal })

            // Whether the focus is in the menu as it closes, to give it back to the trigger once it is closed (see the toggle handler below).
            this.$root.addEventListener('beforetoggle', (e) => {
                if (e.target === this.popover && e.newState === 'closed') {
                    this.$root._atomMenuHadFocus = !!this.$root._atomMenuHeld && this.popover.contains(document.activeElement)
                }
            }, { capture: true, signal })

            // `toggle` doesn't bubble, so it is caught on the way down instead —
            // the popover is always a descendant of $root.
            this.$root.addEventListener('toggle', (e) => {
                if (e.target !== this.popover) return

                if (e.newState === 'open') {
                    this.$dispatch('open')
                }
                else if (e.newState === 'closed') {
                    // Single source of truth for "closed" so a native dismiss
                    // (ESC / outside-click) cleans up state too, not just hide().
                    this.$dispatch('close')
                    this.$root._atomMenuHeld = null
                    this.$root.removeAttribute('data-open')
                    this.trigger?.setAttribute('aria-expanded', 'false')
                    this.cleanup?.()

                    // The popover gives the focus back to what had it before it opened. A menu opened with the keyboard moves the focus onto an item,
                    // and in WebKit that return ends on <body> (the next Escape then reaches the page, not the layer around the menu), after this event
                    // (the item is still the active element here): so, in the next task, put it on the trigger, but only when nothing else has it (a
                    // dialog an item opened, the field a click went to).
                    if (this.$root._atomMenuHadFocus) {
                        setTimeout(() => {
                            let active = document.activeElement
                            let lost = !active || active === document.body || active === document.documentElement || !!this.popover?.contains(active)

                            if (lost && !this.popover?.matches(':popover-open')) this.trigger?.focus({ preventScroll: true })
                        })
                    }

                    this.$root._atomMenuHadFocus = false
                }
            }, { capture: true, signal })

            // The menu lives in the browser top layer; close it on SPA
            // navigation so it can't outlive a wire:navigate page swap (the
            // removal of an open popover doesn't reliably fire `toggle`, so the
            // close handler above wouldn't run cleanup otherwise).
            document.addEventListener('livewire:navigating', () => this.hide(), { signal })

            // A Livewire morph strips what the server did not render: `data-open` on the
            // root (the panel turns opacity-0 while the popover is still in the top layer,
            // positioned and clickable) and the aria on the trigger. The popover stays
            // open, so write them again from it. Not `wire:ignore.self` on the root: that
            // would also freeze a server-driven class or attribute on it. Dropped by
            // destroy(); kept on the element like the listeners, so a re-run init() drops
            // the previous registration.
            this.$root._atomDropdownStopMorph?.()
            this.$root._atomDropdownStopMorph = onMorphed((el) => {
                if (!el || el.contains(this.$root)) {
                    // an open popup closes when a morph disables the control around it
                    if (this.disabled) this.hide()

                    this.syncState()
                    this._atomRestoreFocus()
                }
            })
        },

        // The methods below carry an `_atom` prefix, as the tooltip's do: an Alpine expression inside the dropdown (`x-on:keydown="menuKey($event)"` on the
        // column picker's menu and the breadcrumbs menu's) reads this object first, so a plain `menuKey` here would be called instead of their own.

        /**
         * The popover when it is a menu (`<atom:menu popover>`: `role="menu"`), else null. A select's list is the same component with `role="listbox"`
         * and its own keys, and a date picker's panel holds no items: neither is walked.
         */
        _atomMenu() {
            let popover = this.popover

            return popover?.matches('[data-atom-menu][role="menu"]') ? popover : null
        },

        /**
         * The menu items a key can reach: without a disabled one, one marked `aria-disabled` or one that is not drawn (hidden by
         * `x-show`, or inside a popover that is closed). A separator is not an item.
         */
        _atomMenuItems(menu) {
            return [...menu.querySelectorAll('[data-atom-menu-item]')].filter((item) => !item.matches(':disabled, [aria-disabled="true"]')
                && (item.checkVisibility?.() ?? true))
        },

        /**
         * Focus the first or last reachable item of the open menu (`'first'`, `'last'`), or the one a step away (`1`, `-1`) from the focused
         * item, wrapping at the ends. Returns whether an item took the focus.
         */
        _atomFocusItem(to) {
            let menu = this._atomMenu()

            if (!menu) return false

            let items = this._atomMenuItems(menu)

            if (!items.length) return false

            let current = items.indexOf(document.activeElement?.closest('[data-atom-menu-item]'))
            let next = to === 'first' ? 0
                : to === 'last' ? items.length - 1
                // nothing focused yet: Down lands on the first, Up on the last
                : current < 0 ? (to > 0 ? 0 : items.length - 1)
                : (current + to + items.length) % items.length

            items[next].focus({ preventScroll: to === 'first' })

            return true
        },

        /**
         * Arrow Down and Up, Home and End walk the items of a menu (`<atom:menu popover>`), wrapping at the ends, and on the trigger Down and Up
         * open the menu on its first or last item. Tab and Escape stay the browser's: Escape closes the popover and gives the focus back to the
         * trigger. Only an item (or the menu itself) hears the keys, never a field or another control inside the menu (a search box, a date picker),
         * and a key a menu's own handler already took (the column picker, the breadcrumbs menu) is left alone.
         */
        _atomMenuKey(e) {
            if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return

            let to = { ArrowDown: 1, ArrowUp: -1, Home: 'first', End: 'last' }[e.key]
            let menu = this._atomMenu()

            if (!to || !menu || this.disabled || !(e.target instanceof Element)) return

            if (menu.contains(e.target)) {
                // not a field or another control in the menu
                if (e.target !== menu && !e.target.closest('[data-atom-menu-item]')) return

                if (this._atomFocusItem(to)) e.preventDefault()

                return
            }

            // the trigger, unless it is a field (Home and End are its own), and only a menu that has items to land on
            let field = e.target.closest('input, textarea, select, [contenteditable]')

            if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && this.trigger?.contains(e.target) && !(field && this.trigger.contains(field)) && menu.querySelector('[data-atom-menu-item]')) {
                e.preventDefault()
                this.show()
                this._atomFocusItem(to === 1 ? 'first' : 'last')
            }
        },

        /**
         * A morph that moves or replaces the focused item of an open menu leaves the focus on the page. Put it back on that item, or on the item
         * at its place when it was replaced. Only when nothing else has the focus: an item the person moved on from is not pulled back.
         */
        _atomRestoreFocus() {
            let held = this.$root._atomMenuHeld
            let active = document.activeElement

            if (!held || !this.popover?.matches(':popover-open') || (active && active !== document.body && active !== document.documentElement)) return

            let items = this._atomMenuItems(this.popover)
            let item = held.item.isConnected && items.includes(held.item) ? held.item : items[Math.min(held.index, items.length - 1)]

            item?.focus({ preventScroll: true })
        },

        /**
         * Write the state JS owns on the root and the trigger from whether the popover is open.
         */
        syncState() {
            let open = !!this.popover?.matches(':popover-open')

            this.$root.toggleAttribute('data-open', open)

            let trigger = this.trigger
            let expanded = open ? 'true' : 'false'

            // Respect a popup role the trigger already declares (e.g. a select
            // sets aria-haspopup=listbox); only default to menu otherwise.
            if (trigger && !trigger.hasAttribute('aria-haspopup')) {
                trigger.setAttribute('aria-haspopup', 'menu')
            }
            // Only when it differs: a write that changes nothing still queues a mutation record
            // for every observer on the page, and this runs after every morph.
            if (trigger && trigger.getAttribute('aria-expanded') !== expanded) {
                trigger.setAttribute('aria-expanded', expanded)
            }
        },

        destroy() {
            // Alpine teardown (incl. morph removal): force-close + drop the
            // positioning loop and every listener bound in init().
            this.hide()
            this.cleanup?.()
            this.$root._atomDropdownStopMorph?.()
            this.$root._atomDropdownListeners?.abort()
        },

        show() {
            if (this.disabled || this.popover?.matches(':popover-open')) return

            this.popover.showPopover()
            this.$root.setAttribute('data-open', '')
            this.trigger?.setAttribute('aria-expanded', 'true')
            this.cleanup = atom.floatingui(this.trigger, this.popover, { placement: this.placement })
        },

        hide() {
            if (this.popover?.matches(':popover-open')) this.popover.hidePopover()
        },
    }
}
