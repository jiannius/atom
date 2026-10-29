import onMorphed from '../helpers/morphed'

// The members carry an `_atom` prefix: a tooltip is also the implicit wrapper of `<atom:button tooltip>`, so its x-data
// scope sits around the host's own expressions on that button, and a generic name (`show`, `hide`) would shadow the
// host's `show` from an outer scope. Only this file calls them.
export default (config) => {
    return {
        _atomCleanup: null,
        _atomHideTimer: null,
        _atomLinkedId: null,
        _atomPlacement: config.placement,
        _atomInteractive: config.interactive,

        get _atomTrigger () {
            return this.$root.querySelector(':scope > *:not([data-atom-tooltip-content])')
        },

        get _atomPopover () {
            return this.$root.querySelector('[data-atom-tooltip-content]')
        },

        /**
         * Link the trigger to its content so assistive tech announces it. The id is added to the trigger's
         * own description tokens, never written over them, and left out when the trigger's `aria-label` already
         * says what the content says (a name and a description with the same words are read twice).
         */
        _atomLink () {
            const popover = this._atomPopover
            const trigger = this._atomTrigger

            if (!popover || !trigger) return

            if (!popover.id) popover.id = this.$id('atom-tooltip')

            const words = (text) => (text || '').replace(/\s+/g, ' ').trim()
            const label = words(trigger.getAttribute('aria-label'))
            const named = label !== '' && label === words(popover.textContent)

            const tokens = (trigger.getAttribute('aria-describedby') || '').split(/\s+/)
                .filter((token) => token && token !== popover.id && token !== this._atomLinkedId)

            if (!named) tokens.push(popover.id)

            this._atomLinkedId = named ? null : popover.id

            if (tokens.length) trigger.setAttribute('aria-describedby', tokens.join(' '))
            else trigger.removeAttribute('aria-describedby')
        },

        /**
         * Hide an open tooltip the moment its trigger reports an open popup (a dropdown or a menu). Rebound after
         * a morph, which can swap the trigger node.
         */
        _atomWatchTrigger () {
            this.$root._atomTooltipObserver?.disconnect()
            this.$root._atomTooltipObserver = null

            const trigger = this._atomTrigger

            if (!trigger) return

            const observer = new MutationObserver(() => {
                if (trigger.getAttribute('aria-expanded') === 'true') this._atomHide()
            })

            observer.observe(trigger, { attributes: true, attributeFilter: ['aria-expanded'] })
            this.$root._atomTooltipObserver = observer
        },

        init () {
            this._atomLink()
            this._atomWatchTrigger()

            // The link lives on the trigger, a node of the host's, which a morph strips
            // of any attribute the server did not render. Write it again afterwards.
            // Kept on the element, so a re-run init() drops the registration it made before.
            this.$root._atomTooltipStopMorph?.()
            this.$root._atomTooltipStopMorph = onMorphed((el) => {
                if (!el || el.contains(this.$root)) {
                    this._atomLink()
                    this._atomWatchTrigger()
                }
            })

            // Show on hover and on keyboard focus (WCAG 1.4.13).
            this.$root.addEventListener('mouseenter', () => this._atomShow())
            this.$root.addEventListener('mouseleave', () => this._atomScheduleHide())
            this.$root.addEventListener('focusin', () => this._atomShow())
            this.$root.addEventListener('focusout', () => this._atomScheduleHide())

            // Interactive tooltips stay open while the pointer is over their
            // content, so users can select text or click links inside them.
            if (this._atomInteractive) {
                this._atomPopover?.addEventListener('mouseenter', () => this._atomCancelHide())
                this._atomPopover?.addEventListener('mouseleave', () => this._atomScheduleHide())
            }

            // The content lives in the browser top layer, so it must never
            // outlive its context. Close it on SPA navigation (wire:navigate
            // swaps the whole page — a lingering tooltip would orphan) and on
            // Escape (WCAG 1.4.13 requires it be dismissible without moving the
            // pointer or focus). Do NOT hide on scroll: focusing or hovering a
            // trigger scrolls it into view, and that very scroll would dismiss
            // the tooltip the instant it appeared. The autoUpdate loop keeps it
            // glued to the anchor while shown instead, and is torn down below.
            this._atomOnNavigate = () => this._atomHide()
            this._atomOnKeydown = (e) => { if (e.key === 'Escape') this._atomHide() }
            document.addEventListener('livewire:navigating', this._atomOnNavigate)
            document.addEventListener('keydown', this._atomOnKeydown)
        },

        destroy () {
            this.$root._atomTooltipStopMorph?.()
            this.$root._atomTooltipObserver?.disconnect()
            // Alpine teardown (incl. morph removal): force-close, stop the
            // positioning loop, and drop the document listeners so nothing
            // lingers in the top layer.
            this._atomCancelHide()
            this._atomHide()
            document.removeEventListener('livewire:navigating', this._atomOnNavigate)
            document.removeEventListener('keydown', this._atomOnKeydown)
        },

        _atomShow () {
            if (!this._atomPopover) return

            // Not over the popup its own trigger has open. When Escape closes a menu and the focus returns to the
            // trigger, `aria-expanded` is cleared after the `focusin`, so the tooltip does not pop up over it.
            if (this._atomTrigger?.getAttribute('aria-expanded') === 'true') return

            this._atomCancelHide()
            if (!this._atomPopover.matches(':popover-open')) this._atomPopover.showPopover()
            this._atomCleanup?.()
            this._atomCleanup = atom.floatingui(this.$root, this._atomPopover, { placement: this._atomPlacement })
        },

        _atomHide () {
            if (!this._atomPopover) return
            if (this._atomPopover.matches(':popover-open')) this._atomPopover.hidePopover()
            this._atomCleanup?.()
            this._atomCleanup = null
        },

        _atomScheduleHide () {
            // A grace period lets the pointer travel from the trigger into the
            // content of an interactive tooltip without it closing.
            this._atomCancelHide()
            this._atomHideTimer = setTimeout(() => this._atomHide(), this._atomInteractive ? 120 : 0)
        },

        _atomCancelHide () {
            if (this._atomHideTimer) {
                clearTimeout(this._atomHideTimer)
                this._atomHideTimer = null
            }
        },
    }
}
