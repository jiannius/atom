export default (config) => {
    return {
        cleanup: null,
        placement: config.placement,
        interactive: config.interactive,

        get popover () {
            return this.$root.querySelector('[data-atom-tooltip-content]')
        },

        init () {
            this.$root.addEventListener('mouseenter', () => this.show())
            this.$root.addEventListener('mouseleave', () => this.show(false))
        },

        show (bool = true) {
            if (!this.popover) return

            if (bool) {
                this.popover.showPopover()
                this.cleanup = atom.floatingui(this.$root, this.popover, { placement: this.placement })
            }
            else {
                this.popover.hidePopover()
                this.cleanup?.()
            }
        },
    }
}