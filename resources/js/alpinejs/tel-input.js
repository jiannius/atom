export default (config) => {
    return {
        code: null,
        number: null,
        telValue: null,

        get options () {
            return Array.from(this.$root.querySelectorAll('option')).map(opt => (opt.getAttribute('value')))
        },

        init () {
            this.$nextTick(() => this.split())
            this.$watch('code', () => this.format())
            this.$watch('number', () => this.format())
        },

        split () {
            if (!this.telValue) return

            let code = this.options.find(opt => (this.telValue.startsWith(opt)))
            let number = code ? this.telValue.replace(code, '').replace('+', '') : this.telValue

            this.number = number || null
            this.code = code || config.code
        },

        format () {
            let code = this.code
            let number = this.number?.replace(/\s/g, '')
            let value = code && number ? `${code}${number}` : null

            this.telValue = value
        },
    }
}