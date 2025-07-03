export default (config) => {
    return {
        cleanup: null,
        locked: config.locked,
        placement: config.placement,
        
        get trigger () {
            return this.$el.querySelector('[data-atom-dropdown-trigger]')
                || this.$el.querySelector('button')
        },

        get popover () {
            return this.$el.querySelector('[data-atom-dropdown-popover]')
                || this.$el.querySelector('[data-atom-menu]')
        },

        init () {
            this.trigger?.addEventListener('click', () => this.open())

            // Close on outside click
            if (!this.locked) {
                document.addEventListener('click', (event) => {
                    if (!this.trigger.contains(event.target)) {
                        this.open(false)
                    }
                })
            }

            if (!this.popover.hasAttribute('popover')) {
                this.popover.setAttribute('popover', '')

                // we added a hidden class to the popover to prevent flickering before the alpine is loaded
                // once initialized we remove the hidden class
                // popover will still be hidden because of the html [popover] attribute
                this.$root.classList.remove('[:where(&_[data-atom-menu])]:hidden')
            }
        },

        open (bool = true) {
            if (bool) {
                this.popover.showPopover()
                this.$root.setAttribute('data-open', '')
                this.cleanup = atom.floatingui(this.trigger, this.popover, { placement: this.placement })
            }
            else {
                this.popover.hidePopover()
                this.$root.removeAttribute('data-open')
                this.cleanup?.()
            }
        },
    }
}