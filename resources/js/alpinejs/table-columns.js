import onMorphed from '../helpers/morphed'
import engine from '../helpers/table-columns'

// The column picker (components/table/columns.blade.php). It owns no state about the columns: the table's engine
// (helpers/table-columns.js) does. This reads the engine's list into `columns` (re-read when a change is announced, after a
// morph and when it starts), and turns the user's clicks into engine calls. The picker's root is wire:ignore, so Livewire never
// patches it; the table it steers is the one it sits in, or, for a toolbar outside the table, the one whose id is `data-atom-column-picker-for`.
export default () => {
    // The picker's root. `$root` is the closest x-data to the element that asks, so inside the menu's x-for clones (which sit in the
    // dropdown's own x-data) it is the dropdown's, not this one: every method after init() reads the root from here.
    let root = null

    return {
        columns: [],
        descriptionId: '',

        get table () {
            let id = root?.dataset.atomColumnPickerFor
            let found = id ? document.getElementById(id) : root?.closest('[data-atom-table]')

            return found?.matches('[data-atom-table]') ? found : null
        },

        // the hideable columns that are shown; the picker never lets the last one go (the engine does not enforce it)
        get shown () {
            return this.columns.filter((column) => column.hideable && column.visible).length
        },

        get hiddenCount () {
            return this.columns.filter((column) => column.hideable && ! column.visible).length
        },

        get description () {
            return this.hiddenCount ? (root?.dataset.hiddenTemplate || '').replace(':count', this.hiddenCount) : ''
        },

        get canReset () {
            return this.columns.some((column) => column.hideable && column.visible === column.hidden)
        },

        // Everything a row shows is worked out here: a binding in the x-for clone reads only `row`
        get rows () {
            let last = this.shown === 1

            return this.columns.map((column) => ({
                name: column.name,
                label: column.label,
                locked: ! column.hideable,
                visible: column.visible,
                disabled: ! column.hideable || (column.visible && last),
            }))
        },

        init () {
            root = this.$root
            this.descriptionId = this.$id('atom-columns-count')
            this.read()
            this.describe()

            this.$watch('hiddenCount', () => this.describe())

            // A morph can bring a new column set or a new table (a change through the engine is the root's window listener). The stop
            // function is kept on the element so a re-run init() drops the old one.
            root._atomStopColumns?.()
            root._atomStopColumns = onMorphed(() => this.read())
        },

        destroy () {
            root?._atomStopColumns?.()
            if (root) root._atomStopColumns = null
        },

        read () {
            let list = this.table ? engine.list(this.table) : []

            if (JSON.stringify(list) !== JSON.stringify(this.columns)) this.columns = list
        },

        // the count is the trigger's description, not its name (the name stays "Columns"); added to the trigger's own tokens
        describe () {
            let trigger = root?.querySelector('[data-atom-column-picker-trigger]')

            if (! trigger) return

            let tokens = (trigger.getAttribute('aria-describedby') || '').split(/\s+/).filter((token) => token && token !== this.descriptionId)

            if (this.hiddenCount) tokens.push(this.descriptionId)

            if (tokens.length) trigger.setAttribute('aria-describedby', tokens.join(' '))
            else trigger.removeAttribute('aria-describedby')
        },

        toggle (row) {
            if (row.disabled || ! this.table) return

            engine.toggle(this.table, row.name)
            this.read()
        },

        reset () {
            if (! this.canReset || ! this.table) return

            engine.reset(this.table)
            this.read()
        },

        // the nth item of the menu, wrapping, focused
        menuFocus (n) {
            let items = [...(root?.querySelectorAll('[data-atom-column-menu] [data-atom-menu-item]') ?? [])]

            items[((n % items.length) + items.length) % items.length]?.focus()
        },

        // Arrow Up and Down, Home and End walk the items (a locked one is focusable); Tab and Escape are the browser's: the
        // popover closes on Escape and gives focus back to the button. As the breadcrumbs "..." menu does.
        menuKey (event) {
            let keys = { ArrowDown: 1, ArrowUp: -1, Home: 'first', End: 'last' }

            if (! (event.key in keys)) return

            event.preventDefault()

            let items = [...event.currentTarget.querySelectorAll('[data-atom-menu-item]')]
            let step = keys[event.key]
            let next = step === 'first' ? 0 : step === 'last' ? items.length - 1 : items.indexOf(document.activeElement) + step

            items[((next % items.length) + items.length) % items.length]?.focus()
        },
    }
}
