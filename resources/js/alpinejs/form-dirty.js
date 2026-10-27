import onMorphed from '../helpers/morphed'
import { errorKeys, targets } from '../helpers/form-errors'

// The "Unsaved changes" label of a page save bar (<atom:form.actions bar dirty>): shown while the enclosing <form> holds changes that
// were not saved. The scope is the label's own (`x-data="formDirty"` on the span, so nothing of the host's `start` slot reads or is
// shadowed by these properties). Two signals:
// - a Livewire form's own `wire:model` values against what they were when the form loaded or was last saved (the signature). Not
//   `wire:dirty`: that compares the whole component's state against the server's, so a `.live` field (which the server has after 150ms)
//   or a property the form does not own would move it, and it goes quiet after ANY round trip, saved or not. Typing a character and
//   deleting it is clean again; a control that only fires an event (a lazy editor's blur, a date typed and escaped) changes nothing.
// - the form's bubbling `input` and `change` events, for a control that is NOT bound with wire:model to the form's component: the whole
//   signal of a plain form (no Livewire), of an unbound control on a Livewire one, and of a field of a child component inside the form. The select, the date picker and the editor `$dispatch('input', ...)`.
//   A control that sets its value without an event (the time picker, the date range, a rating, removing an email chip) is only seen
//   through the first signal, which needs Livewire. An event from inside a <dialog> that does not hold the form (a modal that sits in
//   the form) is not the form's.
//
// It clears on a `reset`, on the `submit` of a plain form, and in the success path of the Livewire action the form submits (the
// action named in its wire:target, from a control of this form) or that a button of its actions bar starts (a "Save draft"
// `wire:click`): when that response leaves none of the form's fields in error. A
// request that fails (offline, 5xx, cancelled) or a save that fails validation leaves the label up, and no other request clears it.
//
// The `wire:model` keys are read again on a clear and after every morph (a repeater row added or removed changes them), and the
// baseline is taken only on a clear, so a row that comes or goes is a change.
//
// The factory is a constant (`x-data="formDirty"`, no per-render value) and keeps its state across a morph.

/**
 * The `wire:model` keys of the form's fields that its own component (`root`) owns.
 */
const modelKeys = (form, root) => {
    const keys = new Set()

    form.querySelectorAll('*').forEach((el) => {
        // a field owned by a child component (its own wire:id) is that component's: $wire here cannot read it
        if (el.closest('[wire\\:id]') !== root) return

        for (const attribute of el.attributes) {
            if (attribute.name.startsWith('wire:model') && attribute.value.trim()) keys.add(attribute.value.trim())
        }
    })

    return [...keys]
}

/**
 * Whether a control (or an ancestor of it, up to the form) is bound with wire:model to the form's own component, so its value is the
 * signature's to judge. One bound to a child component inside the form is not: its value is not in the signature, so its events count.
 */
const bound = (el, form, root) => {
    for (let node = el; node && node !== form; node = node.parentElement) {
        for (const attribute of node.attributes ?? []) {
            if (attribute.name.startsWith('wire:model') && node.closest('[wire\\:id]') === root) return true
        }
    }

    return false
}

export default () => ({
    // an unbound control was typed in or picked since the form loaded, or was last saved
    touched: false,
    // the form's wire:model values differ from the baseline
    differs: false,
    form: null,
    bar: null,
    root: null,
    keys: [],
    baseline: null,
    stops: [],

    init () {
        this.form = this.$el.closest('form')

        if (! this.form) return

        this.root = this.form.closest('[wire\\:id]')
        this.bar = this.$el.closest('[data-atom-form-actions]')

        const on = (type, callback) => {
            this.form.addEventListener(type, callback)
            this.stops.push(() => this.form.removeEventListener(type, callback))
        }

        const changed = (e) => {
            const dialog = e.target?.closest?.('dialog')

            if (dialog && ! dialog.contains(this.form)) return
            if (bound(e.target, this.form, this.root)) return

            this.touched = true
        }

        on('input', changed)
        on('change', changed)
        on('reset', () => setTimeout(() => this.clear()))

        if (! this.root) {
            on('submit', () => this.clear())

            return
        }

        // after every control has settled its value (a picker normalises what it is given), not before
        this.$nextTick(() => this.$nextTick(() => {
            this.keys = modelKeys(this.form, this.root)
            this.baseline = this.signature
            this.$watch('signature', () => this.differs = this.signature !== this.baseline)
        }))

        this.stops.push(onMorphed((el) => {
            // only a morph that reaches the form can change its fields
            if (el && this.form && (el.contains(this.form) || this.form.contains(el))) this.read()
        }))

        if (typeof window.Livewire?.interceptAction === 'function') {
            const stop = window.Livewire.interceptAction((context) => this.intercept(context))

            if (typeof stop === 'function') this.stops.push(stop)
        }
    },

    destroy () {
        this.stops.forEach((stop) => stop())
        this.stops = []
    },

    get dirty () {
        return this.touched || this.differs
    },

    // the values of the form's wire:model fields, as one string; nothing, null and '' are one value (an editor writes '' for an empty
    // document, a field that was typed in and cleared is '' where it was null)
    get signature () {
        if (! this.root) return ''

        return JSON.stringify(this.keys.map((key) => this.$wire.$get(key) ?? ''))
    },

    // the wire:model keys as they are now (a morph can add or remove fields); the baseline stays
    read () {
        if (! this.form?.isConnected || this.baseline === null) return

        const keys = modelKeys(this.form, this.root)

        if (keys.join('\n') !== this.keys.join('\n')) this.keys = keys
    },

    // an action that saves the form: the one it submits (named in its wire:target, started by a control of this form, or by no one:
    // `$wire.save()`), or one started from a button in its actions bar (a "Save draft" `wire:click`). A framework action (`$set`,
    // `$refresh`) never is. Nothing else is: a plain `<form wire:submit>` that is not an <atom:form>, or a save that an Alpine `fetch`es.
    intercept ({ action, onSuccess, onFinish }) {
        if (! this.form?.isConnected || action.component.el !== this.root) return

        const origin = action.origin?.el
        const fromBar = origin && this.bar?.contains(origin) && ! action.name.startsWith('$')
        const submits = targets(this.form).includes(action.name) && (! origin || this.form.contains(origin))

        if (! fromBar && ! submits) return

        let succeeded = false

        onSuccess(() => succeeded = true)

        // after the response is merged and morphed, so the form's errors are this response's; also runs when the request failed
        onFinish(() => {
            if (! succeeded) return

            try {
                if (errorKeys(this.form, action.component).length === 0) this.clear()
            }
            catch (e) {
                console.warn('[atom] form dirty failed', e)
            }
        })
    },

    clear () {
        this.touched = false

        if (this.root && this.form?.isConnected) {
            this.keys = modelKeys(this.form, this.root)
            this.baseline = this.signature
        }

        this.differs = false
    },
})
