// How a control reports to a table's filter bar (components/table/filters.blade.php): it says what it holds in a
// `table-filter:set` event, the bar draws a chip from it and answers `table-filter:do-clear` when the chip's x or "Clear all"
// asks for it to be emptied. The blades hand over the magics of their own scope; what each control shows stays in the blade.
// Internal to atom's own controls: `atom.tableFilter` is not a documented host API.

/**
 * Tell the bar what the control holds now. `detail` carries `display` (null for no value) and anything else the control adds.
 */
const set = ($dispatch, key, label, detail) => $dispatch('table-filter:set', { key, label, ...detail })

/**
 * Register a control under `key`: report on start (after the first render) and after every change of `watch`, and tell the table
 * that its filters changed, so it can drop a selection. `detail()` is read at each report.
 * It must return nothing: x-init evaluates the call as an expression, and Alpine would call a returned function (the `$watch` stop function) at once.
 */
const register = ({ $dispatch, $nextTick, $watch }, { key, label, watch, detail }) => {
    const emit = () => set($dispatch, key, label, detail())

    $nextTick(emit)
    $watch(watch, () => { $nextTick(emit); $dispatch('table-filter:changed') })
}

/**
 * Whether a `table-filter:do-clear` event is for the control registered under `key`.
 */
const cleared = (event, key) => event.detail.key === key

export default { set, register, cleared }
