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

            this.trigger?.addEventListener('click', () => this.show())
            this.popover?.addEventListener('toggle', (e) => {
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
            })

            if (!this.locked) {
                this.popover?.addEventListener('click', () => this.hide())
            }
        },

        show() {
            this.popover.showPopover()
            this.$root.setAttribute('data-open', '')
            this.trigger?.setAttribute('aria-expanded', 'true')
            this.cleanup = atom.floatingui(this.trigger, this.popover, { placement: this.placement })
        },

        hide() {
            this.popover.hidePopover()
        },
    }
}
