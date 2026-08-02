export default (config) => {
    return {
        text: null,
        timer: null,
        open: false,
        loading: false,
        options: [],
        activeIndex: -1,
        typeahead: '',
        typeaheadTimer: null,
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

        // We hold a value we cannot yet name — the options are lazy, so the label
        // for a server-set value only exists once they have been fetched.
        get hasUnlabelledValue () {
            let values = [].concat(this.selectValue).filter(val => !empty(val))
            if (!values.length) return false

            return [].concat(this.selectedOptions ?? []).filter(Boolean).length < values.length
        },

        get searchable () {
            return config.searchable && (
                this.text
                || (Array.isArray(this.options) && this.options.length > 0)
                || this.callback
            )
        },

        // The element that owns DOM focus while open and therefore carries
        // role=combobox + aria-activedescendant: the search input when the
        // select is searchable, otherwise the trigger button.
        get focusHost () {
            return config.searchable
                ? this.$root.querySelector('[data-atom-select-search]')
                : this.$root.querySelector('[data-atom-select-combobox]')
        },

        init () {
            // The dropdown's open/close events are bound here rather than with
            // x-on in the blade: a Livewire morph can drop Alpine's directive
            // listeners while keeping this data object alive, which left the
            // list stuck on "No Results" because onOpen() — the only thing that
            // fetches callback options — never ran again. The controller lives
            // on the element so a re-evaluated x-data replaces its listeners
            // instead of stacking a second set on top.
            this.$root._atomSelectListeners?.abort()
            let { signal } = this.$root._atomSelectListeners = new AbortController()

            this.$root.addEventListener('open', () => this.onOpen(), { signal })
            this.$root.addEventListener('close', () => this.onClose(), { signal })

            // Populate static (array) options eagerly so the list survives a
            // Livewire morph / re-render even before the dropdown is first
            // opened — otherwise this.options stays [] until onOpen() and an
            // un-opened filter renders "No Results" after any concurrent morph.
            // Callback (string) options stay lazy — fetched on open to avoid a
            // network round-trip per render.
            if (Array.isArray(config.options)) this.options = this.filterOptions()

            this.$nextTick(() => {
                if (this.hasUnlabelledValue) this.fetch()
            })

            // The value can also arrive AFTER init: a form modal is mounted on
            // its host page while empty, and edit() fills it in on a later
            // round-trip. Nothing refetches on its own then, so the field shows
            // a blank picker for a record it is actually holding. Picking from
            // an open list labels itself locally, so this cannot loop.
            this.$watch('selectValue', () => {
                if (this.hasUnlabelledValue) this.fetch()
            })
        },

        show () {
            this.$root.querySelector('[data-atom-dropdown] > button').click()
        },

        // Open only if closed — clicking the trigger while open would
        // light-dismiss then reopen the popover (a visible flicker).
        ensureOpen () {
            if (!this.open) this.show()
        },

        onOpen () {
            this.open = true
            this.$nextTick(() => {
                this.fetch()
                this.$nextTick(() => this.resetActive())
            })
        },

        onClose () {
            this.open = false
            this.setActive(-1)
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
                        this.$nextTick(() => {
                            this.$root.querySelector('[data-atom-select-search]')?.focus({ preventScroll: true })
                            this.resetActive()
                        })
                    })
            }
            else {
                this.options = this.filterOptions()

                this.setWidth()
                this.$nextTick(() => {
                    this.$root.querySelector('[data-atom-select-search]')?.focus({ preventScroll: true })
                    this.resetActive()
                })
            }
        },

        // Build the visible option list from the static config, honouring the
        // current search text. Returns NEW objects — never mutates
        // config.options (the shared source array), so repeated fetches and
        // re-renders can't permanently shrink a group's options.
        filterOptions () {
            return config.options
                .map(opt => opt.hasOwnProperty('options')  // grouped options
                    ? { ...opt, options: opt.options.filter(option => this.isOptionMatched(option)) }
                    : opt)
                .filter(opt => opt.hasOwnProperty('options') || this.isOptionMatched(opt))
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

        isOptionMatched (option) {
            return !this.text || option.label.toLowerCase().includes(this.text.toLowerCase())
        },

        getOptionHtml (option, selected = false) {
            if (selected && option.selected_html) {
                return option.selected_html
            }
            else if (option.html) {
                return option.html
            }

            let color = option.color
                ? '<div style="background-color: '+option.color+'" class="shrink-0 w-3 h-3 rounded-full bg-zinc-100 flex items-center justify-center"></div>'
                : ''

            return '<div class="flex items-center gap-2">'+color+'<span>'+option.label+'</span></div>'
        },

        getOptionsElements (index = -1) {
            let els = Array.from(this.$root.querySelectorAll('[data-atom-option]'))
            return index > -1 ? els[index] : els
        },

        isSelected (opt) {
            return config.multiple
                ? (this.selectValue || []).includes(opt.value)
                : opt.value === this.selectValue
        },

        // Virtual focus: move the active option without taking DOM focus off
        // the combobox host, so the user can keep typing in a searchable list.
        setActive (index) {
            let els = this.getOptionsElements()
            els.forEach(el => el.removeAttribute('data-active'))

            this.activeIndex = index
            let el = els[index]

            if (!el) {
                this.focusHost?.removeAttribute('aria-activedescendant')
                return
            }

            if (!el.id) el.id = `${this.$id('atom-select')}-opt-${index}`
            el.setAttribute('data-active', '')
            this.focusHost?.setAttribute('aria-activedescendant', el.id)
            this.revealOption(el)
        },

        // Keep the active option visible by scrolling ONLY the option list.
        // scrollIntoView() walks every scrollable ancestor up to the document, and
        // because the list lives in a top-layer popover the browser resolves that by
        // yanking the page itself — on a long page the whole window jumps.
        revealOption (el) {
            let stop = el.closest('[popover]')
            let list = el.parentElement

            while (list && list.scrollHeight <= list.clientHeight) {
                if (list === stop) return
                list = list.parentElement
            }

            if (!list) return

            let elRect = el.getBoundingClientRect()
            let listRect = list.getBoundingClientRect()

            if (elRect.top < listRect.top) {
                list.scrollTop -= listRect.top - elRect.top
            }
            else if (elRect.bottom > listRect.bottom) {
                list.scrollTop += elRect.bottom - listRect.bottom
            }
        },

        // On open / refetch, pre-activate the selected option (or none).
        resetActive () {
            let els = this.getOptionsElements()
            let selected = els.findIndex(el => el.getAttribute('aria-selected') === 'true')
            this.setActive(selected)
        },

        move (dir) {
            this.ensureOpen()

            let els = this.getOptionsElements()
            if (!els.length) return

            let next = this.activeIndex < 0
                ? (dir > 0 ? 0 : els.length - 1)
                : (this.activeIndex + dir + els.length) % els.length

            this.setActive(next)
        },

        keyUp () {
            this.move(-1)
        },

        keyDown () {
            this.move(1)
        },

        home () {
            this.ensureOpen()
            this.setActive(0)
        },

        end () {
            this.ensureOpen()
            this.setActive(this.getOptionsElements().length - 1)
        },

        enterKey () {
            if (!this.open) return this.show()
            if (this.activeIndex < 0) return

            let el = this.getOptionsElements(this.activeIndex)
            if (el) el.click()
        },

        // Type-ahead for non-searchable selects: jump to the first option whose
        // label starts with the buffered keystrokes.
        typeAhead (e) {
            if (e.key.length !== 1 || e.metaKey || e.ctrlKey || e.altKey) return

            this.ensureOpen()
            clearTimeout(this.typeaheadTimer)
            this.typeahead += e.key.toLowerCase()
            this.typeaheadTimer = setTimeout(() => this.typeahead = '', 500)

            let els = this.getOptionsElements()
            let match = els.findIndex(el => (el.getAttribute('data-label') || '').toLowerCase().startsWith(this.typeahead))
            if (match > -1) this.setActive(match)
        },
    }
}
