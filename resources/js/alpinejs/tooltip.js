export default (config) => {
    return {
        cleanup: null,
        hideTimer: null,
        placement: config.placement,
        interactive: config.interactive,

        get trigger () {
            return this.$root.querySelector(':scope > *:not([data-atom-tooltip-content])')
        },

        get popover () {
            return this.$root.querySelector('[data-atom-tooltip-content]')
        },

        init () {
            // Link the trigger to its content so assistive tech announces it.
            if (this.popover && this.trigger) {
                if (!this.popover.id) this.popover.id = this.$id('atom-tooltip')
                this.trigger.setAttribute('aria-describedby', this.popover.id)
            }

            // Show on hover and on keyboard focus (WCAG 1.4.13).
            this.$root.addEventListener('mouseenter', () => this.show())
            this.$root.addEventListener('mouseleave', () => this.scheduleHide())
            this.$root.addEventListener('focusin', () => this.show())
            this.$root.addEventListener('focusout', () => this.scheduleHide())

            // Interactive tooltips stay open while the pointer is over their
            // content, so users can select text or click links inside them.
            if (this.interactive) {
                this.popover?.addEventListener('mouseenter', () => this.cancelHide())
                this.popover?.addEventListener('mouseleave', () => this.scheduleHide())
            }

            // The content lives in the browser top layer, so it must never
            // outlive its context. Close it on SPA navigation (wire:navigate
            // swaps the whole page — a lingering tooltip would orphan) and on
            // Escape (WCAG 1.4.13 requires it be dismissible without moving the
            // pointer or focus). Do NOT hide on scroll: focusing or hovering a
            // trigger scrolls it into view, and that very scroll would dismiss
            // the tooltip the instant it appeared. The autoUpdate loop keeps it
            // glued to the anchor while shown instead, and is torn down below.
            this.onNavigate = () => this.hide()
            this.onKeydown = (e) => { if (e.key === 'Escape') this.hide() }
            document.addEventListener('livewire:navigating', this.onNavigate)
            document.addEventListener('keydown', this.onKeydown)
        },

        destroy () {
            // Alpine teardown (incl. morph removal): force-close, stop the
            // positioning loop, and drop the document listeners so nothing
            // lingers in the top layer.
            this.cancelHide()
            this.hide()
            document.removeEventListener('livewire:navigating', this.onNavigate)
            document.removeEventListener('keydown', this.onKeydown)
        },

        show () {
            if (!this.popover) return

            this.cancelHide()
            if (!this.popover.matches(':popover-open')) this.popover.showPopover()
            this.cleanup?.()
            this.cleanup = atom.floatingui(this.$root, this.popover, { placement: this.placement })
        },

        hide () {
            if (!this.popover) return
            if (this.popover.matches(':popover-open')) this.popover.hidePopover()
            this.cleanup?.()
            this.cleanup = null
        },

        scheduleHide () {
            // A grace period lets the pointer travel from the trigger into the
            // content of an interactive tooltip without it closing.
            this.cancelHide()
            this.hideTimer = setTimeout(() => this.hide(), this.interactive ? 120 : 0)
        },

        cancelHide () {
            if (this.hideTimer) {
                clearTimeout(this.hideTimer)
                this.hideTimer = null
            }
        },
    }
}
