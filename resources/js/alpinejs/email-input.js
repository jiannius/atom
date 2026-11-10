import empty from '../helpers/empty'

export default (config) => {
    return {
        text: null,
        pointer: null,
        emailInputValue: [],

        // Disabled is the marker the server prints on the root, read when a handler runs (never x-data: a morph does not rebuild it).
        // A disabled control writes nothing to its model on any gesture: every way to add, remove or clear asks first.
        get disabled () {
            return this.$root.hasAttribute('data-atom-disabled')
        },

        get options () {
            return config.options
                .map(opt => (typeof opt === 'string' ? { name: opt, email: opt } : opt))
                .filter(opt => !empty(opt.email))
                .filter(opt => {
                    let search = this.text ? (opt.email.toLowerCase().includes(this.text) || opt.name.toLowerCase().includes(this.text)) : true
                    let exists = (this.emailInputValue || []).some(val => (val.email === opt.email))
                    return !exists && search
                })
        },

        select (opt) {
            if (this.disabled) return

            if (typeof opt === 'string') {
                opt.split(';')
                    .map(str => str.trim())
                    .forEach(str => this.select({ name: str, email: str }))
            }
            else {
                if (!opt.email) return
                if (!this.emailInputValue) this.emailInputValue = []
                this.emailInputValue.push(opt)
                this.text = null
            }
        },

        remove (email) {
            if (this.disabled) return

            let index = this.emailInputValue.findIndex(val => (val.email === email))
            if (index > -1) this.emailInputValue.splice(index, 1)
            this.$root.querySelector('input').focus()
        },

        clear () {
            if (this.disabled) return

            this.emailInputValue = []
        },

        keyEnter () {
            if (this.disabled) return

            if (this.text) {
                this.select(this.text)
            }
            else if (this.options.length) {
                this.select(this.options[this.pointer || 0])
            }
        },

        keyUp () {
            if (this.pointer === null) this.pointer = 0
            this.pointer--
            if (this.pointer < 0) this.pointer = 0
        },

        keyDown () {
            if (this.pointer === null) this.pointer = 0
            const max = this.options.length ? this.options.length - 1 : 0
            this.pointer++
            if (this.pointer > max) this.pointer = max
        },

        validate (val) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)
        },
    }
}