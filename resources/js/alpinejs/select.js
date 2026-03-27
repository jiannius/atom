export default (config) => {
    return {
        text: null,
        timer: null,
        loading: false,
        visible: false,
        options: [],
        callback: typeof config.options === 'string' ? config.options : null,
        selectValue: config.multiple ? [] : null,

        get isEmpty () {
            return !this.selectedOptions || (Array.isArray(this.selectedOptions) && !this.selectedOptions.length)
        },

        get selectedOptions () {
            let options = [].concat(this.selectValue).map(val => {
                let option = this.options.find(opt => opt.value == val || opt.options?.some(groupOpt => groupOpt.value == val))
                return option?.hasOwnProperty('options')  // if the options is a grouped options
                    ? option.options.find(groupOpt => groupOpt.value == val)
                    : option
            }).filter(Boolean)

            return Array.isArray(this.selectValue) ? options : options[0]
        },

        get searchable () {
            return config.searchable && (
                this.text
                || (Array.isArray(this.options) && this.options.length > 0)
                || this.callback
            )
        },

        init () {
            this.$nextTick(() => {
                if ((config.multiple && this.selectValue?.length) || (!config.multiple && !empty(this.selectValue))) {
                    this.fetch()
                }
            })
        },

        show () {
            this.$root.querySelector('[data-atom-dropdown] > button').click()
        },

        clear () {
            this.selectValue = config.multiple ? [] : null
            this.$dispatch('input', this.selectValue)
        },

        fetch () {
            if (this.callback) {
                this.loading = true
                atom.action('get-options', { name: this.callback, filters: {
                    search: this.text,
                    value: this.selectValue,
                    ...config.filters,
                }})
                    .then(res => this.options = [...res])
                    .then(() => this.loading = false)
                    .then(() => {
                        this.setWidth()
                        this.$nextTick(() => this.$root.querySelector('[data-atom-select-search]')?.focus())
                    })
            }
            else {
                this.options = config.options.filter(opt => {
                    if (opt.hasOwnProperty('options')) {  // if the options is a grouped options
                        opt.options = opt.options.filter(option => this.isOptionMatched(option))
                        return true
                    }
                    else return this.isOptionMatched(opt)
                })

                this.setWidth()
                this.$nextTick(() => this.$root.querySelector('[data-atom-select-search]')?.focus())
            }
        },

        setWidth () {
            let container = this.$root
            let menu = this.$root.querySelector('[data-atom-menu]')
            if (container.clientWidth > menu.clientWidth) menu.style.width = container.clientWidth+'px'
        },

        select (opt) {
            if (config.multiple) {
                if (this.isSelected(opt)) return this.deselect(opt)
                else this.selectValue = [...(this.selectValue || []), ...[opt.value]]
            }
            else {
                this.selectValue = opt.value
            }

            this.text = null
            this.loading = false
            this.$dispatch('input', this.selectValue)
        },

        deselect (opt) {
            let values = [...this.selectValue]
            let index = values.findIndex(val => (val == opt.value))

            if (index > -1) {
                values.splice(index, 1)
                this.selectValue = [...values]
                this.text = null
                this.loading = false
                this.$dispatch('input', this.selectValue)
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

        isOptionMatched (option) {
            return !this.text || option.label.toLowerCase().includes(this.text.toLowerCase())
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
            this.show()

            let els = this.getOptionsElements()
            let active = this.getFocusedElementIndex()
            let prev = active <= 0 ? (els.length - 1) : (active - 1)
            if (prev > -1) {
                this.moveTo(els[prev])
                this.scroll()
            }
        },

        keyDown () {
            this.show()

            let els = this.getOptionsElements()
            let active = this.getFocusedElementIndex()
            let next = active >= els.length - 1 ? 0 : (active + 1)
            if (next > -1) {
                this.moveTo(els[next])
                this.scroll()
            }
        },

        isSelected (opt) {
            return config.multiple
                ? (this.selectValue || []).includes(opt.value)
                : opt.value === this.selectValue
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
