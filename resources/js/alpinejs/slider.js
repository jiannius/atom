export default (config = {}) => ({
    min: Number(config.min ?? 0),
    max: Number(config.max ?? 100),
    step: Number(config.step ?? 1),
    value: config.value ?? (config.min ?? 0),

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

    /** Keep the bound value inside [min, max]; ignore transient empty/NaN states. */
    clamp () {
        const n = Number(this.value)
        if (this.value === '' || Number.isNaN(n)) return
        const c = Math.min(this.max, Math.max(this.min, n))
        if (c !== n) this.value = c
    },
})
