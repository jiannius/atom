import floatingui from '../helpers/floatingui'

export default (config = {}) => ({
    show: false,
    props: null,
    timer: null,
    cleanup: null,
    pointer: 0,
    options: Array.isArray(config.options) ? config.options : [],
    callback: config.callback ?? null,
    filteredOptions: [],

    init () {
        this.$el.start = (props) => this.start(props)
        this.$el.update = (props) => this.update(props)
        this.$el.exit = (props) => this.exit(props)
        this.$el.keydown = (props) => this.keydown(props)
    },

    start (props) { this.props = props; this.pointer = 0; this.fetch() },
    update (props) { this.props = props; this.fetch() },

    exit () {
        this.show = false
        this.props = null
        this.filteredOptions = []
        clearTimeout(this.timer)
        if (this.cleanup) { this.cleanup(); this.cleanup = null }
    },

    keydown (props) {
        const key = props.event.key
        if (key === 'Escape') { this.exit(); return true }
        if (key === 'Enter' && this.filteredOptions.length) {
            props.event.preventDefault()
            props.event.stopPropagation()
            this.select(this.filteredOptions[this.pointer > -1 ? this.pointer : 0])
            return true
        }
        if (key === 'ArrowUp' && this.filteredOptions.length) { this.arrowUp(); return true }
        if (key === 'ArrowDown' && this.filteredOptions.length) { this.arrowDown(); return true }
        return false
    },

    arrowUp () { this.pointer = ((this.pointer + this.filteredOptions.length) - 1) % this.filteredOptions.length; this.scroll() },
    arrowDown () { this.pointer = (this.pointer + 1) % this.filteredOptions.length; this.scroll() },

    scroll () {
        const ul = this.$refs.dropdown.querySelector('ul')
        const li = Array.from(this.$refs.dropdown.querySelectorAll('li'))[this.pointer]
        if (!ul || !li) return
        if (this.pointer === 0) ul.scrollTop = 0
        else if (this.pointer === this.filteredOptions.length - 1) ul.scrollTop = ul.scrollHeight
        else {
            const top = li.getBoundingClientRect().top - ul.getBoundingClientRect().top
            const floor = ul.getBoundingClientRect().height
            if (top > floor) ul.scrollTop += li.getBoundingClientRect().height
            else if (top < 0) ul.scrollTop += top
        }
    },

    fetch () {
        this.pointer = 0

        if (this.callback) {
            clearTimeout(this.timer)
            this.timer = setTimeout(() => {
                if (!this.props) return
                this.$wire.$call(this.callback, this.props.query)
                    .then(res => { this.filteredOptions = [...res]; this.position() })
            }, 300)
        }
        else {
            const query = (this.props.query ?? '').toLowerCase()
            this.filteredOptions = this.options.filter(opt => {
                const searchable = typeof opt === 'object'
                    ? (opt.searchable || `${opt.label ?? ''} ${opt.small ?? ''} ${opt.caption ?? ''}`).trim().toLowerCase()
                    : opt.toString().toLowerCase()
                return searchable.includes(query)
            })
            this.position()
        }
    },

    position () {
        if (this.cleanup) { this.cleanup(); this.cleanup = null }

        this.$nextTick(() => {
            if (!this.filteredOptions.length || !this.props?.clientRect) { this.show = false; return }
            const anchor = { getBoundingClientRect: () => this.props.clientRect() }
            this.cleanup = floatingui(anchor, this.$refs.dropdown, { placement: 'bottom-start', offset: 6 })
            this.show = true
        })
    },

    select (opt) {
        if (typeof opt === 'string') this.props.command({ id: opt })
        else this.props.command({ id: opt.id, label: opt.render || opt.label || opt.value })
        this.exit()
    },
})
