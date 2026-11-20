// `x-atom-rows-toggle="<selector>"` on a table root (`<atom:table rows-toggle-when-selected>`, #235): while anything is ticked, a click on a row that
// has a checkbox cell ticks or unticks that row instead of opening it (its `href`, `wire:navigate`, `wire:click` or `x-on:click`), so a stray click
// cannot throw the selection away. Two capture listeners on the table root, read at click time: the row itself prints nothing for it. The tick is the
// checkbox's own click, so select-all mode and sticky selection behave as for a click on the box (that click lands on the box itself, which the guard
// skips, so it is not read again).
//
// Left alone: a click on a control inside the row (the guard the row's href handler uses: `selector` is Attributes::ROW_INTERACTIVE, and the
// attribute prefixes below are the ones Attributes::rowGuard() walks for), Ctrl or Cmd with a click or Enter (a new tab keeps this page, and its
// selection) and a middle click. Enter on a focused row ticks it.
//
// What would open the row right after the last tick went is swallowed, not passed on, and does not tick: a second click of a double-click
// (`e.detail > 1`), an auto-repeated Enter (`e.repeat`: the first press unticked the last row, the repeat would find nothing ticked), and a click that
// ends a drag-select of the row's text (a selection that starts or ends in this row; one elsewhere on the page does not count) while rows are
// ticked. Each is stopped on a toggle row only.
const CLICK_ATTRIBUTES = ['wire:click', 'x-on:click', '@click']
const TICKED = '$wire._table?.checkboxes?.length || $wire._table?.select_all'

export default (el, { expression }, { evaluate, cleanup }) => {
    const selector = evaluate(expression)
    const ticked = () => !!evaluate(TICKED)

    const swallow = (e) => {
        e.stopPropagation()
        e.preventDefault()
    }

    // a click that landed on an interactive descendant of the row belongs to it
    const onControl = (e, row) => {
        for (const node of e.composedPath()) {
            if (node === row) break
            if (node.nodeType !== 1) continue

            if (node.matches(selector) || node.getAttributeNames().some((name) => CLICK_ATTRIBUTES.some((prefix) => name.startsWith(prefix)))) return true
        }

        return false
    }

    const click = (e) => {
        if (e.ctrlKey || e.metaKey) return

        const row = e.composedPath().find((node) => node.nodeType === 1 && node.hasAttribute('data-atom-table-row'))
        const box = row?.querySelector('[data-atom-table-checkbox]')

        if (!box || row.closest('[data-atom-table]') !== el || onControl(e, row)) return

        const isTicked = ticked()
        const selection = window.getSelection()

        if (e.detail > 1 || (isTicked && selection && !selection.isCollapsed && selection.rangeCount && (row.contains(selection.anchorNode) || row.contains(selection.focusNode)))) {
            return swallow(e)
        }

        if (!isTicked) return

        swallow(e)
        box.click()
    }

    const enter = (e) => {
        if (e.key !== 'Enter' || e.ctrlKey || e.metaKey || !e.target.matches('[data-atom-table-row]') || e.target.closest('[data-atom-table]') !== el) return

        const box = e.target.querySelector('[data-atom-table-checkbox]')

        if (!box) return
        if (e.repeat) return swallow(e)
        if (!ticked()) return

        swallow(e)
        box.click()
    }

    el.addEventListener('click', click, { capture: true })
    el.addEventListener('keydown', enter, { capture: true })

    cleanup(() => {
        el.removeEventListener('click', click, { capture: true })
        el.removeEventListener('keydown', enter, { capture: true })
    })
}
