import floatingui from '../helpers/floatingui'
import { closeOnNavigate, listen, unlisten } from '../helpers/scope'

export default (config = {}) => ({
    cleanup: null,
    locked: config.locked,
    x: 0,
    y: 0,

    get trigger () {
        return this.$root.querySelector('[data-atom-context-menu-trigger]')
    },

    get popover () {
        return this.$root.querySelector('[data-atom-menu]')
    },

    init () {
        // Everything is bound on $root, not on the trigger and popover nodes: a Livewire morph can replace those nodes, which orphans a
        // listener bound to the old one (as for the dropdown). `toggle` does not bubble, so it is caught on the way down.
        const signal = listen(this.$root, '_atomContextMenuListeners')

        this.$root.addEventListener('contextmenu', (e) => {
            if (!this.trigger?.contains(e.target)) return

            e.preventDefault()
            this.x = e.clientX
            this.y = e.clientY
            this.show()
        }, { signal })

        this.$root.addEventListener('toggle', (e) => {
            if (e.target !== this.popover) return

            if (e.newState === 'open') {
                this.$dispatch('open')
            }
            else {
                // Single source of truth for "closed" — a native dismiss
                // (ESC / outside-click) cleans up here too, not just hide().
                this.$dispatch('close')
                this.$root.removeAttribute('data-open')
                this.cleanup?.()
            }
        }, { capture: true, signal })

        if (!this.locked) {
            this.$root.addEventListener('click', (e) => {
                // the path, not `contains(e.target)`: an item removed by its own click (an x-if or x-for re-render in a microtask) is out of
                // the popover by the time this runs
                if (e.composedPath().includes(this.popover)) this.hide()
            }, { signal })
        }

        // Close on SPA nav so the menu can't outlive a wire:navigate page swap.
        closeOnNavigate(() => this.hide(), signal)
    },

    destroy () {
        this.hide()
        this.cleanup?.()
        unlisten(this.$root, '_atomContextMenuListeners')
    },

    show () {
        const anchor = {
            getBoundingClientRect: () => ({
                x: this.x,
                y: this.y,
                top: this.y,
                left: this.x,
                right: this.x,
                bottom: this.y,
                width: 0,
                height: 0,
            }),
        }

        if (this.popover?.matches(':popover-open')) {
            // already open (second right-click elsewhere) — reposition only;
            // showPopover() throws on an open popover.
            this.cleanup?.()
        }
        else {
            this.popover.showPopover()
            this.$root.setAttribute('data-open', '')
        }

        this.cleanup = floatingui(anchor, this.popover, {
            placement: 'bottom-start',
            // The panel's corner sits a little over the pointer, not at it: the menu opens on the press
            // and its popover closes on a release outside it, so a release over the rounded corner (outside
            // the painted shape) shut the menu at once. Six px puts the pointer inside a 10px radius.
            // `alignmentAxis`, not `crossAxis`: it is mirrored in RTL (and for an end alignment), so the
            // panel still overhangs the pointer there. A pointer within 5px of a viewport edge can still
            // end up outside the panel once shift() pads it in.
            offset: { mainAxis: -6, alignmentAxis: -6 },
            autoUpdate: false,
        })
    },

    hide () {
        if (this.popover?.matches(':popover-open')) {
            this.popover.hidePopover()
        }
    },
})
