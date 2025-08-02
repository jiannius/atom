export default (config) => {
    return {
        text: null,
        pointer: null,
        emailInputValue: [],

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
            let index = this.emailInputValue.findIndex(val => (val.email === email))
            if (index > -1) this.emailInputValue.splice(index, 1)
            this.$root.querySelector('input').focus()
        },

        keyEnter () {
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