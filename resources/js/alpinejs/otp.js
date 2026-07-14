export default (config = {}) => ({
    length: config.length ?? 6,
    submit: config.submit ?? null,
    code: '',
    digits: [],
    _completed: null,

    init () {
        this.digits = Array.from({ length: this.length }, () => '')
        // wire:model hydrates `code` via x-modelable after init — defer the split.
        this.$nextTick(() => this.split())
        this.$watch('code', () => this.split())
    },

    /** Spread the bound code string across the boxes (external / hydration). */
    split () {
        const clean = String(this.code ?? '').replace(/\D/g, '').slice(0, this.length)
        if (clean === this.digits.join('')) return
        this.digits = Array.from({ length: this.length }, (_, i) => clean[i] ?? '')
    },

    /** Recompute the bound code from the boxes and fire completion once full. */
    collect () {
        this.code = this.digits.join('')

        if (this.digits.every(d => d !== '') && this.digits.length === this.length) {
            this.complete()
        } else {
            this._completed = null
        }
    },

    /** Keep a single digit per box and advance focus. */
    onInput (index, event) {
        const digit = (event.target.value.match(/\d/g) || []).pop() || ''

        this.digits[index] = digit
        this.collect()

        if (digit && index < this.length - 1) {
            this.focusBox(index + 1)
        }
    },

    onKeydown (index, event) {
        if (event.key === 'Backspace' && !this.digits[index] && index > 0) {
            this.focusBox(index - 1)
        } else if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault()
            this.focusBox(index - 1)
        } else if (event.key === 'ArrowRight' && index < this.length - 1) {
            event.preventDefault()
            this.focusBox(index + 1)
        }
    },

    onPaste (event) {
        const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, this.length)
        if (!pasted) return

        this.digits = Array.from({ length: this.length }, (_, i) => pasted[i] ?? '')
        this.collect()
        this.focusBox(Math.min(pasted.length, this.length) - 1)
    },

    focusBox (index) {
        this.$root.querySelectorAll('[data-atom-input-otp-box]')[index]?.focus()
    },

    complete () {
        if (this._completed === this.code) return
        this._completed = this.code

        this.$dispatch('otp-completed', this.code)

        if (this.submit && this.$wire) {
            this.$wire[this.submit]?.()
        }
    },
})
