import withDisabled from '../helpers/disabled'

export default (config = {}) => withDisabled({
    count: Number(config.count ?? 5),
    half: !!config.half,
    clearable: !!config.clearable,
    value: Number(config.value ?? 0),
    hover: null,

    // `locked` (helpers/disabled.js): disabled, or a display-only rating (`data-atom-rating-readonly` on the track), both read when the user acts.

    /** The value to paint — the hovered preview while pointing, else the committed value. */
    get display () {
        return this.hover ?? this.value
    },

    /** Filled portion of the row, 0–100, driving the clip-path fill. */
    get percent () {
        return Math.min(100, Math.max(0, (this.display / this.count) * 100))
    },

    /** Map a pointer x onto a rating value, snapped to halves or whole icons. */
    fromPointer (e) {
        const rect = this.$refs.track.getBoundingClientRect()
        const raw = ((e.clientX - rect.left) / rect.width) * this.count

        return this.half
            ? Math.min(this.count, Math.max(0.5, Math.round(raw * 2) / 2))
            : Math.min(this.count, Math.max(1, Math.ceil(raw)))
    },

    onMove (e) {
        this.hover = this.locked ? null : this.fromPointer(e)
    },

    onLeave () {
        this.hover = null
    },

    onClick (e) {
        if (this.locked) {
            return
        }

        const v = this.fromPointer(e)
        this.value = (this.clearable && v === this.value) ? 0 : v
    },

    onKey (e) {
        if (this.locked) {
            return
        }

        const step = this.half ? 0.5 : 1
        let v = this.value

        if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
            v = Math.min(this.count, v + step)
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
            v = Math.max(0, v - step)
        } else if (e.key === 'Home') {
            v = 0
        } else if (e.key === 'End') {
            v = this.count
        } else {
            return
        }

        e.preventDefault()
        this.value = v
    },
}, { name: 'locked', also: (self) => self.$refs.track?.hasAttribute('data-atom-rating-readonly') })
