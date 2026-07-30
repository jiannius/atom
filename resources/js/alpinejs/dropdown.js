export default (config) => {
    return {
        cleanup: null,
        locked: config.locked,
        placement: config.placement,

        get trigger() {
            return this.$root.querySelector('[data-atom-dropdown-trigger]')
                || this.$root.querySelector('button')
                || this.$root.querySelector(':scope > *')
        },

        get popover() {
            return this.$root.querySelector(':scope >[popover]')
                || this.$root.querySelector('[data-atom-dropdown-popover]')
                || this.$root.querySelector('[data-atom-menu]')
        },

        init() {
            // Respect a popup role the trigger already declares (e.g. a select
            // sets aria-haspopup=listbox); only default to menu otherwise.
            if (!this.trigger?.hasAttribute('aria-haspopup')) {
                this.trigger?.setAttribute('aria-haspopup', 'menu')
            }
            this.trigger?.setAttribute('aria-expanded', 'false')

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

            this.$root.addEventListener('click', (e) => {
                if (this.trigger?.contains(e.target)) this.show()
                // Close on a click inside the menu, unless `locked`. Bubble
                // phase, so `x-on:click.stop` on a child of the popover still
                // keeps the menu open (form controls rely on that).
                else if (!this.locked && this.popover?.contains(e.target)) this.hide()
            }, { signal })

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
                    this.$root.removeAttribute('data-open')
                    this.trigger?.setAttribute('aria-expanded', 'false')
                    this.cleanup?.()
                }
            }, { capture: true, signal })

            // The menu lives in the browser top layer; close it on SPA
            // navigation so it can't outlive a wire:navigate page swap (the
            // removal of an open popover doesn't reliably fire `toggle`, so the
            // close handler above wouldn't run cleanup otherwise).
            document.addEventListener('livewire:navigating', () => this.hide(), { signal })
        },

        destroy() {
            // Alpine teardown (incl. morph removal): force-close + drop the
            // positioning loop and every listener bound in init().
            this.hide()
            this.cleanup?.()
            this.$root._atomDropdownListeners?.abort()
        },

        show() {
            if (this.popover?.matches(':popover-open')) return

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
