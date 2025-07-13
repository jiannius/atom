export default (config) => {
    return {
        value: config.multiple ? [] : null,
        options: [],
        callback: typeof config.options === 'string' ? config.options : null,
        multiple: config.multiple,
        text: null,
        loading: false,
        visible: false,

        get isEmpty () {
            return !this.selected || (Array.isArray(this.selected) && !this.selected.length)
        },

        get selected () {
            if (Array.isArray(this.value)) return this.value.map(val => this.options.find(opt => opt.value == val))
            else return this.options.find(opt => opt.value == this.value)
        },

        get searchable () {
            return config.searchable && (
                this.text
                || (Array.isArray(this.options) && this.options.length > 0)
                || this.callback
            )
        },

        init () {
            this.wiresync()
            this.$wire.$watch(config.wiremodel, () => this.wiresync())
            this.$watch('visible', () => this.fetch())
            this.$watch('text', () => this.fetch())
        },

        wiresync () {
            this.value = this.$wire.get(config.wiremodel)
        },

        show () {
            this.$root.querySelector('[data-atom-dropdown] > button').click()
        },

        clear () {
            this.value = this.multiple ? [] : ''
            this.$dispatch('input', this.value)
        },

        fetch () {
            if (this.callback) {
                this.loading = true
                atom.action('get-options', { name: this.callback, filters: {
                    search: this.text,
                    value: this.value,
                    ...config.filters,
                }}).then(res => this.options = [...res]).then(() => this.loading = false).then(() => this.setWidth())
            }
            else {
                this.options = this.text
                    ? config.options.filter(opt => (opt.label.toLowerCase().includes(this.text.toLowerCase())))
                    : [...(config.options || [])]

                this.setWidth()
            }
        },

        setWidth () {
            let container = this.$root
            let menu = this.$root.querySelector('[data-atom-menu]')
            if (container.clientWidth > menu.clientWidth) menu.style.width = container.clientWidth+'px'
        },

        select (opt) {
            if (this.multiple) {
                if (this.isSelected(opt)) return this.deselect(opt)
                else this.value = [...(this.value || []), ...[opt.value]]
            }
            else {
                this.value = opt.value
            }

            this.text = null
            this.loading = false
            this.$dispatch('input', this.value)
        },

        deselect (opt) {
            let values = [...this.value]
            let index = values.findIndex(val => (val == opt.value))

            if (index > -1) {
                values.splice(index, 1)
                this.value = [...values]
                this.text = null
                this.loading = false
                this.$dispatch('input', this.value)
            }
        },

        moveTo (el, focus = true) {
            if (focus) {
                let focused = this.getFocusedElementIndex()
                if (focused > -1) this.moveTo(this.getOptionsElements(focused), false)
                el.setAttribute('data-option-focus', '')
                el.focus()
            }
            else {
                el.removeAttribute('data-option-focus')
            }
        },

        getOptionHtml (option) {
            if (option.html) return option.html

            let color = option.color
                ? '<div style="background-color: '+option.color+'" class="shrink-0 w-3 h-3 rounded-full bg-zinc-100 flex items-center justify-center"></div>'
                : ''

            return '<div class="flex items-center gap-2">'+color+'<span>'+option.label+'</span></div>'
        },

        getOptionsElements (index = -1) {
            let els = Array.from(this.$root.querySelectorAll('[data-atom-option]'))
            return index > -1 ? els[index] : els
        },

        getFocusedElementIndex () {
            return this.getOptionsElements().findIndex(node => (node.hasAttribute('data-option-focus')))
        },

        keyUp () {
            if (!this.visible) this.show()
            else {
                let els = this.getOptionsElements()
                let active = this.getFocusedElementIndex()
                let prev = active <= 0 ? (els.length - 1) : (active - 1)
                if (prev > -1) {
                    this.moveTo(els[prev])
                    this.scroll()
                }
            }
        },

        keyDown () {
            if (!this.visible) this.show()
            else {
                let els = this.getOptionsElements()
                let active = this.getFocusedElementIndex()
                let next = active >= els.length - 1 ? 0 : (active + 1)
                if (next > -1) {
                    this.moveTo(els[next])
                    this.scroll()
                }
            }
        },

        isSelected (opt) {
            return this.multiple
                ? (this.value || []).includes(opt.value)
                : opt.value === this.value
        },

        scroll () {
            let menu = this.$root.querySelector('[data-atom-menu]')
            let els = this.getOptionsElements()
            let index = els.findIndex(node => (node.getAttribute('data-option-focus', true)))
            let focus = index > -1 ? els[index] : null

            if (!focus) return

            if (index === 0) menu.scrollTop = 0
            else if (index === els.length - 1) menu.scrollTop = menu.scrollHeight
            else {
                let ceiling = 0
                let floor = menu.getBoundingClientRect().height
                let top = focus.getBoundingClientRect().top - menu.getBoundingClientRect().top
                let height = focus.getBoundingClientRect().height

                // sinked below floor, scroll down
                if (top > floor) menu.scrollTop = menu.scrollTop + (height * 2)
                // above scroll ceiling, scroll up
                else if (top < 0) menu.scrollTop = menu.scrollTop + top
            }
        },
    }
}