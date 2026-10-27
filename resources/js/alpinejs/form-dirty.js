import { errorKeys } from '../helpers/form-errors'

// The "Unsaved changes" label of a page save bar (<atom:form.actions bar dirty>): shown while the enclosing <form> holds changes that
// were not saved. Two signals, either one is enough:
// - the form's own `input` and `change` events, which bubble. A native control fires them, and so do the atom controls that set a
//   value by hand: the select, the date picker and the editor `$dispatch('input', ...)`. This is the whole signal of a plain form
//   (no Livewire). A control that sets its value without an event (the time picker, the date range, a rating, removing an email
//   chip) is only seen through the next signal, which needs Livewire.
// - on a Livewire form, the form's own `wire:model` values against what they were when the form loaded or was last saved. Not
//   `wire:dirty`: that compares the whole component's state against the server's, so a `.live` field (which the server has after
//   150ms) or a property the form does not own would move it, and it goes quiet after ANY round trip, saved or not.
//
// It clears on a `reset`, on the `submit` of a plain form, and on the first Livewire response after a submit of a form of this
// component that leaves none of its fields in error. A save that fails validation leaves the label up.
//
// The factory is a constant (`x-data="formDirty"`, no per-render value) and keeps its state across a morph.

/**
 * The `wire:model` keys of a form's fields.
 */
const modelKeys = (form) => {
    const keys = new Set()

    form.querySelectorAll('*').forEach((el) => {
        for (const attribute of el.attributes) {
            if (attribute.name.startsWith('wire:model') && attribute.value.trim()) keys.add(attribute.value.trim())
        }
    })

    return [...keys]
}

export default () => ({
    // a field was typed in or picked since the form loaded, or was last saved
    touched: false,
    // the form's wire:model values differ from the baseline
    differs: false,
    form: null,
    root: null,
    keys: [],
    baseline: null,
    saving: false,
    stops: [],

    init () {
        this.form = this.$el.closest('form')

        if (! this.form) return

        this.root = this.form.closest('[wire\\:id]')

        const on = (type, callback) => {
            this.form.addEventListener(type, callback)
            this.stops.push(() => this.form.removeEventListener(type, callback))
        }

        on('input', () => this.touched = true)
        on('change', () => this.touched = true)
        on('reset', () => setTimeout(() => this.clear()))
        on('submit', () => this.submitted())

        if (this.root) {
            // after every control has settled its value (a picker normalises what it is given), not before
            this.$nextTick(() => this.$nextTick(() => {
                this.keys = modelKeys(this.form)
                this.baseline = this.signature
                this.$watch('signature', () => this.differs = this.signature !== this.baseline)
            }))

            if (typeof window.Livewire?.hook === 'function') {
                const stop = window.Livewire.hook('commit', ({ component, succeed }) => {
                    if (component.el !== this.root) return

                    succeed(() => setTimeout(() => this.landed(component)))
                })

                if (typeof stop === 'function') this.stops.push(stop)
            }
        }
    },

    destroy () {
        this.stops.forEach((stop) => stop())
        this.stops = []
    },

    get dirty () {
        return this.touched || this.differs
    },

    // the values of the form's wire:model fields, as one string
    get signature () {
        if (! this.root) return ''

        return JSON.stringify(this.keys.map((key) => this.$wire.$get(key)))
    },

    submitted () {
        // a Livewire form is saved when the response lands; any other form has left the page or handled the submit itself
        if (this.root) this.saving = true
        else this.clear()
    },

    // a response of this form's component: the one after a submit that left no field in error is the save
    landed (component) {
        if (! this.saving) return

        this.saving = false

        try {
            if (errorKeys(this.form, component).length === 0) this.clear()
        }
        catch (e) {
            console.warn('[atom] form dirty failed', e)
        }
    },

    clear () {
        this.touched = false
        this.baseline = this.signature
        this.differs = false
    },
})
