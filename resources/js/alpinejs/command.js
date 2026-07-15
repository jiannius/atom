export default (config) => {
    return {
        open: false,
        text: '',
        activeIndex: -1,

        init () {
            // Re-filter (and re-pick the active item) whenever the query changes.
            this.$watch('text', () => this.filter())
        },

        // Bound to the keyboard shortcut in the blade.
        toggle () {
            this.open ? this.closeCommand() : this.showCommand()
        },

        showCommand (e = null) {
            if (e?.detail?.name && e.detail.name !== config.name) return
            if (this.$root.open) return // showModal() throws on an already-open dialog

            this.$root.showModal()
            this.$root.setAttribute('data-open', '')
            this.open = true
            this.text = ''

            this.$nextTick(() => {
                this.filter()
                this.$root.querySelector('[data-atom-command-search]')?.focus()
            })
        },

        closeCommand (e = null) {
            if (e?.detail?.name && e.detail.name !== config.name) return

            this.$root.close()
            this.$root.removeAttribute('data-open')
            this.open = false
            this.text = ''
        },

        backdropClick (e) {
            // Only a click on the dialog element itself is the backdrop; clicks
            // on inner content have a descendant target.
            if (e.target === this.$root) this.closeCommand()
        },

        items () {
            return Array.from(this.$root.querySelectorAll('[data-atom-command-item]'))
        },

        visibleItems () {
            return this.items().filter(el => !el.hidden)
        },

        // Show/hide items by label match, hide emptied groups, toggle the empty
        // state, and re-pick the active item. Uses the native `hidden` attribute
        // (UA display:none) so hiding works without Tailwind loaded.
        filter () {
            let text = (this.text || '').toLowerCase()

            this.items().forEach(el => {
                let label = (el.getAttribute('data-label') || '').toLowerCase()
                el.hidden = !!text && !label.includes(text)
            })

            this.$root.querySelectorAll('[data-atom-command-group]').forEach(group => {
                group.hidden = !group.querySelector('[data-atom-command-item]:not([hidden])')
            })

            let empty = this.$root.querySelector('[data-atom-command-empty]')
            if (empty) empty.hidden = this.visibleItems().length > 0

            this.resetActive()
        },

        // Virtual focus: mark the active item without moving DOM focus off the
        // search input, so the user can keep typing (mirrors select.js).
        setActive (index) {
            this.items().forEach(el => el.removeAttribute('data-active'))

            this.activeIndex = index
            let el = this.visibleItems()[index]
            let search = this.$root.querySelector('[data-atom-command-search]')

            if (!el) {
                search?.removeAttribute('aria-activedescendant')
                return
            }

            if (!el.id) el.id = `${config.name || 'command'}-item-${this.items().indexOf(el)}`
            el.setAttribute('data-active', '')
            search?.setAttribute('aria-activedescendant', el.id)
            el.scrollIntoView({ block: 'nearest' })
        },

        resetActive () {
            this.setActive(this.visibleItems().length ? 0 : -1)
        },

        move (dir) {
            let els = this.visibleItems()
            if (!els.length) return

            let next = this.activeIndex < 0
                ? (dir > 0 ? 0 : els.length - 1)
                : (this.activeIndex + dir + els.length) % els.length

            this.setActive(next)
        },

        keyDown () { this.move(1) },
        keyUp () { this.move(-1) },
        home () { this.setActive(0) },
        end () { this.setActive(this.visibleItems().length - 1) },

        enterKey () {
            let el = this.visibleItems()[this.activeIndex]
            if (el) el.click() // anchor navigates; button fires its wire:click / x-on:click
        },
    }
}
