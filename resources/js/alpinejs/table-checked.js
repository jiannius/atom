// The total a table's checked (bulk) bar names: "12 selected" in select-all mode, and whether "Select all 12" is still on offer.
// The server prints the total on the bar as `data-total`, and a morph rewrites that attribute in place, which does not re-run an
// Alpine expression that read it (it tracks only reactive state). So this holds the number as reactive state (`_atomTotal`) and a
// MutationObserver on the attribute refreshes it, which re-runs the `x-text` and the `x-show` that read it.
//
// It is placed on the count and on the select-all button, NOT on the bar: the host's `checked` slot sits inside the bar, and an
// `x-data` there would put this scope around the host's own expressions. The name is distinctive (`_atomTotal`) for the same reason.
// The factory is a constant (`x-data="tableChecked"`); the observer is stopped by destroy(), and a re-run init() drops the previous one.
export default () => ({
    _atomTotal: 0,

    init () {
        const bar = this.$el.closest('[data-atom-table-checked]')

        if (! bar) return

        const read = () => this._atomTotal = Number(bar.dataset.total) || 0

        read()

        this.$el._atomStopTotal?.()

        const observer = new MutationObserver(read)

        observer.observe(bar, { attributes: true, attributeFilter: ['data-total'] })

        this.$el._atomStopTotal = () => observer.disconnect()
    },

    destroy () {
        this.$el._atomStopTotal?.()
        this.$el._atomStopTotal = null
    },
})
