import onMorphed from '../helpers/morphed'

// The row count in the toolbar's end group ("1–50 of 128"). The toolbar sits in the table's `header` slot, which is an Alpine
// <template>: the server renders it once and a re-render never refreshes the clone. So the table prints the text outside the
// template, on a hidden `data-atom-table-count-source` element (a morph keeps that one current), and this reads it: when it starts,
// and after every morph. A toolbar outside a table, or a table with no paginator, has no source and shows nothing.
export default () => {
    return {
        text: '',
        stopMorph: null,

        init() {
            this.read()

            // Livewire re-rendered the page: a filter, a search, a page or a rows-per-page change. Not tied to the morphed
            // element: the source is somewhere else in the same table, and reading it again is cheap.
            this.stopMorph = onMorphed(() => this.read())
        },

        read() {
            let source = this.$root.closest('[data-atom-table]')?.querySelector(':scope > [data-atom-table-count-source]')

            this.text = source?.dataset.text ?? ''
        },

        destroy() {
            this.stopMorph?.()
        },
    }
}
