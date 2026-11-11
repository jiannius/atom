export default (config) => {
    return {
        code: config.code,
        number: null,
        telValue: null,

        // the dial code the control last stood for, so a pick that reaches a disabled select can be put back
        shownCode: config.code,

        // the value the boxes stand for: what `format()` last wrote, or `split()` last read. A model that differs from it was pushed (a server `$set`,
        // an `x-model` parent) and is shown; one that equals it is the echo of an edit, and splitting it would fight the user's typing.
        formatted: undefined,

        get options () {
            return Array.from(this.$root.querySelectorAll('option')).map(opt => (opt.getAttribute('value')))
        },

        // Disabled is the marker the server prints on the root, read when a handler runs (never x-data: a morph does not rebuild it).
        get disabled () {
            return this.$root.hasAttribute('data-atom-disabled')
        },

        init () {
            this.$nextTick(() => this.split())

            // A value the model changed to something other than what an edit wrote is a push: show it. Splitting writes nothing back.
            this.$watch('telValue', (value) => {
                if (value !== this.formatted) this.split()
            })
        },

        // Model -> boxes. Never writes the model: a number without a dial code is shown under the default one and left as it is until the user edits.
        split () {
            this.formatted = this.telValue

            if (!this.telValue) {
                this.number = null
                this.code = config.code
                this.shownCode = this.code
                return
            }

            let code = this.options.find(opt => (this.telValue.startsWith(opt)))
            let number = code ? this.telValue.replace(code, '').replace('+', '') : this.telValue

            this.number = number || null
            this.code = code || config.code
            this.shownCode = this.code
        },

        // A pick from the native list of a select that a morph disabled while the list was open still changes the select and fires input and
        // change (WebKit keeps the list and its callback has no disabled check), and `x-model` would show the new code, which the next digit
        // typed after the control is enabled again would write into the model. Put the code back, and the select with it.
        keepCode (e) {
            if (!this.disabled) return

            this.code = this.shownCode
            e.target.value = this.shownCode
        },

        // Boxes -> model, from an edit of the number or the dial code only (a user gesture), never on load.
        format () {
            if (this.disabled) return

            let code = this.code
            let number = this.number?.replace(/\s/g, '')
            let value = code && number ? `${code}${number}` : null

            this.formatted = value
            this.shownCode = code
            this.telValue = value
        },
    }
}
