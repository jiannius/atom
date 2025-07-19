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
            this.trigger?.addEventListener('click', () => this.show())
            this.popover?.addEventListener('toggle', (e) => {
                if (e.newState === 'open') this.$dispatch('open')
                else if (e.newState === 'closed') {
                    this.$dispatch('close')
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
            this.cleanup = atom.floatingui(this.trigger, this.popover, { placement: this.placement })
        },

        hide() {
            this.popover.hidePopover()
            this.$root.removeAttribute('data-open')
        },
    }
}