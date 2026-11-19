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
        this.trigger?.addEventListener('contextmenu', (e) => {
            e.preventDefault()
            this.x = e.clientX
            this.y = e.clientY
            this.show()
        })

        this.popover?.addEventListener('toggle', (e) => {
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
        })

        if (!this.locked) {
            this.popover?.addEventListener('click', () => this.hide())
        }

        // Close on SPA nav so the menu can't outlive a wire:navigate page swap.
        this.onNavigate = () => this.hide()
        document.addEventListener('livewire:navigating', this.onNavigate)
    },

    destroy () {
        this.hide()
        this.cleanup?.()
        document.removeEventListener('livewire:navigating', this.onNavigate)
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

        this.cleanup = atom.floatingui(anchor, this.popover, {
            placement: 'bottom-start',
            // The panel's corner sits a little over the pointer, not at it: the menu opens on the press
            // and its popover closes on a release outside it, so a release over the rounded corner (outside
            // the painted shape) shut the menu at once. Six px puts the pointer inside a 10px radius.
            // `alignmentAxis`, not `crossAxis`: it flips with an end-aligned placement, so a menu that
            // flipped to the pointer's left still holds the pointer.
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
