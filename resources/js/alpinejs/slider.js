export default (config = {}) => ({
    min: Number(config.min ?? 0),
    max: Number(config.max ?? 100),
    step: Number(config.step ?? 1),
    value: config.value ?? (config.min ?? 0),

    /**
     * Whether the server marked this slider disabled (its own `disabled`, or a disabled form's). Read from the DOM at event time, never held in `x-data`:
     * a morph re-renders the marker and does not rebuild the factory. Not reactive, so a binding must not read it.
     */
    get disabled () {
        return this.$root.hasAttribute('data-atom-disabled')
    },

    init () {
        // wire:model hydrates `value` via x-modelable after init — clamp then too.
        this.clamp()
        this.$watch('value', () => this.clamp())
    },

    /** Filled portion of the track, 0–100, driving the fill gradient + bubble position. */
    get percent () {
        const span = this.max - this.min
        if (span <= 0) return 0
        return Math.min(100, Math.max(0, ((Number(this.value) - this.min) / span) * 100))
    },

    /**
     * Keep the bound value inside [min, max]; ignore transient empty/NaN states. A disabled slider writes nothing to its model, on load either:
     * the track still draws the value clamped, and the model keeps what the server sent.
     */
    clamp () {
        if (this.disabled) return
        const n = Number(this.value)
        if (this.value === '' || Number.isNaN(n)) return
        const c = Math.min(this.max, Math.max(this.min, n))
        if (c !== n) this.value = c
    },
})
